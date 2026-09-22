<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicYearRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\GradebookComponentRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\SubjectOfferingRepository;
use DomainException;
use PDO;
use Throwable;

final class GradebookComponentService
{
    public function __construct(
        private PDO $pdo,
        private SchoolRepository $schools,
        private AcademicYearRepository $years,
        private SubjectOfferingRepository $offerings,
        private GradebookComponentRepository $components,
        private AuditLogRepository $audit
    ) {}

    /** School and actor IDs must come from the authenticated, authorized server context. */
    public function createComponent(int $schoolId, int $actorUserId, int $subjectOfferingId, string $code, string $nameTh, string $maxScore, mixed $sortOrder, ?string $ipAddress = null): int
    {
        return $this->transaction(function () use ($schoolId, $actorUserId, $subjectOfferingId, $code, $nameTh, $maxScore, $sortOrder, $ipAddress): int {
            $offering = $this->lockMutableOffering($schoolId, $subjectOfferingId);
            $values = $this->details($code, $nameTh, $maxScore, $sortOrder);
            $id = $this->components->create($schoolId, (int) $offering['academic_year_id'], $subjectOfferingId,
                $values['code'], $values['name_th'], $values['max_score'], $values['sort_order']);
            $this->audit->record($schoolId, $actorUserId, 'GRADEBOOK_COMPONENT_CREATED', 'gradebook_components', $id, null,
                ['subject_offering_id' => $subjectOfferingId] + $values + ['status' => 'ACTIVE'], null, $ipAddress);

            return $id;
        });
    }

    public function updateComponent(int $schoolId, int $actorUserId, int $subjectOfferingId, int $componentId, string $code, string $nameTh, string $maxScore, mixed $sortOrder, ?string $ipAddress = null): void
    {
        $this->transaction(function () use ($schoolId, $actorUserId, $subjectOfferingId, $componentId, $code, $nameTh, $maxScore, $sortOrder, $ipAddress): void {
            $offering = $this->lockMutableOffering($schoolId, $subjectOfferingId);
            $target = $this->target($schoolId, $subjectOfferingId, $componentId, (int) $offering['academic_year_id']);
            $values = $this->details($code, $nameTh, $maxScore, $sortOrder);
            $old = [];
            $new = [];
            foreach ($values as $field => $value) {
                if ($target[$field] !== $value) { $old[$field] = $target[$field]; $new[$field] = $value; }
            }
            if ($new === []) { return; }
            if (isset($new['max_score']) && $this->components->hasScoreHistory($schoolId, $subjectOfferingId, $componentId)) {
                throw new DomainException('ไม่สามารถเปลี่ยนคะแนนเต็มขององค์ประกอบที่มีประวัติคะแนนแล้ว');
            }
            $this->components->update($schoolId, $subjectOfferingId, $componentId,
                $values['code'], $values['name_th'], $values['max_score'], $values['sort_order']);
            $this->audit->record($schoolId, $actorUserId, 'GRADEBOOK_COMPONENT_UPDATED', 'gradebook_components', $componentId, $old, $new, null, $ipAddress);
        });
    }

