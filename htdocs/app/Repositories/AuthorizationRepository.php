<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuthorizationRepository
{
    public function __construct(private PDO $pdo) {}

    public function hasActiveSystemAdmin(int $userId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1
             FROM user_role_assignments ura
             INNER JOIN roles r ON r.id = ura.role_id
             WHERE ura.user_id = :user_id
               AND ura.school_id IS NULL
               AND ura.academic_year_id IS NULL
               AND ura.status = 'ACTIVE'
               AND r.status = 'ACTIVE'
               AND r.scope_type = 'SYSTEM'
               AND r.code = 'SYSTEM_ADMIN'
             LIMIT 1"
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchColumn() !== false;
    }

    public function hasActiveSchoolRole(int $userId, int $schoolId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1
             FROM user_role_assignments ura
             INNER JOIN roles r ON r.id = ura.role_id
             INNER JOIN school_memberships sm ON sm.user_id = ura.user_id AND sm.school_id = ura.school_id
             INNER JOIN schools s ON s.id = sm.school_id
             WHERE ura.user_id = :user_id
               AND ura.school_id = :school_id
               AND ura.academic_year_id IS NULL
               AND ura.status = 'ACTIVE'
               AND r.status = 'ACTIVE'
               AND r.scope_type = 'SCHOOL'
               AND sm.status = 'ACTIVE'
               AND s.status = 'ACTIVE'
             LIMIT 1"
        );
        $statement->execute(['user_id' => $userId, 'school_id' => $schoolId]);

        return $statement->fetchColumn() !== false;
    }

    public function hasSystemPermission(int $userId, string $permissionCode): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1
             FROM user_role_assignments ura
             INNER JOIN roles r ON r.id = ura.role_id
             INNER JOIN role_permissions rp ON rp.role_id = r.id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE ura.user_id = :user_id
               AND ura.school_id IS NULL
               AND ura.academic_year_id IS NULL
               AND ura.status = 'ACTIVE'
               AND r.status = 'ACTIVE'
               AND r.scope_type = 'SYSTEM'
               AND p.code = :permission_code
             LIMIT 1"
        );
        $statement->execute(['user_id' => $userId, 'permission_code' => $permissionCode]);

        return $statement->fetchColumn() !== false;
    }

    public function hasSchoolPermission(int $userId, int $schoolId, string $permissionCode): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1
             FROM user_role_assignments ura
             INNER JOIN roles r ON r.id = ura.role_id
             INNER JOIN role_permissions rp ON rp.role_id = r.id
             INNER JOIN permissions p ON p.id = rp.permission_id
             INNER JOIN school_memberships sm ON sm.user_id = ura.user_id AND sm.school_id = ura.school_id
             INNER JOIN schools s ON s.id = sm.school_id
             WHERE ura.user_id = :user_id
               AND ura.school_id = :school_id
               AND ura.academic_year_id IS NULL
               AND ura.status = 'ACTIVE'
               AND r.status = 'ACTIVE'
               AND r.scope_type = 'SCHOOL'
               AND sm.status = 'ACTIVE'
               AND s.status = 'ACTIVE'
               AND p.code = :permission_code
             LIMIT 1"
        );
        $statement->execute([
            'user_id' => $userId,
            'school_id' => $schoolId,
            'permission_code' => $permissionCode,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
