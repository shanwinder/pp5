<?php
declare(strict_types=1);

namespace App\Repositories;

use DomainException;
use PDO;
use PDOException;

final class StudentEnrollmentRepository
{
    private const COLUMNS = 'e.id, e.school_id, e.academic_year_id, e.student_id, e.grade_level_id,
        e.entry_date, e.exit_date, e.status, e.created_at, e.updated_at';
    private const DETAILS = ', s.student_code, s.prefix_th, s.first_name_th, s.last_name_th,
        y.year_be, y.status AS academic_year_status, g.name_th AS grade_level_name,
        p.classroom_id, c.code AS classroom_code, c.name_th AS classroom_name';
    private const JOINS = " FROM student_enrollments e
        JOIN students s ON s.id = e.student_id AND s.school_id = e.school_id
        JOIN academic_years y ON y.id = e.academic_year_id AND y.school_id = e.school_id
        JOIN grade_levels g ON g.id = e.grade_level_id
        LEFT JOIN student_classroom_placements p ON p.enrollment_id = e.id AND p.school_id = e.school_id
            AND p.academic_year_id = e.academic_year_id AND p.grade_level_id = e.grade_level_id AND p.status = 'ACTIVE'
        LEFT JOIN classrooms c ON c.id = p.classroom_id AND c.school_id = p.school_id
            AND c.academic_year_id = p.academic_year_id AND c.grade_level_id = p.grade_level_id";

    public function __construct(private PDO $pdo) {}

    public function listForSchoolYear(
        int $schoolId,
        int $academicYearId,
        ?int $gradeLevelId = null,
        ?int $classroomId = null,
        ?string $status = null,
        ?string $search = null
    ): array {
        $sql = 'SELECT ' . self::COLUMNS . self::DETAILS . self::JOINS . ' WHERE e.school_id = :school_id AND e.academic_year_id = :year_id';
        $parameters = ['school_id' => $schoolId, 'year_id' => $academicYearId];
        foreach (['e.grade_level_id' => ['grade_id', $gradeLevelId], 'p.classroom_id' => ['classroom_id', $classroomId], 'e.status' => ['status', $status]] as $column => [$key, $value]) {
            if ($value !== null) {
                $sql .= ' AND ' . $column . ' = :' . $key;
                $parameters[$key] = $value;
            }
        }
        if ($search !== null && $search !== '') {
            $pattern = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $sql .= " AND (s.student_code LIKE :code ESCAPE '!' OR s.first_name_th LIKE :first_name ESCAPE '!'
                OR s.last_name_th LIKE :last_name ESCAPE '!'
                OR CONCAT_WS(' ', s.prefix_th, s.first_name_th, s.last_name_th) LIKE :full_name ESCAPE '!')";
            $parameters += ['code' => $pattern, 'first_name' => $pattern, 'last_name' => $pattern, 'full_name' => $pattern];
        }
        $statement = $this->pdo->prepare($sql . ' ORDER BY s.student_code ASC, e.id ASC');
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function listForStudent(int $schoolId, int $studentId): array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . self::DETAILS . self::JOINS .
            ' WHERE e.school_id = :school_id AND e.student_id = :student_id ORDER BY y.year_be DESC, e.id DESC');
        $statement->execute(['school_id' => $schoolId, 'student_id' => $studentId]);

        return $statement->fetchAll();
    }

    public function findForSchool(int $schoolId, int $enrollmentId): ?array
    {
        return $this->fetch('SELECT ' . self::COLUMNS . ' FROM student_enrollments e WHERE e.school_id = :school_id AND e.id = :enrollment_id LIMIT 1',
            ['school_id' => $schoolId, 'enrollment_id' => $enrollmentId]);
    }

    public function lockForSchool(int $schoolId, int $enrollmentId): ?array
    {
        return $this->fetch('SELECT ' . self::COLUMNS . ' FROM student_enrollments e WHERE e.school_id = :school_id AND e.id = :enrollment_id LIMIT 1 FOR UPDATE',
            ['school_id' => $schoolId, 'enrollment_id' => $enrollmentId]);
    }

    public function findForStudentYear(int $schoolId, int $academicYearId, int $studentId): ?array
    {
        return $this->fetch('SELECT ' . self::COLUMNS . ' FROM student_enrollments e
            WHERE e.school_id = :school_id AND e.academic_year_id = :year_id AND e.student_id = :student_id LIMIT 1',
            ['school_id' => $schoolId, 'year_id' => $academicYearId, 'student_id' => $studentId]);
    }

    public function create(int $schoolId, int $academicYearId, int $studentId, int $gradeLevelId, ?string $entryDate): int
    {
        try {
            $statement = $this->pdo->prepare("INSERT INTO student_enrollments (school_id, academic_year_id, student_id, grade_level_id, entry_date, status)
                VALUES (:school_id, :year_id, :student_id, :grade_id, :entry_date, 'ACTIVE')");
            $statement->execute(['school_id' => $schoolId, 'year_id' => $academicYearId, 'student_id' => $studentId, 'grade_id' => $gradeLevelId, 'entry_date' => $entryDate]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('นักเรียนมีการลงทะเบียนในปีการศึกษานี้แล้ว');
            }
            throw $exception;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $schoolId, int $enrollmentId, string $status, ?string $exitDate): void
    {
        $statement = $this->pdo->prepare('UPDATE student_enrollments SET status = :status, exit_date = :exit_date WHERE school_id = :school_id AND id = :enrollment_id');
        $statement->execute(['status' => $status, 'exit_date' => $exitDate, 'school_id' => $schoolId, 'enrollment_id' => $enrollmentId]);
    }

    private function fetch(string $sql, array $parameters): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

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
