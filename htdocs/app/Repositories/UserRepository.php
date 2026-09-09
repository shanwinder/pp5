<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;
use DomainException;

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findByUsername(string $username): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, username, email, display_name, status FROM users WHERE username = ? LIMIT 1');
        $statement->execute([$username]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, username, email, display_name, status FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function create(
        string $username,
        ?string $email,
        #[\SensitiveParameter] string $passwordHash,
        string $displayName
    ): int
    {
        try {
            $statement = $this->pdo->prepare(
                "INSERT INTO users (username, email, password_hash, display_name, status) VALUES (?, ?, ?, ?, 'ACTIVE')"
            );
            $statement->execute([$username, $email, $passwordHash, $displayName]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้งานแล้ว กรุณาใช้ข้อมูลอื่น');
            }
            throw $exception;
        }

        return (int) $this->pdo->lastInsertId();
    }

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

    public function isActiveById(int $userId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM users WHERE id = :id AND status = 'ACTIVE' LIMIT 1"
        );
        $statement->execute(['id' => $userId]);

        return $statement->fetchColumn() !== false;
    }
}
