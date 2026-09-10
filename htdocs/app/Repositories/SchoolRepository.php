<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;
use DomainException;

final class SchoolRepository
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        $statement = $this->pdo->prepare('SELECT id, school_code, name_th, status FROM schools ORDER BY id');
        $statement->execute();

        return $statement->fetchAll();
    }

    public function findById(int $schoolId): ?array
    {
        // Status mutations call this inside a transaction to lock the audited row.
        $statement = $this->pdo->prepare('SELECT id, school_code, name_th, status FROM schools WHERE id = ? LIMIT 1 FOR UPDATE');
        $statement->execute([$schoolId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function findByCode(string $schoolCode): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, school_code, name_th, status FROM schools WHERE school_code = ? LIMIT 1');
        $statement->execute([$schoolCode]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $schoolCode, string $nameTh): int
    {
        try {
            $statement = $this->pdo->prepare("INSERT INTO schools (school_code, name_th, status) VALUES (?, ?, 'ACTIVE')");
            $statement->execute([$schoolCode, $nameTh]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('รหัสโรงเรียนนี้ถูกใช้งานแล้ว กรุณาใช้รหัสอื่น');
            }
            throw $exception;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $schoolId, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?');
        $statement->execute([$status, $schoolId]);
    }

    public function findActiveById(int $schoolId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, school_code, name_th
             FROM schools
             WHERE id = :id AND status = 'ACTIVE'
             LIMIT 1"
        );
        $statement->execute(['id' => $schoolId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function lockActiveById(int $schoolId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, school_code, name_th, status FROM schools
             WHERE id = ? AND status = 'ACTIVE' LIMIT 1 FOR UPDATE"
        );
        $statement->execute([$schoolId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
