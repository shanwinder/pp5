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
            $component = $this->components->lockForOffering($schoolId, $offeringId, $componentId);
            if ($component === null || (int) $component['academic_year_id'] !== $yearId || $component['status'] !== 'ACTIVE') {
                throw new DomainException(self::DENIED);
            }
            $enrollment = $this->enrollments->lockForSchool($schoolId, $enrollmentId);
            if ($enrollment === null || (int) $enrollment['academic_year_id'] !== $yearId || $enrollment['status'] !== 'ACTIVE') {
                throw new DomainException(self::DENIED);
            }
            $placement = $this->placements->lockActiveForEnrollment($schoolId, $enrollmentId);
            if ($placement === null || (int) $placement['academic_year_id'] !== $yearId || (int) $placement['classroom_id'] !== (int) $offering['classroom_id']) {
                throw new DomainException(self::DENIED);
            }
            if (!$this->authorization->hasSubjectOfferingPermissionForUpdate($actorUserId, 'SCHOOL', $schoolId, $offeringId, 'GRADEBOOK_SCORE_ENTER')) {
                throw new DomainException(self::DENIED);
            }
            // Parent locks serialize creation too, including when this cell does not yet exist.
            $cell = $this->scores->lockCell($schoolId, $yearId, $offeringId, $enrollmentId, $componentId);
            $normalized = $score === null ? null : $this->decimal($score);
            if ($normalized !== null && $this->exceeds($normalized, $component['max_score'])) { throw new DomainException(self::INVALID_SCORE); }
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
        });
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
