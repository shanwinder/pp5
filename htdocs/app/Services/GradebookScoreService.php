<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicYearRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\GradebookComponentRepository;
use App\Repositories\GradebookScoreRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\StudentClassroomPlacementRepository;
use App\Repositories\StudentEnrollmentRepository;
use App\Repositories\SubjectOfferingRepository;
use DomainException;
use PDO;
use Throwable;

final class GradebookScoreService
{
    public const MAX_BATCH_CELLS = 2000;
    public const MAX_BATCH_BYTES = 262144;

    private const DENIED = 'ไม่สามารถบันทึกคะแนนในรายวิชานี้ได้';
    private const INVALID_SCORE = 'คะแนนไม่ถูกต้องหรือเกินคะแนนเต็ม';

    public function __construct(
        private PDO $pdo,
        private SchoolRepository $schools,
        private AcademicYearRepository $years,
        private SubjectOfferingRepository $offerings,
        private GradebookComponentRepository $components,
        private StudentEnrollmentRepository $enrollments,
        private StudentClassroomPlacementRepository $placements,
        private AuthorizationService $authorization,
        private GradebookScoreRepository $scores,
        private AuditLogRepository $audit
    ) {}

    /** School and actor are trusted server-context IDs. PHP null is the only clear sentinel. */
    public function setScore(int $schoolId, int $actorUserId, int $offeringId, int $componentId, int $enrollmentId, ?string $score, ?string $ipAddress = null): array
    {
        return $this->transaction(function () use ($schoolId, $actorUserId, $offeringId, $componentId, $enrollmentId, $score, $ipAddress): array {
            [$yearId, $offering] = $this->lockContext($schoolId, $offeringId);
            $component = $this->lockComponent($schoolId, $yearId, $offeringId, $componentId);
            $this->lockEnrollment($schoolId, $yearId, $offering, $enrollmentId);
            $this->authorize($schoolId, $actorUserId, $offeringId);
            // Parent locks serialize creation too, including when this cell does not yet exist.
            $cell = $this->scores->lockCell($schoolId, $yearId, $offeringId, $enrollmentId, $componentId);
            $normalized = $this->normalize($score, $component['max_score']);
            return $this->mutateCell($schoolId, $yearId, $actorUserId, $offeringId, $componentId, $enrollmentId, $normalized, $cell, $ipAddress);
        });
    }

    /** One rectangular command; IDs are locators and values are literal clipboard fields. */
    public function setScoresBatch(int $schoolId, int $actorUserId, int $offeringId, array $matrix, ?string $ipAddress = null): array
    {
        $this->validateMatrix($matrix);
        return $this->transaction(function () use ($schoolId, $actorUserId, $offeringId, $matrix, $ipAddress): array {
            [$yearId, $offering] = $this->lockContext($schoolId, $offeringId);
            $componentIds = $matrix['component_ids'];
            $enrollmentIds = $matrix['enrollment_ids'];
            sort($componentIds, SORT_NUMERIC);
            sort($enrollmentIds, SORT_NUMERIC);
            $components = [];
            foreach ($componentIds as $id) { $components[$id] = $this->lockComponent($schoolId, $yearId, $offeringId, $id); }
            foreach ($enrollmentIds as $id) { $this->lockEnrollment($schoolId, $yearId, $offering, $id); }
            $this->authorize($schoolId, $actorUserId, $offeringId);
            // Names are fetched only after every relationship and the live grant are validated.
            $names = $this->enrollments->displayNamesForSchoolYear($schoolId, $yearId, $enrollmentIds);
            $normalized = [];
            foreach ($matrix['enrollment_ids'] as $row => $enrollmentId) {
                foreach ($matrix['component_ids'] as $column => $componentId) {
                    $value = $matrix['values'][$row][$column];
                    try {
                        $score = $this->normalize($value === '' ? null : $value, $components[$componentId]['max_score']);
                    } catch (DomainException) {
                        throw new GradebookBatchException('บันทึกไม่ได้: นักเรียน ' . ($names[$enrollmentId] ?? ('แถวที่ ' . ($row + 1)))
                            . ' · หัวข้อคะแนน “' . $components[$componentId]['name_th'] . '” คะแนนไม่ถูกต้องหรือเกินคะแนนเต็ม '
                            . $components[$componentId]['max_score'],
                            ['row' => $row + 1, 'column' => $column + 1, 'enrollment_id' => $enrollmentId, 'component_id' => $componentId]);
                    }
                    $normalized[$enrollmentId][$componentId] = $score;
                }
            }
            // All targets/values pass before any score mutation. Locks and writes use numeric IDs,
            // independently of the browser's display order. Parent locks also cover absent cells.
            $existing = [];
            foreach ($enrollmentIds as $enrollmentId) {
                foreach ($componentIds as $componentId) {
                    $existing[$enrollmentId][$componentId] = $this->scores->lockCell($schoolId, $yearId, $offeringId, $enrollmentId, $componentId);
                }
            }
            $results = [];
            $changed = 0;
            foreach ($enrollmentIds as $enrollmentId) {
                foreach ($componentIds as $componentId) {
                    $cell = $this->mutateCell($schoolId, $yearId, $actorUserId, $offeringId, $componentId, $enrollmentId,
                        $normalized[$enrollmentId][$componentId], $existing[$enrollmentId][$componentId], $ipAddress);
                    $results[$enrollmentId][$componentId] = $cell;
                    if ($cell['changed']) { ++$changed; }
                }
            }
            $cells = [];
            foreach ($matrix['enrollment_ids'] as $enrollmentId) {
                foreach ($matrix['component_ids'] as $componentId) { $cells[] = $results[$enrollmentId][$componentId]; }
            }
            return ['offering_id' => $offeringId, 'row_count' => count($enrollmentIds), 'column_count' => count($componentIds),
                'targeted_count' => count($cells), 'changed_count' => $changed, 'cells' => $cells];
        });
    }

