<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class StudentEnrollmentRepository
{
    public function __construct(private PDO $pdo) {}

    public function hasActiveInOpenYear(int $schoolId, int $studentId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM student_enrollments e
             JOIN academic_years y ON y.id = e.academic_year_id AND y.school_id = e.school_id
             WHERE e.school_id = :school_id AND e.student_id = :student_id
               AND e.status = 'ACTIVE' AND y.status IN ('DRAFT', 'ACTIVE') LIMIT 1"
        );
        $statement->execute(['school_id' => $schoolId, 'student_id' => $studentId]);

        return $statement->fetchColumn() !== false;
    }
}
