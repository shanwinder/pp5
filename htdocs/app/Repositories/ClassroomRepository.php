<?php
declare(strict_types=1);

namespace App\Repositories;

use DomainException;
use PDO;
use PDOException;

final class ClassroomRepository
{
    private const COLUMNS = 'c.id, c.school_id, c.academic_year_id, c.grade_level_id, c.code, c.name_th, c.status, c.created_at, c.updated_at';
    private const DETAILS = ', y.year_be, y.status AS academic_year_status, g.name_th AS grade_level_name';
    private const JOINS = ' FROM classrooms c JOIN academic_years y ON y.id = c.academic_year_id AND y.school_id = c.school_id
        JOIN grade_levels g ON g.id = c.grade_level_id';

    public function __construct(private PDO $pdo) {}

    public function listForSchool(int $schoolId, ?int $academicYearId = null): array
    {
        $sql = 'SELECT ' . self::COLUMNS . self::DETAILS . self::JOINS . ' WHERE c.school_id = ?';
        $parameters = [$schoolId];
        if ($academicYearId !== null) {
            $sql .= ' AND c.academic_year_id = ?';
            $parameters[] = $academicYearId;
        }
        $sql .= ' ORDER BY y.year_be DESC, g.sort_order ASC, g.code ASC, c.code ASC, c.id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function findForSchool(int $schoolId, int $classroomId): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . self::DETAILS . self::JOINS . ' WHERE c.school_id = ? AND c.id = ? LIMIT 1');
        $statement->execute([$schoolId, $classroomId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function lockForSchool(int $schoolId, int $classroomId): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM classrooms c WHERE c.school_id = ? AND c.id = ? LIMIT 1 FOR UPDATE');
        $statement->execute([$schoolId, $classroomId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function create(int $schoolId, int $academicYearId, int $gradeLevelId, string $code, string $nameTh): int
    {
        try {
            $statement = $this->pdo->prepare('INSERT INTO classrooms (school_id, academic_year_id, grade_level_id, code, name_th) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$schoolId, $academicYearId, $gradeLevelId, $code, $nameTh]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('รหัสห้องเรียนนี้มีอยู่แล้วในปีการศึกษานี้');
            }
            throw $exception;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $schoolId, int $classroomId, int $gradeLevelId, string $code, string $nameTh): void
    {
        try {
            $statement = $this->pdo->prepare('UPDATE classrooms SET grade_level_id = ?, code = ?, name_th = ? WHERE school_id = ? AND id = ?');
            $statement->execute([$gradeLevelId, $code, $nameTh, $schoolId, $classroomId]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('รหัสห้องเรียนนี้มีอยู่แล้วในปีการศึกษานี้');
            }
            throw $exception;
        }
    }

    public function updateStatus(int $schoolId, int $classroomId, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE classrooms SET status = ? WHERE school_id = ? AND id = ?');
        $statement->execute([$status, $schoolId, $classroomId]);
    }
}