    private function validateMatrix(array $matrix): void
    {
        $invalid = 'รูปแบบตารางคะแนนไม่ถูกต้อง ต้องเป็นสี่เหลี่ยมและไม่มีช่องซ้ำ (สูงสุด ' . self::MAX_BATCH_CELLS . ' ช่อง)';
        $keys = array_keys($matrix); sort($keys);
        if ($keys !== ['component_ids', 'enrollment_ids', 'values']) { throw new DomainException($invalid); }
        foreach (['enrollment_ids', 'component_ids', 'values'] as $key) {
            if (!is_array($matrix[$key]) || !array_is_list($matrix[$key]) || $matrix[$key] === []) { throw new DomainException($invalid); }
        }
        $rows = count($matrix['enrollment_ids']); $columns = count($matrix['component_ids']);
        if ($rows > self::MAX_BATCH_CELLS || $columns > self::MAX_BATCH_CELLS || $rows * $columns > self::MAX_BATCH_CELLS
            || count($matrix['values']) !== $rows) { throw new DomainException($invalid); }
        foreach (['enrollment_ids', 'component_ids'] as $key) {
            $seen = [];
            foreach ($matrix[$key] as $id) {
                if (!is_int($id) || $id <= 0 || isset($seen[$id])) { throw new DomainException($invalid); }
                $seen[$id] = true;
            }
        }
        $bytes = 0;
        foreach ($matrix['values'] as $row) {
            if (!is_array($row) || !array_is_list($row) || count($row) !== $columns) { throw new DomainException($invalid); }
            foreach ($row as $value) {
                if (!is_string($value)) { throw new DomainException($invalid); }
                $bytes += strlen($value);
                if ($bytes > self::MAX_BATCH_BYTES) { throw new DomainException($invalid); }
            }
        }
    }

    private function lockContext(int $schoolId, int $offeringId): array
    {
        if ($this->schools->lockActiveById($schoolId) === null) { throw new DomainException(self::DENIED); }
        // Discovery only. Current year/offering rows below are authoritative.
        $hint = $this->offerings->findForSchool($schoolId, $offeringId);
        if ($hint === null) { throw new DomainException(self::DENIED); }
        $yearId = (int) $hint['academic_year_id'];
        $year = $this->years->lockForSchool($schoolId, $yearId);
        if ($year === null || !in_array($year['status'], ['DRAFT', 'ACTIVE'], true)) { throw new DomainException(self::DENIED); }
        $offering = $this->offerings->lockForSchool($schoolId, $offeringId);
        if ($offering === null || (int) $offering['academic_year_id'] !== $yearId || $offering['status'] !== 'ACTIVE') {
            throw new DomainException(self::DENIED);
        }
        return [$yearId, $offering];
    }

