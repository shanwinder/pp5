<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findActiveByUsername(string $username): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, username, password_hash, display_name
             FROM users
             WHERE username = :username
               AND status = 'ACTIVE'
             LIMIT 1"
        );
        $statement->execute(['username' => $username]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function recordLogin(int $userId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute(['id' => $userId]);
    }
}
