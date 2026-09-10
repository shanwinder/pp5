<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicYearRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClassroomRepository;
use App\Repositories\GradeLevelRepository;
use App\Repositories\SchoolRepository;
use DomainException;
use PDO;
use Throwable;

final class ClassroomAdministrationService
{
    public function __construct(
        private PDO $pdo,
        private SchoolRepository $schools,
        private AcademicYearRepository $years,
        private GradeLevelRepository $grades,
        private ClassroomRepository $classrooms,
        private AuditLogRepository $audit
    ) {}

    public function createClassroom(int $schoolId, int $actorUserId, int $academicYearId, int $gradeLevelId, string $code, string $nameTh, ?string $ipAddress = null): int
    {
        return $this->transaction(function () use ($schoolId, $actorUserId, $academicYearId, $gradeLevelId, $code, $nameTh, $ipAddress): int {
            $this->lockSchool($schoolId);
            $this->openYear($schoolId, $academicYearId);
            [$code, $nameTh] = $this->details($gradeLevelId, $code, $nameTh);
            $id = $this->classrooms->create($schoolId, $academicYearId, $gradeLevelId, $code, $nameTh);
            $this->audit->record($schoolId, $actorUserId, 'CLASSROOM_CREATED', 'classrooms', $id, null,
                ['academic_year_id' => $academicYearId, 'grade_level_id' => $gradeLevelId, 'code' => $code, 'name_th' => $nameTh, 'status' => 'ACTIVE'], null, $ipAddress);

            return $id;
        });
    }

    public function updateClassroom(int $schoolId, int $actorUserId, int $classroomId, int $gradeLevelId, string $code, string $nameTh, ?string $ipAddress = null): void
    {
        $this->transaction(function () use ($schoolId, $actorUserId, $classroomId, $gradeLevelId, $code, $nameTh, $ipAddress): void {
            $this->lockSchool($schoolId);
            $target = $this->target($schoolId, $classroomId);
            $this->openYear($schoolId, $target['academic_year_id']);
            [$code, $nameTh] = $this->details($gradeLevelId, $code, $nameTh);
            $old = [];
            $new = [];
            foreach (['grade_level_id' => $gradeLevelId, 'code' => $code, 'name_th' => $nameTh] as $field => $value) {
                if ($target[$field] !== $value) {
                    $old[$field] = $target[$field];
                    $new[$field] = $value;
                }
            }
            if ($new === []) {
                return;
            }
            $this->classrooms->update($schoolId, $classroomId, $gradeLevelId, $code, $nameTh);
            $this->audit->record($schoolId, $actorUserId, 'CLASSROOM_UPDATED', 'classrooms', $classroomId, $old, $new, null, $ipAddress);
        });
    }

    public function changeStatus(int $schoolId, int $actorUserId, int $classroomId, string $status, ?string $ipAddress = null): void
    {
        $this->transaction(function () use ($schoolId, $actorUserId, $classroomId, $status, $ipAddress): void {
            $this->lockSchool($schoolId);
            $target = $this->target($schoolId, $classroomId);
            $this->openYear($schoolId, $target['academic_year_id']);
            if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                throw new DomainException('สถานะห้องเรียนไม่ถูกต้อง');
            }
            if ($target['status'] === $status) {
                return;
            }
            $this->classrooms->updateStatus($schoolId, $classroomId, $status);
            $this->audit->record($schoolId, $actorUserId, 'CLASSROOM_STATUS_CHANGED', 'classrooms', $classroomId,
                ['status' => $target['status']], ['status' => $status], null, $ipAddress);
        });
    }

    private function lockSchool(int $schoolId): void
    {
        // Share the academic-year service's school lock so closure and classroom writes serialize.
        if ($this->schools->lockActiveById($schoolId) === null) {
            throw new DomainException('ไม่พบโรงเรียนที่เปิดใช้งาน');
        }
    }

    private function openYear(int $schoolId, int $academicYearId): void
    {
        $year = $this->years->lockForSchool($schoolId, $academicYearId);
        if ($year === null) {
            throw new DomainException('ไม่พบปีการศึกษาในโรงเรียนนี้');
        }
        if (!in_array($year['status'], ['DRAFT', 'ACTIVE'], true)) {
            throw new DomainException('ไม่สามารถแก้ไขห้องเรียนในปีการศึกษาที่ปิดแล้ว');
        }
    }

    private function target(int $schoolId, int $classroomId): array
    {
        $target = $this->classrooms->lockForSchool($schoolId, $classroomId);
        if ($target === null) {
            throw new DomainException('ไม่พบห้องเรียนในโรงเรียนนี้');
        }

        return $target;
    }

    private function details(int $gradeLevelId, string $code, string $nameTh): array
    {
        if ($this->grades->findActiveById($gradeLevelId) === null) {
            throw new DomainException('กรุณาเลือกระดับชั้นที่เปิดใช้งาน');
        }

        return [$this->text($code, 50, 'รหัสห้องเรียน'), $this->text($nameTh, 120, 'ชื่อห้องเรียน')];
    }

    private function text(string $value, int $max, string $label): string
    {
        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/\p{Cc}/u', $value)) {
            throw new DomainException($label . 'ต้องไม่มีอักขระควบคุม');
        }
        $value = preg_replace('/\A\s+|\s+\z/u', '', $value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > $max) {
            throw new DomainException($label . 'ต้องมี 1–' . $max . ' ตัวอักษร');
        }

        return $value;
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
            throw new DomainException('ไม่สามารถบันทึกห้องเรียนได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }
}