    public function changeStatus(int $schoolId, int $actorUserId, int $subjectOfferingId, int $componentId, string $status, ?string $ipAddress = null): void
    {
        $this->transaction(function () use ($schoolId, $actorUserId, $subjectOfferingId, $componentId, $status, $ipAddress): void {
            $offering = $this->lockMutableOffering($schoolId, $subjectOfferingId);
            $target = $this->target($schoolId, $subjectOfferingId, $componentId, (int) $offering['academic_year_id']);
            if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) { throw new DomainException('สถานะองค์ประกอบคะแนนไม่ถูกต้อง'); }
            if ($target['status'] === $status) { return; }
            $this->components->updateStatus($schoolId, $subjectOfferingId, $componentId, $status);
            $this->audit->record($schoolId, $actorUserId, 'GRADEBOOK_COMPONENT_STATUS_CHANGED', 'gradebook_components', $componentId,
                ['status' => $target['status']], ['status' => $status], null, $ipAddress);
        });
    }

    private function lockMutableOffering(int $schoolId, int $offeringId): array
    {
        if ($this->schools->lockActiveById($schoolId) === null) { throw new DomainException('ไม่พบโรงเรียนที่เปิดใช้งาน'); }
        // Discovery only; the year and offering below are the authoritative locking reads.
        $hint = $this->offerings->findForSchool($schoolId, $offeringId);
        if ($hint === null) { throw new DomainException('ไม่พบการเปิดรายวิชาในโรงเรียนนี้'); }
        $yearId = (int) $hint['academic_year_id'];
        $year = $this->years->lockForSchool($schoolId, $yearId);
        if ($year === null || !in_array($year['status'], ['DRAFT', 'ACTIVE'], true)) {
            throw new DomainException('ไม่สามารถแก้ไขโครงสร้างคะแนนในปีการศึกษาที่ปิดแล้ว');
        }
        $offering = $this->offerings->lockForSchool($schoolId, $offeringId);
        if ($offering === null || (int) $offering['academic_year_id'] !== $yearId || $offering['status'] !== 'ACTIVE') {
            throw new DomainException('ไม่พบรายวิชาที่เปิดใช้งานสำหรับแก้ไขโครงสร้างคะแนน');
        }

        return $offering;
    }

    private function target(int $schoolId, int $offeringId, int $componentId, int $yearId): array
    {
        $target = $this->components->lockForOffering($schoolId, $offeringId, $componentId);
        if ($target === null || (int) $target['academic_year_id'] !== $yearId) {
            throw new DomainException('ไม่พบองค์ประกอบคะแนนในรายวิชานี้');
        }

        return $target;
    }

    private function details(string $code, string $nameTh, string $maxScore, mixed $sortOrder): array
    {
        return ['code' => $this->text($code, 50, 'รหัส'), 'name_th' => $this->text($nameTh, 190, 'ชื่อ'),
            'max_score' => $this->decimal($maxScore), 'sort_order' => $this->sortOrder($sortOrder)];
    }

    private function text(string $value, int $max, string $label): string
    {
        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/\p{Cc}/u', $value)) {
            throw new DomainException($label . 'ต้องเป็นข้อความ UTF-8 ที่ไม่มีอักขระควบคุม');
        }
        $value = preg_replace('/\A\s+|\s+\z/u', '', $value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > $max) { throw new DomainException($label . 'ต้องมี 1–' . $max . ' ตัวอักษร'); }

        return $value;
    }

    private function decimal(string $value): string
    {
        $value = trim($value, " \t\n\r\v\f");
        if (!preg_match('/\A([0-9]+)(?:\.([0-9]{1,2}))?\z/', $value, $parts)) {
            throw new DomainException('คะแนนเต็มต้องเป็นเลขฐานสิบ ทศนิยมไม่เกิน 2 ตำแหน่ง');
        }
        $integer = ltrim($parts[1], '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad($parts[2] ?? '', 2, '0');
        // At most five integer digits fits DECIMAL(7,2); reject zero without numeric coercion.
        if (strlen($integer) > 5 || ($integer === '0' && $fraction === '00')) {
            throw new DomainException('คะแนนเต็มต้องอยู่ระหว่าง 0.01 ถึง 99999.99');
        }

        return $integer . '.' . $fraction;
    }

    private function sortOrder(mixed $value): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/\A[0-9]+\z/', (string) $value)) {
            throw new DomainException('ลำดับต้องเป็นจำนวนเต็มตั้งแต่ 0 ถึง 65535');
        }
        $digits = ltrim((string) $value, '0');
        if (strlen($digits) > 5 || (strlen($digits) === 5 && strcmp($digits, '65535') > 0)) {
            throw new DomainException('ลำดับต้องเป็นจำนวนเต็มตั้งแต่ 0 ถึง 65535');
        }

        return $digits === '' ? 0 : (int) $digits;
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
            if ($started && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            if ($exception instanceof DomainException) { throw $exception; }
            throw new DomainException('ไม่สามารถบันทึกโครงสร้างคะแนนได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }
}
