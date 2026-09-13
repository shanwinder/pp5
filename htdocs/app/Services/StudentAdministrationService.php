<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\StudentRepository;
use App\Repositories\StudentEnrollmentRepository;
use App\Validation\StudentProfileRules;
use DomainException;
use PDO;
use Throwable;

final class StudentAdministrationService
{
    public function __construct(
        private PDO $pdo,
        private SchoolRepository $schools,
        private StudentRepository $students,
        private StudentEnrollmentRepository $enrollments,
        private AuditLogRepository $audit
    ) {}

    public function createStudent(
        int $schoolId,
        int $actorUserId,
        string $studentCode,
        ?string $nationalId,
        string $prefixTh,
        string $firstNameTh,
        string $lastNameTh,
        ?string $genderCode,
        ?string $birthDate,
        ?string $ipAddress = null
    ): int {
        return $this->transaction(function () use ($schoolId, $actorUserId, $studentCode, $nationalId, $prefixTh, $firstNameTh, $lastNameTh, $genderCode, $birthDate, $ipAddress): int {
            $this->lockSchool($schoolId);
            $profile = StudentProfileRules::normalize($studentCode, $nationalId, $prefixTh, $firstNameTh, $lastNameTh, $genderCode, $birthDate);
            $id = $this->students->create($schoolId, $profile);
            $this->audit->record($schoolId, $actorUserId, 'STUDENT_CREATED', 'students', $id, null,
                ['status' => 'ACTIVE', 'has_national_id' => $profile['national_id'] !== null], null, $ipAddress);

            return $id;
        });
    }

    public function updateStudent(
        int $schoolId,
        int $actorUserId,
        int $studentId,
        string $studentCode,
        ?string $nationalId,
        string $prefixTh,
        string $firstNameTh,
        string $lastNameTh,
        ?string $genderCode,
        ?string $birthDate,
        ?string $ipAddress = null
    ): void {
        $this->transaction(function () use ($schoolId, $actorUserId, $studentId, $studentCode, $nationalId, $prefixTh, $firstNameTh, $lastNameTh, $genderCode, $birthDate, $ipAddress): void {
            $this->lockSchool($schoolId);
            $profile = StudentProfileRules::normalize($studentCode, $nationalId, $prefixTh, $firstNameTh, $lastNameTh, $genderCode, $birthDate);
            $target = $this->target($schoolId, $studentId);
            $changedFields = [];
            foreach ($profile as $field => $value) {
                if ($target[$field] !== $value) {
                    $changedFields[] = $field;
                }
            }
            if ($changedFields === []) {
                return;
            }
            $this->students->update($schoolId, $studentId, $profile);
            $this->audit->record($schoolId, $actorUserId, 'STUDENT_UPDATED', 'students', $studentId, null,
                ['changed_fields' => $changedFields], null, $ipAddress);
        });
    }

    public function changeStatus(int $schoolId, int $actorUserId, int $studentId, string $status, ?string $ipAddress = null): void
    {
        $this->transaction(function () use ($schoolId, $actorUserId, $studentId, $status, $ipAddress): void {
            $this->lockSchool($schoolId);
            if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                throw new DomainException('สถานะนักเรียนไม่ถูกต้อง');
            }
            $target = $this->target($schoolId, $studentId);
            if ($target['status'] === $status) {
                return;
            }
            if ($status === 'INACTIVE' && $this->enrollments->hasActiveInOpenYear($schoolId, $studentId)) {
                throw new DomainException('ไม่สามารถปิดใช้งานนักเรียนที่ยังลงทะเบียนในปีการศึกษาที่เปิดอยู่');
            }
            $this->students->updateStatus($schoolId, $studentId, $status);
            $this->audit->record($schoolId, $actorUserId, 'STUDENT_STATUS_CHANGED', 'students', $studentId,
                ['status' => $target['status']], ['status' => $status], null, $ipAddress);
        });
    }

    private function lockSchool(int $schoolId): void
    {
        if ($this->schools->lockActiveById($schoolId) === null) {
            throw new DomainException('ไม่พบโรงเรียนที่เปิดใช้งาน');
        }
    }

    private function target(int $schoolId, int $studentId): array
    {
        $target = $this->students->lockForSchool($schoolId, $studentId);
        if ($target === null) {
            throw new DomainException('ไม่พบนักเรียนในโรงเรียนนี้');
        }

        return $target;
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
            throw new DomainException('ไม่สามารถบันทึกข้อมูลนักเรียนได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }
}
