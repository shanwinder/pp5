<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicYearRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClassroomRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\SubjectOfferingRepository;
use DomainException;
use PDO;
use Throwable;

final class SubjectOfferingAdministrationService
{
    public function __construct(
        private PDO $pdo,
        private SchoolRepository $schools,
        private AcademicYearRepository $years,
        private ClassroomRepository $classrooms,
        private SubjectRepository $subjects,
        private SubjectOfferingRepository $offerings,
        private AuditLogRepository $audit
    ) {}

    public function createOffering(int $schoolId, int $actorUserId, int $academicYearId, int $classroomId, int $subjectId, int $termNo, ?string $ipAddress = null): int
    {
        return $this->transaction(function () use ($schoolId, $actorUserId, $academicYearId, $classroomId, $subjectId, $termNo, $ipAddress): int {
            $this->lockSchool($schoolId);
            $this->openYear($schoolId, $academicYearId);
            $this->activeParents($schoolId, $academicYearId, $classroomId, $subjectId, $termNo);
            $id = $this->offerings->create($schoolId, $academicYearId, $classroomId, $subjectId, $termNo);
            $this->audit->record($schoolId, $actorUserId, 'SUBJECT_OFFERING_CREATED', 'subject_offerings', $id, null,
                ['academic_year_id' => $academicYearId, 'classroom_id' => $classroomId, 'subject_id' => $subjectId, 'term_no' => $termNo, 'status' => 'ACTIVE'], null, $ipAddress);

            return $id;
        });
    }

    public function updateOffering(int $schoolId, int $actorUserId, int $offeringId, int $classroomId, int $subjectId, int $termNo, ?string $ipAddress = null): void
    {
        $this->transaction(function () use ($schoolId, $actorUserId, $offeringId, $classroomId, $subjectId, $termNo, $ipAddress): void {
            $this->lockSchool($schoolId);
            $target = $this->target($schoolId, $offeringId);
            $this->openYear($schoolId, $target['academic_year_id']);
            $this->activeParents($schoolId, $target['academic_year_id'], $classroomId, $subjectId, $termNo);
            $old = []; $new = [];
            foreach (['classroom_id' => $classroomId, 'subject_id' => $subjectId, 'term_no' => $termNo] as $field => $value) {
                if ($target[$field] !== $value) {
                    $old[$field] = $target[$field];
                    $new[$field] = $value;
                }
            }
            if ($new === []) {
                return;
            }
            $this->offerings->update($schoolId, $offeringId, $classroomId, $subjectId, $termNo);
            $this->audit->record($schoolId, $actorUserId, 'SUBJECT_OFFERING_UPDATED', 'subject_offerings', $offeringId, $old, $new, null, $ipAddress);
        });
    }

    public function changeStatus(int $schoolId, int $actorUserId, int $offeringId, string $status, ?string $ipAddress = null): void
    {
        $this->transaction(function () use ($schoolId, $actorUserId, $offeringId, $status, $ipAddress): void {
            $this->lockSchool($schoolId);
            $target = $this->target($schoolId, $offeringId);
            $this->openYear($schoolId, $target['academic_year_id']);
            if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                throw new DomainException('สถานะการเปิดรายวิชาไม่ถูกต้อง');
            }
            if ($target['status'] === $status) {
                return;
            }
            // Inactivation remains available when a parent has since become inactive.
            if ($status === 'ACTIVE') {
                $this->activeParents($schoolId, $target['academic_year_id'], $target['classroom_id'], $target['subject_id'], $target['term_no']);
            }
            $this->offerings->updateStatus($schoolId, $offeringId, $status);
            $this->audit->record($schoolId, $actorUserId, 'SUBJECT_OFFERING_STATUS_CHANGED', 'subject_offerings', $offeringId,
                ['status' => $target['status']], ['status' => $status], null, $ipAddress);
        });
    }

    private function lockSchool(int $schoolId): void
    {
        // All academic mutations share this first lock, including year closure and parent status changes.
        if ($this->schools->lockActiveById($schoolId) === null) {
            throw new DomainException('ไม่พบโรงเรียนที่เปิดใช้งาน');
        }
    }

    private function target(int $schoolId, int $offeringId): array
    {
        $target = $this->offerings->lockForSchool($schoolId, $offeringId);
        if ($target === null) {
            throw new DomainException('ไม่พบการเปิดรายวิชาในโรงเรียนนี้');
        }

        return $target;
    }

    private function openYear(int $schoolId, int $academicYearId): void
    {
        $year = $this->years->lockForSchool($schoolId, $academicYearId);
        if ($year === null) {
            throw new DomainException('ไม่พบปีการศึกษาในโรงเรียนนี้');
        }
        if (!in_array($year['status'], ['DRAFT', 'ACTIVE'], true)) {
            throw new DomainException('ไม่สามารถแก้ไขการเปิดรายวิชาในปีการศึกษาที่ปิดแล้ว');
        }
    }

    private function activeParents(int $schoolId, int $academicYearId, int $classroomId, int $subjectId, int $termNo): void
    {
        $classroom = $this->classrooms->lockForSchool($schoolId, $classroomId);
        if ($classroom === null || $classroom['academic_year_id'] !== $academicYearId || $classroom['status'] !== 'ACTIVE') {
            throw new DomainException('กรุณาเลือกห้องเรียนที่เปิดใช้งานในโรงเรียนและปีการศึกษานี้');
        }
        $subject = $this->subjects->lockForSchool($schoolId, $subjectId);
        if ($subject === null || $subject['status'] !== 'ACTIVE') {
            throw new DomainException('กรุณาเลือกรายวิชาที่เปิดใช้งานในโรงเรียนนี้');
        }
        if (!in_array($termNo, [1, 2], true)) {
            throw new DomainException('ภาคเรียนต้องเป็น 1 หรือ 2');
        }
    }

    private function transaction(callable $operation): mixed
    {
        $started = false;
        try {
            $this->pdo->beginTransaction();
            $started = true;
            $result = $operation();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('ไม่สามารถบันทึกการเปิดรายวิชาได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }
}
