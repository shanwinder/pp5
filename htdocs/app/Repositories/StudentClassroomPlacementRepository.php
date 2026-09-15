<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class StudentClassroomPlacementRepository
{
    private const COLUMNS = 'p.id, p.school_id, p.academic_year_id, p.grade_level_id, p.enrollment_id,
        p.classroom_id, p.status, p.started_at, p.ended_at';

    public function __construct(private PDO $pdo) {}

    public function listForEnrollment(int $schoolId, int $enrollmentId): array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ', c.code AS classroom_code, c.name_th AS classroom_name
            FROM student_classroom_placements p
            JOIN student_enrollments e ON e.id = p.enrollment_id AND e.school_id = p.school_id
                AND e.academic_year_id = p.academic_year_id AND e.grade_level_id = p.grade_level_id
            JOIN classrooms c ON c.id = p.classroom_id AND c.school_id = p.school_id
                AND c.academic_year_id = p.academic_year_id AND c.grade_level_id = p.grade_level_id
            WHERE p.school_id = :school_id AND p.enrollment_id = :enrollment_id ORDER BY p.started_at ASC, p.id ASC');
        $statement->execute(['school_id' => $schoolId, 'enrollment_id' => $enrollmentId]);

        return $statement->fetchAll();
    }

    public function findActiveForEnrollment(int $schoolId, int $enrollmentId): ?array
    {
        return $this->active($schoolId, $enrollmentId, false);
    }

    public function lockActiveForEnrollment(int $schoolId, int $enrollmentId): ?array
    {
        return $this->active($schoolId, $enrollmentId, true);
    }

    public function create(int $schoolId, int $academicYearId, int $gradeLevelId, int $enrollmentId, int $classroomId): int
    {
        $statement = $this->pdo->prepare("INSERT INTO student_classroom_placements (school_id, academic_year_id, grade_level_id, enrollment_id, classroom_id, status)
            VALUES (:school_id, :year_id, :grade_id, :enrollment_id, :classroom_id, 'ACTIVE')");
        $statement->execute(['school_id' => $schoolId, 'year_id' => $academicYearId, 'grade_id' => $gradeLevelId, 'enrollment_id' => $enrollmentId, 'classroom_id' => $classroomId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function end(int $schoolId, int $placementId): void
    {
        $statement = $this->pdo->prepare("UPDATE student_classroom_placements SET status = 'ENDED', ended_at = CURRENT_TIMESTAMP
            WHERE school_id = :school_id AND id = :placement_id AND status = 'ACTIVE'");
        $statement->execute(['school_id' => $schoolId, 'placement_id' => $placementId]);
    }

    private function active(int $schoolId, int $enrollmentId, bool $lock): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . " FROM student_classroom_placements p
            WHERE p.school_id = :school_id AND p.enrollment_id = :enrollment_id AND p.status = 'ACTIVE'
            ORDER BY p.id ASC LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['school_id' => $schoolId, 'enrollment_id' => $enrollmentId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
