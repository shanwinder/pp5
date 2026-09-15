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
