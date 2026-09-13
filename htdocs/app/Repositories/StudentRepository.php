<?php
declare(strict_types=1);

namespace App\Repositories;

use DomainException;
use PDO;
use PDOException;

final class StudentRepository
{
    private const LIST_COLUMNS = 'id, school_id, student_code, prefix_th, first_name_th, last_name_th, gender_code, birth_date, status, created_at, updated_at';
    private const COLUMNS = self::LIST_COLUMNS . ', national_id';

    public function __construct(private PDO $pdo) {}

    public function listForSchool(int $schoolId, ?string $search = null): array
    {
        $sql = 'SELECT ' . self::LIST_COLUMNS . ' FROM students WHERE school_id = :school_id';
        $parameters = ['school_id' => $schoolId];
        if ($search !== null && $search !== '') {
            $pattern = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $sql .= " AND (student_code LIKE :code ESCAPE '!'
                OR first_name_th LIKE :first_name ESCAPE '!'
                OR last_name_th LIKE :last_name ESCAPE '!'
                OR CONCAT_WS(' ', prefix_th, first_name_th, last_name_th) LIKE :full_name ESCAPE '!')";
            $parameters += ['code' => $pattern, 'first_name' => $pattern, 'last_name' => $pattern, 'full_name' => $pattern];
        }
        $statement = $this->pdo->prepare($sql . ' ORDER BY student_code ASC, id ASC');
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function findForSchool(int $schoolId, int $studentId): ?array
    {
        return $this->fetch('SELECT ' . self::COLUMNS . ' FROM students WHERE school_id = :school_id AND id = :student_id LIMIT 1',
            ['school_id' => $schoolId, 'student_id' => $studentId]);
    }

    public function lockForSchool(int $schoolId, int $studentId): ?array
    {
        return $this->fetch('SELECT ' . self::COLUMNS . ' FROM students WHERE school_id = :school_id AND id = :student_id LIMIT 1 FOR UPDATE',
            ['school_id' => $schoolId, 'student_id' => $studentId]);
    }

    public function findByCodeForSchool(int $schoolId, string $studentCode): ?array
    {
        return $this->fetch('SELECT ' . self::COLUMNS . ' FROM students WHERE school_id = :school_id AND student_code = :student_code LIMIT 1',
            ['school_id' => $schoolId, 'student_code' => $studentCode]);
    }

    public function findByNationalIdForSchool(int $schoolId, string $nationalId): ?array
    {
        return $this->fetch('SELECT ' . self::COLUMNS . ' FROM students WHERE school_id = :school_id AND national_id = :national_id LIMIT 1',
            ['school_id' => $schoolId, 'national_id' => $nationalId]);
    }

    public function create(int $schoolId, array $profile): int
    {
        $this->writeProfile(
            "INSERT INTO students (school_id, student_code, national_id, prefix_th, first_name_th, last_name_th, gender_code, birth_date, status)
             VALUES (:school_id, :student_code, :national_id, :prefix_th, :first_name_th, :last_name_th, :gender_code, :birth_date, 'ACTIVE')",
            ['school_id' => $schoolId] + $this->profileParameters($profile)
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $schoolId, int $studentId, array $profile): void
    {
        $this->writeProfile(
            'UPDATE students SET student_code = :student_code, national_id = :national_id, prefix_th = :prefix_th,
                first_name_th = :first_name_th, last_name_th = :last_name_th, gender_code = :gender_code, birth_date = :birth_date
             WHERE school_id = :school_id AND id = :student_id',
            ['school_id' => $schoolId, 'student_id' => $studentId] + $this->profileParameters($profile)
        );
    }

    public function updateStatus(int $schoolId, int $studentId, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE students SET status = :status WHERE school_id = :school_id AND id = :student_id');
        $statement->execute(['status' => $status, 'school_id' => $schoolId, 'student_id' => $studentId]);
    }

    private function fetch(string $sql, array $parameters): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    private function profileParameters(array $profile): array
    {
        $parameters = [];
        foreach (['student_code', 'national_id', 'prefix_th', 'first_name_th', 'last_name_th', 'gender_code', 'birth_date'] as $field) {
            $parameters[$field] = $profile[$field];
        }

        return $parameters;
    }

    private function writeProfile(string $sql, array $parameters): void
    {
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('รหัสนักเรียนหรือเลขประจำตัวประชาชนมีอยู่แล้วในโรงเรียน');
            }
            throw $exception;
        }
    }
}
