<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicYearRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\SchoolRepository;
use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

final class AcademicYearAdministrationService
{
    public function __construct(
        private PDO $pdo,
        private SchoolRepository $schools,
        private AcademicYearRepository $years,
        private AuditLogRepository $audit
    ) {}

    public function createYear(
        int $schoolId,
        int $actorUserId,
        int $yearBe,
        ?string $startDate,
        ?string $endDate,
        ?string $ipAddress = null
    ): int {
        [$startDate, $endDate] = $this->dates($yearBe, $startDate, $endDate);

        return $this->transaction(function () use ($schoolId, $actorUserId, $yearBe, $startDate, $endDate, $ipAddress): int {
            $this->lockSchool($schoolId);
            $id = $this->years->create($schoolId, $yearBe, $startDate, $endDate);
            $this->audit->record($schoolId, $actorUserId, 'ACADEMIC_YEAR_CREATED', 'academic_years', $id, null,
                ['year_be' => $yearBe, 'start_date' => $startDate, 'end_date' => $endDate, 'status' => 'DRAFT'], null, $ipAddress);

            return $id;
        });
    }

    public function updateYear(
        int $schoolId,
        int $actorUserId,
        int $academicYearId,
        int $yearBe,
        ?string $startDate,
        ?string $endDate,
        ?string $ipAddress = null
    ): void {
        $this->transaction(function () use ($schoolId, $actorUserId, $academicYearId, $yearBe, $startDate, $endDate, $ipAddress): void {
            $this->lockSchool($schoolId);
            $target = $this->lockTarget($schoolId, $academicYearId);
            if ($target['status'] !== 'DRAFT') {
                throw new DomainException('แก้ไขรายละเอียดได้เฉพาะปีการศึกษาสถานะ DRAFT');
            }
            [$startDate, $endDate] = $this->dates($yearBe, $startDate, $endDate);
            $old = [];
            $new = [];
            foreach (['year_be' => $yearBe, 'start_date' => $startDate, 'end_date' => $endDate] as $field => $value) {
                if ($target[$field] !== $value) {
                    $old[$field] = $target[$field];
                    $new[$field] = $value;
                }
            }
            if ($new === []) {
                return;
            }
            $this->years->updateDraft($schoolId, $academicYearId, $yearBe, $startDate, $endDate);
            $this->audit->record($schoolId, $actorUserId, 'ACADEMIC_YEAR_UPDATED', 'academic_years', $academicYearId,
                $old, $new, null, $ipAddress);
        });
    }

    public function changeStatus(
        int $schoolId,
        int $actorUserId,
        int $academicYearId,
        string $status,
        ?string $ipAddress = null
    ): void {
        $this->transaction(function () use ($schoolId, $actorUserId, $academicYearId, $status, $ipAddress): void {
            // All mutations use the same school → year lock order, including activation.
            $this->lockSchool($schoolId);
            $target = $this->lockTarget($schoolId, $academicYearId);
            if (!in_array($status, ['DRAFT', 'ACTIVE', 'CLOSED'], true)) {
                throw new DomainException('สถานะปีการศึกษาไม่ถูกต้อง');
            }
            if ($target['status'] === $status) {
                return;
            }
            $next = ['DRAFT' => 'ACTIVE', 'ACTIVE' => 'CLOSED'];
            if (($next[$target['status']] ?? null) !== $status) {
                throw new DomainException('ไม่สามารถเปลี่ยนสถานะปีการศึกษาตามที่ระบุได้');
            }
            if ($status === 'ACTIVE') {
                [$startDate, $endDate] = $this->dates((int) $target['year_be'], $target['start_date'], $target['end_date']);
                if ($startDate === null || $endDate === null) {
                    throw new DomainException('กรุณาระบุวันเริ่มต้นและวันสิ้นสุดก่อนเปิดปีการศึกษา');
                }
                if ($this->years->findActiveForSchool($schoolId) !== null) {
                    throw new DomainException('โรงเรียนมีปีการศึกษาที่เปิดใช้งานอยู่แล้ว');
                }
            }
            $this->years->updateStatus($schoolId, $academicYearId, $status);
            $this->audit->record($schoolId, $actorUserId, 'ACADEMIC_YEAR_STATUS_CHANGED', 'academic_years', $academicYearId,
                ['status' => $target['status']], ['status' => $status], null, $ipAddress);
        });
    }

    private function lockSchool(int $schoolId): void
    {
        if ($this->schools->lockActiveById($schoolId) === null) {
            throw new DomainException('ไม่พบโรงเรียนที่เปิดใช้งาน');
        }
    }

    private function lockTarget(int $schoolId, int $academicYearId): array
    {
        $target = $this->years->lockForSchool($schoolId, $academicYearId);
        if ($target === null) {
            throw new DomainException('ไม่พบปีการศึกษาในโรงเรียนนี้');
        }

        return $target;
    }

    /** @return array{?string, ?string} */
    private function dates(int $yearBe, ?string $startDate, ?string $endDate): array
    {
        if ($yearBe < 2400 || $yearBe > 2700) {
            throw new DomainException('ปีการศึกษาต้องอยู่ระหว่าง พ.ศ. 2400–2700');
        }
        $startDate = $this->isoDate($startDate);
        $endDate = $this->isoDate($endDate);
        $baseYear = $yearBe - 543;
        if ($startDate !== null && (int) substr($startDate, 0, 4) !== $baseYear) {
            throw new DomainException('ปี ค.ศ. ของวันเริ่มต้นต้องตรงกับปีการศึกษา พ.ศ. ลบ 543');
        }
        if ($endDate !== null && !in_array((int) substr($endDate, 0, 4), [$baseYear, $baseYear + 1], true)) {
            throw new DomainException('ปี ค.ศ. ของวันสิ้นสุดต้องเป็นปีการศึกษา พ.ศ. ลบ 543 หรือปีถัดไป');
        }
        if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
            throw new DomainException('วันเริ่มต้นต้องไม่อยู่หลังวันสิ้นสุดปีการศึกษา');
        }

        return [$startDate, $endDate];
    }

    private function isoDate(?string $value): ?string
    {
        if ($value === null || trim($value, " \t\n\r\v\f") === '') {
            return null;
        }
        if (!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value)) {
            throw new DomainException('วันที่ต้องเป็นวันที่ ค.ศ. ที่ถูกต้องในรูปแบบ YYYY-MM-DD');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new DomainException('วันที่ต้องเป็นวันที่ ค.ศ. ที่ถูกต้องในรูปแบบ YYYY-MM-DD');
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
            throw new DomainException('ไม่สามารถบันทึกปีการศึกษาได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }
}
