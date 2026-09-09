<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SchoolMembershipRepository
{
    public function __construct(private PDO $pdo) {}

    public function findForSchoolUser(int $schoolId, int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT sm.id, sm.user_id, sm.school_id, sm.status, u.username, u.display_name, u.email, u.status AS user_status
             FROM school_memberships sm
             INNER JOIN users u ON u.id = sm.user_id
             WHERE sm.school_id = ? AND sm.user_id = ?
             LIMIT 1 FOR UPDATE'
        );
        $statement->execute([$schoolId, $userId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function listForSchool(int $schoolId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT sm.id, sm.user_id, sm.school_id, sm.status, u.username, u.display_name, u.email, u.status AS user_status
             FROM school_memberships sm
             INNER JOIN users u ON u.id = sm.user_id
             WHERE sm.school_id = ?
             ORDER BY sm.id'
        );
        $statement->execute([$schoolId]);

        return $statement->fetchAll();
    }

    public function updateStatus(int $membershipId, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE school_memberships SET status = ? WHERE id = ?');
        $statement->execute([$status, $membershipId]);
    }

    public function create(int $userId, int $schoolId, ?int $createdBy): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO school_memberships (user_id, school_id, created_by, status) VALUES (?, ?, ?, 'ACTIVE')"
        );
        $statement->execute([$userId, $schoolId, $createdBy]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findActiveRowsForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, user_id, school_id
             FROM school_memberships
             WHERE user_id = ? AND status = 'ACTIVE'
             ORDER BY id"
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function findActiveForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT sm.id, sm.user_id, sm.school_id
             FROM school_memberships sm
             INNER JOIN schools s ON s.id = sm.school_id
             WHERE sm.user_id = :user_id
               AND sm.status = 'ACTIVE'
               AND s.status = 'ACTIVE'
             ORDER BY sm.id"
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function isActiveMembership(
        int $membershipId,
        int $userId,
        int $schoolId
    ): bool {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM school_memberships sm
             INNER JOIN schools s ON s.id = sm.school_id
             WHERE sm.id = :membership_id
               AND sm.user_id = :user_id
               AND sm.school_id = :school_id
               AND sm.status = 'ACTIVE'
               AND s.status = 'ACTIVE'"
        );
        $statement->execute([
            'membership_id' => $membershipId,
            'user_id' => $userId,
            'school_id' => $schoolId,
        ]);

        return (int) $statement->fetchColumn() === 1;
    }
}
