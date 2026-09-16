<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class TeachingAssignmentRepository
{
    private const COLUMNS = 'id, school_id, academic_year_id, user_role_assignment_id, subject_offering_id, status, assigned_by, created_at, updated_at';
    private const TEACHERS = "SELECT ura.id AS user_role_assignment_id, ura.user_id, u.display_name
        FROM user_role_assignments ura
        INNER JOIN roles r ON r.id = ura.role_id
        INNER JOIN school_memberships sm ON sm.user_id = ura.user_id AND sm.school_id = ura.school_id
        INNER JOIN users u ON u.id = ura.user_id
        WHERE ura.school_id = ?
          AND ura.academic_year_id IS NULL
          AND ura.status = 'ACTIVE'
          AND r.scope_type = 'SCHOOL'
          AND r.status = 'ACTIVE'
          AND r.code = 'SUBJECT_TEACHER'
          AND sm.status = 'ACTIVE'";

    public function __construct(private PDO $pdo) {}

    public function listSubjectTeachers(int $schoolId): array
    {
        $statement = $this->pdo->prepare(self::TEACHERS . ' ORDER BY u.display_name, ura.id');
        $statement->execute([$schoolId]);

        return array_map($this->castIds(...), $statement->fetchAll());
    }

    public function lockEligibleSubjectTeacher(int $schoolId, int $userRoleAssignmentId): ?array
    {
        // A locking join revalidates and locks the role and membership as well as the assignment.
        $statement = $this->pdo->prepare(self::TEACHERS . ' AND ura.id = ? LIMIT 1 FOR UPDATE');
        $statement->execute([$schoolId, $userRoleAssignmentId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->castIds($row);
    }

    public function listDetailedForSchool(int $schoolId, ?int $academicYearId = null): array
    {
        // Retained history is independent of current teacher, offering, and year eligibility.
        $sql = 'SELECT ps.id, ps.school_id, ps.academic_year_id, ps.user_role_assignment_id,
                ps.subject_offering_id, ps.status, ps.created_at, ps.updated_at,
                y.year_be, y.status AS academic_year_status, o.term_no, o.status AS offering_status,
                c.code AS classroom_code, c.name_th AS classroom_name,
                s.code AS subject_code, s.name_th AS subject_name,
                ura.user_id, u.display_name AS teacher_display_name
            FROM permission_scopes ps
            JOIN academic_years y ON y.id = ps.academic_year_id AND y.school_id = ps.school_id
            JOIN subject_offerings o ON o.id = ps.subject_offering_id AND o.school_id = ps.school_id
                AND o.academic_year_id = ps.academic_year_id
            JOIN classrooms c ON c.id = o.classroom_id AND c.school_id = o.school_id
                AND c.academic_year_id = o.academic_year_id
            JOIN subjects s ON s.id = o.subject_id AND s.school_id = o.school_id
            JOIN user_role_assignments ura ON ura.id = ps.user_role_assignment_id AND ura.school_id = ps.school_id
            JOIN users u ON u.id = ura.user_id
            WHERE ps.school_id = ?';
        $parameters = [$schoolId];
        if ($academicYearId !== null) {
            $sql .= ' AND ps.academic_year_id = ?';
            $parameters[] = $academicYearId;
        }
        $sql .= ' ORDER BY y.year_be DESC, c.code ASC, s.code ASC, o.term_no ASC, u.display_name ASC, ps.id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return array_map($this->castIds(...), $statement->fetchAll());
    }

    public function listForSchool(int $schoolId): array
    {
        // History includes inactive scopes and closed years.
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM permission_scopes WHERE school_id = ? ORDER BY id');
        $statement->execute([$schoolId]);

        return array_map($this->castIds(...), $statement->fetchAll());
    }

    public function findForSchool(int $schoolId, int $teachingAssignmentId): ?array
    {
        return $this->find($schoolId, $teachingAssignmentId, false);
    }

    public function lockForSchool(int $schoolId, int $teachingAssignmentId): ?array
    {
        return $this->find($schoolId, $teachingAssignmentId, true);
    }

    public function lockPair(int $schoolId, int $userRoleAssignmentId, int $subjectOfferingId): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM permission_scopes
            WHERE school_id = ? AND user_role_assignment_id = ? AND subject_offering_id = ? LIMIT 1 FOR UPDATE');
        $statement->execute([$schoolId, $userRoleAssignmentId, $subjectOfferingId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->castIds($row);
    }

    public function create(int $schoolId, int $academicYearId, int $userRoleAssignmentId, int $subjectOfferingId, int $assignedBy): int
    {
        $statement = $this->pdo->prepare("INSERT INTO permission_scopes
            (school_id, academic_year_id, user_role_assignment_id, subject_offering_id, status, assigned_by)
            VALUES (?, ?, ?, ?, 'ACTIVE', ?)");
        $statement->execute([$schoolId, $academicYearId, $userRoleAssignmentId, $subjectOfferingId, $assignedBy]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $schoolId, int $teachingAssignmentId, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE permission_scopes SET status = ? WHERE school_id = ? AND id = ?');
        $statement->execute([$status, $schoolId, $teachingAssignmentId]);
    }

    private function find(int $schoolId, int $teachingAssignmentId, bool $lock): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM permission_scopes
            WHERE school_id = ? AND id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([$schoolId, $teachingAssignmentId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->castIds($row);
    }

    private function castIds(array $row): array
    {
        foreach (['id', 'school_id', 'academic_year_id', 'user_role_assignment_id', 'subject_offering_id', 'assigned_by', 'user_id'] as $column) {
            if (array_key_exists($column, $row)) { $row[$column] = (int) $row[$column]; }
        }

        return $row;
    }
}
