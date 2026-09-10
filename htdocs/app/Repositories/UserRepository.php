<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;
use DomainException;

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function updateProfile(int $schoolId, int $userId, string $displayName, ?string $email): void
    {
        try {
            $statement = $this->pdo->prepare(
                "UPDATE users u
                 INNER JOIN school_memberships sm ON sm.user_id = u.id
                 SET u.display_name = ?, u.email = ?
                 WHERE sm.school_id = ? AND sm.user_id = ? AND sm.status IN ('ACTIVE', 'SUSPENDED')"
            );
            $statement->execute([$displayName, $email, $schoolId, $userId]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('อีเมลนี้ถูกใช้งานแล้ว กรุณาใช้อีเมลอื่น');
            }
            throw $exception;
        }
    }

    public function updatePasswordHash(int $schoolId, int $userId, #[\SensitiveParameter] string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE users u
             INNER JOIN school_memberships sm ON sm.user_id = u.id
             SET u.password_hash = ?
             WHERE sm.school_id = ? AND sm.user_id = ? AND sm.status IN ('ACTIVE', 'SUSPENDED')"
        );
        $statement->execute([$passwordHash, $schoolId, $userId]);
    }

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
