<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\SubjectRepository;
use DomainException;
use PDO;
use Throwable;

final class SubjectAdministrationService
{
    public function __construct(
        private PDO $pdo,
        private SchoolRepository $schools,
        private SubjectRepository $subjects,
        private AuditLogRepository $audit
    ) {}

    public function createSubject(int $schoolId, int $actorUserId, string $code, string $nameTh, ?string $ipAddress = null): int
    {
        return $this->transaction(function () use ($schoolId, $actorUserId, $code, $nameTh, $ipAddress): int {
            $this->lockSchool($schoolId);
            [$code, $nameTh] = $this->details($code, $nameTh);
            $id = $this->subjects->create($schoolId, $code, $nameTh);
            $this->audit->record($schoolId, $actorUserId, 'SUBJECT_CREATED', 'subjects', $id, null,
                ['code' => $code, 'name_th' => $nameTh, 'status' => 'ACTIVE'], null, $ipAddress);

            return $id;
        });
    }

    public function updateSubject(int $schoolId, int $actorUserId, int $subjectId, string $code, string $nameTh, ?string $ipAddress = null): void
    {
        $this->transaction(function () use ($schoolId, $actorUserId, $subjectId, $code, $nameTh, $ipAddress): void {
            $this->lockSchool($schoolId);
            $target = $this->target($schoolId, $subjectId);
            [$code, $nameTh] = $this->details($code, $nameTh);
            $old = [];
            $new = [];
            foreach (['code' => $code, 'name_th' => $nameTh] as $field => $value) {
                if ($target[$field] !== $value) {
                    $old[$field] = $target[$field];
                    $new[$field] = $value;
                }
            }
            if ($new === []) {
                return;
            }
            $this->subjects->update($schoolId, $subjectId, $code, $nameTh);
            $this->audit->record($schoolId, $actorUserId, 'SUBJECT_UPDATED', 'subjects', $subjectId, $old, $new, null, $ipAddress);
        });
    }

    public function changeStatus(int $schoolId, int $actorUserId, int $subjectId, string $status, ?string $ipAddress = null): void
    {
        $this->transaction(function () use ($schoolId, $actorUserId, $subjectId, $status, $ipAddress): void {
            $this->lockSchool($schoolId);
            $target = $this->target($schoolId, $subjectId);
            if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                throw new DomainException('สถานะรายวิชาไม่ถูกต้อง');
            }
            if ($target['status'] === $status) {
                return;
            }
            $this->subjects->updateStatus($schoolId, $subjectId, $status);
            $this->audit->record($schoolId, $actorUserId, 'SUBJECT_STATUS_CHANGED', 'subjects', $subjectId,
                ['status' => $target['status']], ['status' => $status], null, $ipAddress);
        });
    }

    private function lockSchool(int $schoolId): void
    {
        if ($this->schools->lockActiveById($schoolId) === null) {
            throw new DomainException('ไม่พบโรงเรียนที่เปิดใช้งาน');
        }
    }

    private function target(int $schoolId, int $subjectId): array
    {
        $target = $this->subjects->lockForSchool($schoolId, $subjectId);
        if ($target === null) {
            throw new DomainException('ไม่พบรายวิชาในโรงเรียนนี้');
        }

        return $target;
    }

    private function details(string $code, string $nameTh): array
    {
        return [$this->text($code, 50, 'รหัสรายวิชา'), $this->text($nameTh, 190, 'ชื่อรายวิชา')];
    }

    private function text(string $value, int $max, string $label): string
    {
        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/\p{Cc}/u', $value)) {
            throw new DomainException($label . 'ต้องเป็นข้อความ UTF-8 ที่ไม่มีอักขระควบคุม');
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
            throw new DomainException('ไม่สามารถบันทึกรายวิชาได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }
}
