<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GradebookScoreRepository
{
    public function __construct(private PDO $pdo) {}

    public function lockCell(int $schoolId, int $academicYearId, int $subjectOfferingId, int $enrollmentId, int $componentId): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, school_id, academic_year_id, subject_offering_id, enrollment_id, component_id,
                score, updated_by, created_at, updated_at
            FROM gradebook_scores
            WHERE school_id = ? AND academic_year_id = ? AND subject_offering_id = ? AND enrollment_id = ? AND component_id = ?
            LIMIT 1 FOR UPDATE');
        $statement->execute([$schoolId, $academicYearId, $subjectOfferingId, $enrollmentId, $componentId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function create(int $schoolId, int $academicYearId, int $subjectOfferingId, int $enrollmentId, int $componentId, string $score, int $updatedBy): int
    {
        $statement = $this->pdo->prepare('INSERT INTO gradebook_scores
            (school_id, academic_year_id, subject_offering_id, enrollment_id, component_id, score, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$schoolId, $academicYearId, $subjectOfferingId, $enrollmentId, $componentId, $score, $updatedBy]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateScore(int $schoolId, int $academicYearId, int $subjectOfferingId, int $enrollmentId, int $componentId, ?string $score, int $updatedBy): void
    {
        $statement = $this->pdo->prepare('UPDATE gradebook_scores SET score = ?, updated_by = ?
            WHERE school_id = ? AND academic_year_id = ? AND subject_offering_id = ? AND enrollment_id = ? AND component_id = ?');
        $statement->execute([$score, $updatedBy, $schoolId, $academicYearId, $subjectOfferingId, $enrollmentId, $componentId]);
    }
}
