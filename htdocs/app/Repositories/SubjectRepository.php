<?php
declare(strict_types=1);

namespace App\Repositories;

use DomainException;
use PDO;
use PDOException;

final class SubjectRepository
{
    private const COLUMNS = 'id, school_id, code, name_th, status, created_at, updated_at';

    public function __construct(private PDO $pdo) {}

    public function listForSchool(int $schoolId): array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM subjects WHERE school_id = ? ORDER BY code ASC, id ASC');
        $statement->execute([$schoolId]);

        return $statement->fetchAll();
    }

    public function findForSchool(int $schoolId, int $subjectId): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM subjects WHERE school_id = ? AND id = ? LIMIT 1');
        $statement->execute([$schoolId, $subjectId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function lockForSchool(int $schoolId, int $subjectId): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM subjects WHERE school_id = ? AND id = ? LIMIT 1 FOR UPDATE');
        $statement->execute([$schoolId, $subjectId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function create(int $schoolId, string $code, string $nameTh): int
    {
        try {
            $statement = $this->pdo->prepare('INSERT INTO subjects (school_id, code, name_th) VALUES (?, ?, ?)');
            $statement->execute([$schoolId, $code, $nameTh]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('รหัสรายวิชานี้มีอยู่แล้วในโรงเรียน');
            }
            throw $exception;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $schoolId, int $subjectId, string $code, string $nameTh): void
    {
        try {
            $statement = $this->pdo->prepare('UPDATE subjects SET code = ?, name_th = ? WHERE school_id = ? AND id = ?');
            $statement->execute([$code, $nameTh, $schoolId, $subjectId]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('รหัสรายวิชานี้มีอยู่แล้วในโรงเรียน');
            }
            throw $exception;
        }
    }

    public function updateStatus(int $schoolId, int $subjectId, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE subjects SET status = ? WHERE school_id = ? AND id = ?');
        $statement->execute([$status, $schoolId, $subjectId]);
    }
}
