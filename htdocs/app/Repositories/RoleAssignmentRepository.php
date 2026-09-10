<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use DomainException;
use Throwable;

final class RoleAssignmentRepository
{
    public function __construct(private PDO $pdo) {}

    public function activeSchoolRoleCodes(int $userId, int $schoolId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT DISTINCT r.code FROM user_role_assignments ura
             INNER JOIN roles r ON r.id = ura.role_id
             WHERE ura.user_id = ? AND ura.school_id = ? AND ura.academic_year_id IS NULL
               AND ura.status = 'ACTIVE' AND r.status = 'ACTIVE'
               AND r.scope_type = 'SCHOOL' AND r.code <> 'SYSTEM_ADMIN'
             ORDER BY r.code"
        );
        $statement->execute([$userId, $schoolId]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /** ACTIVE school-wide assignments, independent of the role definition's status or scope. */
    public function activeSchoolWideRoleIds(int $userId, int $schoolId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT DISTINCT role_id FROM user_role_assignments
             WHERE user_id = ? AND school_id = ? AND academic_year_id IS NULL AND status = 'ACTIVE'
             ORDER BY role_id"
        );
        $statement->execute([$userId, $schoolId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function activateSchoolRole(int $userId, int $schoolId, int $roleId, int $assignedBy): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            // Serialize activation for this membership; the schema permits historical duplicates.
            $membership = $this->pdo->prepare('SELECT id FROM school_memberships WHERE user_id = ? AND school_id = ? FOR UPDATE');
            $membership->execute([$userId, $schoolId]);
            if ($membership->fetchColumn() === false) {
                throw new DomainException('ไม่พบสมาชิกของโรงเรียนที่ระบุ');
            }
            $statement = $this->pdo->prepare(
                "SELECT id FROM user_role_assignments
                 WHERE user_id = ? AND school_id = ? AND role_id = ? AND academic_year_id IS NULL
                 ORDER BY (status = 'ACTIVE') DESC, id LIMIT 1 FOR UPDATE"
            );
            $statement->execute([$userId, $schoolId, $roleId]);
            $assignmentId = $statement->fetchColumn();
            if ($assignmentId === false) {
                $this->assignSchoolRole($userId, $schoolId, $roleId, $assignedBy);
            } else {
                $statement = $this->pdo->prepare(
                    "UPDATE user_role_assignments SET status = 'INACTIVE'
                     WHERE user_id = ? AND school_id = ? AND role_id = ? AND academic_year_id IS NULL
                       AND id <> ? AND status = 'ACTIVE'"
                );
                $statement->execute([$userId, $schoolId, $roleId, $assignmentId]);
                $statement = $this->pdo->prepare(
                    "UPDATE user_role_assignments SET status = 'ACTIVE', assigned_by = ?
                     WHERE user_id = ? AND school_id = ? AND role_id = ? AND academic_year_id IS NULL AND id = ?"
                );
                $statement->execute([$assignedBy, $userId, $schoolId, $roleId, $assignmentId]);
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deactivateSchoolRole(int $userId, int $schoolId, int $roleId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE user_role_assignments SET status = 'INACTIVE'
             WHERE user_id = ? AND school_id = ? AND role_id = ? AND academic_year_id IS NULL"
        );
        $statement->execute([$userId, $schoolId, $roleId]);
    }

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
