<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class StudentImportRowRepository
{
    private const FIELDS = ['row_no', 'student_code', 'national_id', 'prefix_th', 'first_name_th', 'last_name_th',
        'gender_code', 'birth_date', 'grade_level_code', 'classroom_code', 'entry_date', 'matched_student_id',
        'student_action', 'enrollment_action', 'error_code', 'error_message'];

    public function __construct(private PDO $pdo) {}

    public function insert(int $batchId, int $schoolId, int $academicYearId, array $row): int
    {
        $values = [$batchId, $schoolId, $academicYearId];
        foreach (self::FIELDS as $field) { $values[] = $row[$field]; }
        $this->pdo->prepare('INSERT INTO student_import_rows (batch_id, school_id, academic_year_id, ' . implode(', ', self::FIELDS) . ')
            VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')')->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function listForBatch(int $schoolId, int $batchId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM student_import_rows WHERE school_id = ? AND batch_id = ? ORDER BY row_no, id');
        $statement->execute([$schoolId, $batchId]);
        return $statement->fetchAll();
    }

    public function deleteForBatch(int $schoolId, int $batchId): void
    {
        $this->pdo->prepare('DELETE FROM student_import_rows WHERE school_id = ? AND batch_id = ?')->execute([$schoolId, $batchId]);
    }
}
