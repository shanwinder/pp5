<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RoleAssignmentRepository
{
    public function __construct(private PDO $pdo) {}

    public function assignSystemRole(int $userId, int $roleId, ?int $assignedBy = null): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO user_role_assignments (user_id, school_id, role_id, assigned_by, academic_year_id, status)
             VALUES (?, NULL, ?, ?, NULL, 'ACTIVE')"
        );
        $statement->execute([$userId, $roleId, $assignedBy]);

        return (int) $this->pdo->lastInsertId();
    }

    public function assignSchoolRole(int $userId, int $schoolId, int $roleId, int $assignedBy): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO user_role_assignments (user_id, school_id, role_id, assigned_by, academic_year_id, status)
             VALUES (?, ?, ?, ?, NULL, 'ACTIVE')"
        );
        $statement->execute([$userId, $schoolId, $roleId, $assignedBy]);

        return (int) $this->pdo->lastInsertId();
    }
}