    private function lockComponent(int $schoolId, int $yearId, int $offeringId, int $componentId): array
    {
        $component = $this->components->lockForOffering($schoolId, $offeringId, $componentId);
        if ($component === null || (int) $component['academic_year_id'] !== $yearId || $component['status'] !== 'ACTIVE') {
            throw new DomainException(self::DENIED);
        }
        return $component;
    }

    private function lockEnrollment(int $schoolId, int $yearId, array $offering, int $enrollmentId): void
    {
        $enrollment = $this->enrollments->lockForSchool($schoolId, $enrollmentId);
        if ($enrollment === null || (int) $enrollment['academic_year_id'] !== $yearId || $enrollment['status'] !== 'ACTIVE') {
            throw new DomainException(self::DENIED);
        }
        $placement = $this->placements->lockActiveForEnrollment($schoolId, $enrollmentId);
        if ($placement === null || (int) $placement['academic_year_id'] !== $yearId || (int) $placement['classroom_id'] !== (int) $offering['classroom_id']) {
            throw new DomainException(self::DENIED);
        }
    }

    private function authorize(int $schoolId, int $actorUserId, int $offeringId): void
    {
        if (!$this->authorization->hasSubjectOfferingPermissionForUpdate($actorUserId, 'SCHOOL', $schoolId, $offeringId, 'GRADEBOOK_SCORE_ENTER')) {
            throw new DomainException(self::DENIED);
        }
    }

    private function normalize(?string $score, string $maximum): ?string
    {
        $normalized = $score === null ? null : $this->decimal($score);
        if ($normalized !== null && $this->exceeds($normalized, $maximum)) { throw new DomainException(self::INVALID_SCORE); }
        return $normalized;
    }

    /** Caller owns the transaction and all validation/locks. Never begins or commits. */
    private function mutateCell(int $schoolId, int $yearId, int $actorUserId, int $offeringId, int $componentId,
        int $enrollmentId, ?string $normalized, ?array $cell, ?string $ipAddress): array
    {
        $oldScore = $cell['score'] ?? null;
        $id = $cell === null ? null : (int) $cell['id'];
        $changed = $oldScore !== $normalized;
        if ($changed) {
            if ($cell === null) {
                $id = $this->scores->create($schoolId, $yearId, $offeringId, $enrollmentId, $componentId, $normalized, $actorUserId);
            } else {
                // Clearing retains the row as historical roster evidence.
                $this->scores->updateScore($schoolId, $yearId, $offeringId, $enrollmentId, $componentId, $normalized, $actorUserId);
            }
            $identity = ['subject_offering_id' => $offeringId, 'enrollment_id' => $enrollmentId, 'component_id' => $componentId];
            $this->audit->record($schoolId, $actorUserId, 'GRADEBOOK_SCORE_CHANGED', 'gradebook_scores', $id,
                $identity + ['score' => $oldScore], $identity + ['score' => $normalized], null, $ipAddress);
        }

        return ['score_id' => $id, 'subject_offering_id' => $offeringId, 'component_id' => $componentId,
            'enrollment_id' => $enrollmentId, 'score' => $normalized, 'changed' => $changed];
    }

    private function decimal(string $value): string
    {
        $value = trim($value, " \t\n\r\v\f");
        if (!preg_match('/\A([0-9]+)(?:\.([0-9]{1,2}))?\z/', $value, $parts)) { throw new DomainException(self::INVALID_SCORE); }
        $integer = ltrim($parts[1], '0');
        $integer = $integer === '' ? '0' : $integer;
        if (strlen($integer) > 5) { throw new DomainException(self::INVALID_SCORE); }

        return $integer . '.' . str_pad($parts[2] ?? '', 2, '0');
    }

    private function exceeds(string $score, string $maximum): bool
    {
        // Both are canonical nonnegative decimals with exactly two fractional digits.
        return strlen($score) > strlen($maximum)
            || (strlen($score) === strlen($maximum) && strcmp($score, $maximum) > 0);
    }

    private function transaction(callable $operation): array
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
            throw new DomainException('ไม่สามารถบันทึกคะแนนได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }
}
