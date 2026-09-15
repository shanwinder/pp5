<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicYearRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClassroomRepository;
use App\Repositories\GradeLevelRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\StudentRepository;
use App\Repositories\StudentEnrollmentRepository;
use App\Repositories\StudentClassroomPlacementRepository;
use DomainException;
use PDO;
use Throwable;

final class EnrollmentAdministrationService
{
    public function __construct(
        private PDO $pdo,
        private SchoolRepository $schools,
        private AcademicYearRepository $years,
        private StudentRepository $students,
        private GradeLevelRepository $grades,
        private ClassroomRepository $classrooms,
        private StudentEnrollmentRepository $enrollments,
        private StudentClassroomPlacementRepository $placements,
        private AuditLogRepository $audit
    ) {}

    public function createEnrollment(
        int $schoolId,
        int $actorUserId,
        int $academicYearId,
        int $studentId,
        int $gradeLevelId,
        ?int $classroomId,
        ?string $entryDate,
        ?string $ipAddress = null
    ): int {
        return $this->transaction(function () use ($schoolId, $actorUserId, $academicYearId, $studentId, $gradeLevelId, $classroomId, $entryDate, $ipAddress): int {
            $this->lockSchool($schoolId);
            $year = $this->openYear($schoolId, $academicYearId);
            $student = $this->students->lockForSchool($schoolId, $studentId);
            if ($student === null || $student['status'] !== 'ACTIVE') {
                throw new DomainException('กรุณาเลือกนักเรียนที่เปิดใช้งานในโรงเรียนนี้');
            }
            if ($this->grades->findActiveById($gradeLevelId) === null) {
                throw new DomainException('กรุณาเลือกระดับชั้นที่เปิดใช้งาน');
            }
            if ($this->enrollments->findForStudentYear($schoolId, $academicYearId, $studentId) !== null) {
                throw new DomainException('นักเรียนมีการลงทะเบียนในปีการศึกษานี้แล้ว');
            }
            $this->date($entryDate, $year);
            if ($classroomId !== null) {
                $this->activeClassroom($schoolId, $academicYearId, $gradeLevelId, $classroomId);
            }
            $id = $this->enrollments->create($schoolId, $academicYearId, $studentId, $gradeLevelId, $entryDate);
            if ($classroomId !== null) {
                $this->placements->create($schoolId, $academicYearId, $gradeLevelId, $id, $classroomId);
            }
            $this->audit->record($schoolId, $actorUserId, 'STUDENT_ENROLLMENT_CREATED', 'student_enrollments', $id, null,
                ['academic_year_id' => $academicYearId, 'student_id' => $studentId, 'grade_level_id' => $gradeLevelId, 'entry_date' => $entryDate, 'status' => 'ACTIVE'], null, $ipAddress);
            if ($classroomId !== null) {
                $this->auditPlacement($schoolId, $actorUserId, $id, null, $classroomId, $ipAddress);
            }

            return $id;
        });
    }

    public function changePlacement(int $schoolId, int $actorUserId, int $enrollmentId, ?int $classroomId, ?string $ipAddress = null): void
    {
        $this->transaction(function (int $yearId) use ($schoolId, $actorUserId, $enrollmentId, $classroomId, $ipAddress): void {
            $this->lockSchool($schoolId);
            $this->openYear($schoolId, $yearId);
            $target = $this->target($schoolId, $enrollmentId, $yearId);
            $current = $this->placements->lockActiveForEnrollment($schoolId, $enrollmentId);
            if ($target['status'] !== 'ACTIVE') {
                throw new DomainException('เปลี่ยนห้องเรียนได้เฉพาะการลงทะเบียนที่ยังใช้งานอยู่');
            }
            $oldClassroomId = $current['classroom_id'] ?? null;
            if ($oldClassroomId === $classroomId) {
                return;
            }
            if ($classroomId !== null) {
                $this->activeClassroom($schoolId, $yearId, $target['grade_level_id'], $classroomId);
            }
            // The enrollment lock serializes moves even when there is no current placement.
            if ($current !== null) {
                $this->placements->end($schoolId, $current['id']);
            }
            if ($classroomId !== null) {
                $this->placements->create($schoolId, $yearId, $target['grade_level_id'], $enrollmentId, $classroomId);
            }
            $this->auditPlacement($schoolId, $actorUserId, $enrollmentId, $oldClassroomId, $classroomId, $ipAddress);
        }, fn (): int => $this->preReadYear($schoolId, $enrollmentId));
    }

