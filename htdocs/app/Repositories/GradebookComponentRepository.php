<?php
declare(strict_types=1);

namespace App\Repositories;

use DomainException;
use PDO;
use PDOException;

final class GradebookComponentRepository
{
    private const COLUMNS = 'id, school_id, academic_year_id, subject_offering_id, code, name_th, max_score, sort_order, status, created_at, updated_at';

    public function __construct(private PDO $pdo) {}

    public function listForOffering(int $schoolId, int $subjectOfferingId): array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM gradebook_components
            WHERE school_id = ? AND subject_offering_id = ? ORDER BY sort_order ASC, id ASC');
        $statement->execute([$schoolId, $subjectOfferingId]);

        return $statement->fetchAll();
    }

    /** One tenant-scoped projection for already-authorized offering IDs. MySQL DECIMAL SUM stays exact. */
    public function summariesForOfferings(int $schoolId, array $offeringIds): array
    {
        if ($offeringIds === []) { return []; }
        $ids = array_values(array_unique(array_map('intval', $offeringIds)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare('SELECT subject_offering_id,
            SUM(CASE WHEN status = \'ACTIVE\' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN status = \'INACTIVE\' THEN 1 ELSE 0 END) AS inactive_count,
            COALESCE(SUM(CASE WHEN status = \'ACTIVE\' THEN max_score ELSE 0 END), 0) AS active_max_total
            FROM gradebook_components WHERE school_id = ? AND subject_offering_id IN (' . $placeholders . ')
            GROUP BY subject_offering_id');
        $statement->execute([$schoolId, ...$ids]);
        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $result[(int) $row['subject_offering_id']] = [
                'active_count' => (int) $row['active_count'],
                'inactive_count' => (int) $row['inactive_count'],
                'active_max_total' => (string) $row['active_max_total'],
            ];
        }
        return $result;
    }

    /** Non-locking display hint. The write service still checks history under lock. */
    public function historyIdsForOffering(int $schoolId, int $offeringId): array
    {
        $statement = $this->pdo->prepare('SELECT DISTINCT component_id FROM gradebook_scores
            WHERE school_id = ? AND subject_offering_id = ?');
        $statement->execute([$schoolId, $offeringId]);
        return array_fill_keys(array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    public function findForOffering(int $schoolId, int $subjectOfferingId, int $componentId): ?array
    {
        return $this->find($schoolId, $subjectOfferingId, $componentId, false);
    }

    public function lockForOffering(int $schoolId, int $subjectOfferingId, int $componentId): ?array
    {
        return $this->find($schoolId, $subjectOfferingId, $componentId, true);
    }

    public function create(int $schoolId, int $academicYearId, int $subjectOfferingId, string $code, string $nameTh, string $maxScore, int $sortOrder): int
    {
        $this->write('INSERT INTO gradebook_components
            (school_id, academic_year_id, subject_offering_id, code, name_th, max_score, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)', [$schoolId, $academicYearId, $subjectOfferingId, $code, $nameTh, $maxScore, $sortOrder]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $schoolId, int $subjectOfferingId, int $componentId, string $code, string $nameTh, string $maxScore, int $sortOrder): void
    {
        $this->write('UPDATE gradebook_components SET code = ?, name_th = ?, max_score = ?, sort_order = ?
            WHERE school_id = ? AND subject_offering_id = ? AND id = ?',
            [$code, $nameTh, $maxScore, $sortOrder, $schoolId, $subjectOfferingId, $componentId]);
    }

    public function updateStatus(int $schoolId, int $subjectOfferingId, int $componentId, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE gradebook_components SET status = ?
            WHERE school_id = ? AND subject_offering_id = ? AND id = ?');
        $statement->execute([$status, $schoolId, $subjectOfferingId, $componentId]);
    }

    public function hasScoreHistory(int $schoolId, int $subjectOfferingId, int $componentId): bool
    {
        // Current locking read after the component lock: even a retained NULL score is history.
        $statement = $this->pdo->prepare('SELECT id FROM gradebook_scores
            WHERE school_id = ? AND subject_offering_id = ? AND component_id = ? LIMIT 1 FOR UPDATE');
        $statement->execute([$schoolId, $subjectOfferingId, $componentId]);

        return $statement->fetchColumn() !== false;
    }

    private function find(int $schoolId, int $subjectOfferingId, int $componentId, bool $lock): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM gradebook_components
            WHERE school_id = ? AND subject_offering_id = ? AND id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([$schoolId, $subjectOfferingId, $componentId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    private function write(string $sql, array $parameters): void
    {
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);
        } catch (PDOException $exception) {
            // The DB unique code key, including its Unicode collation, is authoritative.
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('รหัสองค์ประกอบคะแนนนี้มีอยู่ในรายวิชาที่เปิดสอนแล้ว');
            }
            throw $exception;
        }
    }
}
