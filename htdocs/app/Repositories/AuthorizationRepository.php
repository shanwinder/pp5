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
               AND rp.resource_scope_type IS NULL
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

    public function hasSubjectOfferingPermission(int $userId, int $schoolId, int $subjectOfferingId, string $permissionCode): bool
    {
        // Bind the scope to the very assignment whose role grants this permission.
        // Offering/year status is a mutation gate, not a historical-read permission gate.
        $statement = $this->pdo->prepare(
            "SELECT 1
             FROM user_role_assignments ura
             INNER JOIN roles r ON r.id = ura.role_id
             INNER JOIN role_permissions rp ON rp.role_id = r.id
             INNER JOIN permissions p ON p.id = rp.permission_id
             INNER JOIN school_memberships sm ON sm.user_id = ura.user_id AND sm.school_id = ura.school_id
             INNER JOIN schools s ON s.id = sm.school_id
             INNER JOIN subject_offerings o ON o.school_id = ura.school_id AND o.id = :offering_id
             WHERE ura.user_id = :user_id
               AND ura.school_id = :school_id
               AND ura.academic_year_id IS NULL
               AND ura.status = 'ACTIVE'
               AND r.status = 'ACTIVE'
               AND r.scope_type = 'SCHOOL'
               AND sm.status = 'ACTIVE'
               AND s.status = 'ACTIVE'
               AND p.code = :permission_code
               AND (
                   rp.resource_scope_type IS NULL
                   OR (rp.resource_scope_type = 'SUBJECT_OFFERING' AND EXISTS (
                       SELECT 1 FROM permission_scopes ps
                       WHERE ps.user_role_assignment_id = ura.id
                         AND ps.school_id = ura.school_id
                         AND ps.subject_offering_id = o.id
                         AND ps.academic_year_id = o.academic_year_id
                         AND ps.status = 'ACTIVE'
                   ))
               )
             LIMIT 1"
        );
        $statement->execute([
            'user_id' => $userId, 'school_id' => $schoolId,
            'offering_id' => $subjectOfferingId, 'permission_code' => $permissionCode,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /** The caller must hold its mutation transaction until the protected write commits. */
    public function hasSubjectOfferingPermissionForUpdate(int $userId, int $schoolId, int $subjectOfferingId, string $permissionCode): bool
    {
        if (!$this->pdo->inTransaction()) { return false; }
        // Join the concrete scope into the locking query: an EXISTS subquery would
        // not inherit the outer FOR UPDATE lock on the granting scope row.
        $statement = $this->pdo->prepare(
            "SELECT ura.id
             FROM user_role_assignments ura
             INNER JOIN roles r ON r.id = ura.role_id
             INNER JOIN role_permissions rp ON rp.role_id = r.id
             INNER JOIN permissions p ON p.id = rp.permission_id
             INNER JOIN school_memberships sm ON sm.user_id = ura.user_id AND sm.school_id = ura.school_id
             INNER JOIN schools s ON s.id = sm.school_id
             INNER JOIN subject_offerings o ON o.school_id = ura.school_id AND o.id = :offering_id
             LEFT JOIN permission_scopes ps ON ps.user_role_assignment_id = ura.id
                 AND ps.school_id = ura.school_id
                 AND ps.subject_offering_id = o.id
                 AND ps.academic_year_id = o.academic_year_id
                 AND ps.status = 'ACTIVE'
             WHERE ura.user_id = :user_id
               AND ura.school_id = :school_id
               AND ura.academic_year_id IS NULL
               AND ura.status = 'ACTIVE'
               AND r.status = 'ACTIVE'
               AND r.scope_type = 'SCHOOL'
               AND sm.status = 'ACTIVE'
               AND s.status = 'ACTIVE'
               AND p.code = :permission_code
               AND (rp.resource_scope_type IS NULL
                    OR (rp.resource_scope_type = 'SUBJECT_OFFERING' AND ps.id IS NOT NULL))
             LIMIT 1 FOR UPDATE"
        );
        $statement->execute([
            'user_id' => $userId, 'school_id' => $schoolId,
            'offering_id' => $subjectOfferingId, 'permission_code' => $permissionCode,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
