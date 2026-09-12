<?php
declare(strict_types=1);

namespace App\Repositories;

use DomainException;
use PDO;
use PDOException;

final class AcademicYearRepository
{
    private const COLUMNS = 'id, school_id, year_be, start_date, end_date, status, created_at, updated_at';

    public function __construct(private PDO $pdo) {}

    public function listForSchool(int $schoolId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM academic_years WHERE school_id = ? ORDER BY year_be DESC, id DESC'
        );
        $statement->execute([$schoolId]);

        return $statement->fetchAll();
    }

    public function findForSchool(int $schoolId, int $academicYearId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM academic_years WHERE school_id = ? AND id = ? LIMIT 1'
        );
        $statement->execute([$schoolId, $academicYearId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function lockForSchool(int $schoolId, int $academicYearId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM academic_years WHERE school_id = ? AND id = ? LIMIT 1 FOR UPDATE'
        );
        $statement->execute([$schoolId, $academicYearId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function findActiveForSchool(int $schoolId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . " FROM academic_years WHERE school_id = ? AND status = 'ACTIVE' ORDER BY year_be DESC, id DESC LIMIT 1"
        );
        $statement->execute([$schoolId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function create(int $schoolId, int $yearBe, ?string $startDate, ?string $endDate): int
    {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO academic_years (school_id, year_be, start_date, end_date) VALUES (?, ?, ?, ?)'
            );
            $statement->execute([$schoolId, $yearBe, $startDate, $endDate]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('ปีการศึกษานี้มีอยู่แล้ว');
            }
            throw $exception;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function updateDraft(int $schoolId, int $academicYearId, int $yearBe, ?string $startDate, ?string $endDate): void
    {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE academic_years SET year_be = ?, start_date = ?, end_date = ? WHERE school_id = ? AND id = ?'
            );
            $statement->execute([$yearBe, $startDate, $endDate, $schoolId, $academicYearId]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('ปีการศึกษานี้มีอยู่แล้ว');
            }
            throw $exception;
        }
    }

    public function updateStatus(int $schoolId, int $academicYearId, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE academic_years SET status = ? WHERE school_id = ? AND id = ?');
        $statement->execute([$status, $schoolId, $academicYearId]);
    }
}