    public function changeStatus(int $schoolId, int $actorUserId, int $enrollmentId, string $status, ?string $exitDate, ?string $ipAddress = null): void
    {
        $this->transaction(function (int $yearId) use ($schoolId, $actorUserId, $enrollmentId, $status, $exitDate, $ipAddress): void {
            $this->lockSchool($schoolId);
            $year = $this->openYear($schoolId, $yearId);
            $target = $this->target($schoolId, $enrollmentId, $yearId);
            $current = $this->placements->lockActiveForEnrollment($schoolId, $enrollmentId);
            if (!in_array($status, ['ACTIVE', 'TRANSFERRED_OUT', 'WITHDRAWN'], true)) {
                throw new DomainException('สถานะการลงทะเบียนไม่ถูกต้อง');
            }
            if ($target['status'] === $status) {
                return;
            }
            if ($target['status'] !== 'ACTIVE' || !in_array($status, ['TRANSFERRED_OUT', 'WITHDRAWN'], true)) {
                throw new DomainException('ไม่สามารถเปลี่ยนสถานะการลงทะเบียนที่สิ้นสุดแล้ว');
            }
            if ($exitDate === null) {
                throw new DomainException('กรุณาระบุวันที่สิ้นสุดการลงทะเบียน');
            }
            $this->date($exitDate, $year);
            if ($target['entry_date'] !== null && $exitDate < $target['entry_date']) {
                throw new DomainException('วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มลงทะเบียน');
            }
            if ($current !== null) {
                $this->placements->end($schoolId, $current['id']);
            }
            $this->enrollments->updateStatus($schoolId, $enrollmentId, $status, $exitDate);
            $this->audit->record($schoolId, $actorUserId, 'STUDENT_ENROLLMENT_STATUS_CHANGED', 'student_enrollments', $enrollmentId,
                ['status' => $target['status'], 'exit_date' => $target['exit_date']], ['status' => $status, 'exit_date' => $exitDate], null, $ipAddress);
            if ($current !== null) {
                $this->auditPlacement($schoolId, $actorUserId, $enrollmentId, $current['classroom_id'], null, $ipAddress);
            }
        }, fn (): int => $this->preReadYear($schoolId, $enrollmentId));
    }

    private function preReadYear(int $schoolId, int $enrollmentId): int
    {
        $target = $this->enrollments->findForSchool($schoolId, $enrollmentId);
        if ($target === null) {
            throw new DomainException('ไม่พบการลงทะเบียนในโรงเรียนนี้');
        }

        return $target['academic_year_id'];
    }

    private function target(int $schoolId, int $enrollmentId, int $yearId): array
    {
        $target = $this->enrollments->lockForSchool($schoolId, $enrollmentId);
        if ($target === null || $target['academic_year_id'] !== $yearId) {
            throw new DomainException('ไม่พบการลงทะเบียนในโรงเรียนนี้');
        }

        return $target;
    }

    private function lockSchool(int $schoolId): void
    {
        if ($this->schools->lockActiveById($schoolId) === null) {
            throw new DomainException('ไม่พบโรงเรียนที่เปิดใช้งาน');
        }
    }

    private function openYear(int $schoolId, int $yearId): array
    {
        $year = $this->years->lockForSchool($schoolId, $yearId);
        if ($year === null) {
            throw new DomainException('ไม่พบปีการศึกษาในโรงเรียนนี้');
        }
        if (!in_array($year['status'], ['DRAFT', 'ACTIVE'], true)) {
            throw new DomainException('ไม่สามารถเปลี่ยนการลงทะเบียนในปีการศึกษาที่ปิดแล้ว');
        }

        return $year;
    }

    private function activeClassroom(int $schoolId, int $yearId, int $gradeId, int $classroomId): void
    {
        $classroom = $this->classrooms->lockForSchool($schoolId, $classroomId);
        if ($classroom === null || $classroom['academic_year_id'] !== $yearId || $classroom['grade_level_id'] !== $gradeId || $classroom['status'] !== 'ACTIVE') {
            throw new DomainException('กรุณาเลือกห้องเรียนที่เปิดใช้งานในโรงเรียน ปีการศึกษา และระดับชั้นนี้');
        }
    }

    private function date(?string $date, array $year): void
    {
        if ($date === null) {
            return;
        }
        if (!preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $date, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new DomainException('วันที่ต้องเป็นวันที่ YYYY-MM-DD ที่ถูกต้อง');
        }
        if (($year['start_date'] !== null && $date < $year['start_date']) || ($year['end_date'] !== null && $date > $year['end_date'])) {
            throw new DomainException('วันที่ต้องอยู่ภายในช่วงปีการศึกษา');
        }
    }

    private function auditPlacement(int $schoolId, int $actorUserId, int $enrollmentId, ?int $oldClassroomId, ?int $newClassroomId, ?string $ipAddress): void
    {
        $this->audit->record($schoolId, $actorUserId, 'STUDENT_CLASSROOM_PLACEMENT_CHANGED', 'student_enrollments', $enrollmentId,
            ['classroom_id' => $oldClassroomId], ['classroom_id' => $newClassroomId], null, $ipAddress);
    }

    private function transaction(callable $operation, ?callable $prepare = null): mixed
    {
        $started = false;
        try {
            // Pre-read failures are sanitized too; the returned year is rechecked under locks.
            $context = $prepare === null ? null : $prepare();
            $this->pdo->beginTransaction();
            $started = true;
            $result = $prepare === null ? $operation() : $operation($context);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('ไม่สามารถบันทึกการลงทะเบียนได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }
}
