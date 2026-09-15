<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class StudentImportBatchRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(int $schoolId, int $academicYearId, int $actorUserId, string $sourceName, string $sourceSha256): int
    {
        $this->pdo->prepare('INSERT INTO student_import_batches (school_id, academic_year_id, created_by, source_name, source_sha256, expires_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 24 HOUR))')->execute([$schoolId, $academicYearId, $actorUserId, $sourceName, $sourceSha256]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findForSchool(int $schoolId, int $batchId): ?array { return $this->target($schoolId, $batchId, false); }
    public function lockForSchool(int $schoolId, int $batchId): ?array { return $this->target($schoolId, $batchId, true); }

    public function findAppliedByHash(int $schoolId, int $academicYearId, string $sourceSha256): ?array
    {
        $statement = $this->pdo->prepare("SELECT * FROM student_import_batches WHERE school_id = ? AND academic_year_id = ? AND source_sha256 = ? AND status = 'APPLIED' LIMIT 1");
        $statement->execute([$schoolId, $academicYearId, $sourceSha256]);
        return $statement->fetch() ?: null;
    }

    public function updatePreviewCounts(int $schoolId, int $batchId, array $counts): void
    {
        $this->pdo->prepare("UPDATE student_import_batches SET row_count = ?, create_student_count = ?, create_enrollment_count = ?, noop_count = ?, error_count = ?
            WHERE school_id = ? AND id = ? AND status = 'PREVIEW'")->execute([$counts['row_count'], $counts['create_student_count'], $counts['create_enrollment_count'], $counts['noop_count'], $counts['error_count'], $schoolId, $batchId]);
    }

    public function markApplied(int $schoolId, int $batchId): void
    {
        $this->pdo->prepare("UPDATE student_import_batches SET status = 'APPLIED', applied_at = CURRENT_TIMESTAMP WHERE school_id = ? AND id = ? AND status = 'PREVIEW'")->execute([$schoolId, $batchId]);
    }

    public function markCancelled(int $schoolId, int $batchId): void
    {
        $this->pdo->prepare("UPDATE student_import_batches SET status = 'CANCELLED', cancelled_at = CURRENT_TIMESTAMP WHERE school_id = ? AND id = ? AND status = 'PREVIEW'")->execute([$schoolId, $batchId]);
    }

    public function expiredPreviewIdsForSchool(int $schoolId): array
    {
        $statement = $this->pdo->prepare("SELECT id FROM student_import_batches WHERE school_id = ? AND status = 'PREVIEW' AND expires_at <= CURRENT_TIMESTAMP ORDER BY id FOR UPDATE");
        $statement->execute([$schoolId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function markExpired(int $schoolId, int $batchId): void
    {
        $this->pdo->prepare("UPDATE student_import_batches SET status = 'EXPIRED' WHERE school_id = ? AND id = ? AND status = 'PREVIEW' AND expires_at <= CURRENT_TIMESTAMP")->execute([$schoolId, $batchId]);
    }

    private function target(int $schoolId, int $batchId, bool $lock): ?array
    {
        $statement = $this->pdo->prepare('SELECT *, expires_at <= CURRENT_TIMESTAMP AS is_expired FROM student_import_batches WHERE school_id = ? AND id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([$schoolId, $batchId]);
        return $statement->fetch() ?: null;
    }
}
