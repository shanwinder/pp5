<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SchoolMembershipRepository
{
    public function __construct(private PDO $pdo) {}

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
