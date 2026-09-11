<?php
declare(strict_types=1);

namespace App\Repositories;

use DomainException;
use PDO;
use PDOException;

final class SubjectOfferingRepository
{
    private const COLUMNS = 'o.id, o.school_id, o.academic_year_id, o.classroom_id, o.subject_id, o.term_no, o.status, o.created_at, o.updated_at';
    private const DETAILS = ', y.year_be, y.status AS academic_year_status, c.code AS classroom_code, c.name_th AS classroom_name,
        c.status AS classroom_status, s.code AS subject_code, s.name_th AS subject_name, s.status AS subject_status';
    private const JOINS = ' FROM subject_offerings o
        JOIN academic_years y ON y.id = o.academic_year_id AND y.school_id = o.school_id
        JOIN classrooms c ON c.id = o.classroom_id AND c.school_id = o.school_id AND c.academic_year_id = o.academic_year_id
        JOIN subjects s ON s.id = o.subject_id AND s.school_id = o.school_id';

    public function __construct(private PDO $pdo) {}

    public function listForSchool(int $schoolId, ?int $academicYearId = null): array
    {
        $sql = 'SELECT ' . self::COLUMNS . self::DETAILS . self::JOINS . ' WHERE o.school_id = ?';
        $parameters = [$schoolId];
        if ($academicYearId !== null) {
            $sql .= ' AND o.academic_year_id = ?';
            $parameters[] = $academicYearId;
        }
        $sql .= ' ORDER BY y.year_be DESC, c.code ASC, s.code ASC, o.term_no ASC, o.id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function findForSchool(int $schoolId, int $offeringId): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . self::DETAILS . self::JOINS . ' WHERE o.school_id = ? AND o.id = ? LIMIT 1');
        $statement->execute([$schoolId, $offeringId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function lockForSchool(int $schoolId, int $offeringId): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM subject_offerings o WHERE o.school_id = ? AND o.id = ? LIMIT 1 FOR UPDATE');
        $statement->execute([$schoolId, $offeringId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function create(int $schoolId, int $academicYearId, int $classroomId, int $subjectId, int $termNo): int
    {
        try {
            $statement = $this->pdo->prepare('INSERT INTO subject_offerings (school_id, academic_year_id, classroom_id, subject_id, term_no) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$schoolId, $academicYearId, $classroomId, $subjectId, $termNo]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('มีการเปิดรายวิชานี้สำหรับห้องเรียนและภาคเรียนนี้แล้ว');
            }
            throw $exception;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $schoolId, int $offeringId, int $classroomId, int $subjectId, int $termNo): void
    {
        try {
            $statement = $this->pdo->prepare('UPDATE subject_offerings SET classroom_id = ?, subject_id = ?, term_no = ? WHERE school_id = ? AND id = ?');
            $statement->execute([$classroomId, $subjectId, $termNo, $schoolId, $offeringId]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('มีการเปิดรายวิชานี้สำหรับห้องเรียนและภาคเรียนนี้แล้ว');
            }
            throw $exception;
        }
    }

    public function updateStatus(int $schoolId, int $offeringId, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE subject_offerings SET status = ? WHERE school_id = ? AND id = ?');
        $statement->execute([$status, $schoolId, $offeringId]);
    }
}
