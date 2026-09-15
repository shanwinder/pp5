<?php
declare(strict_types=1);

use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StudentCoreSchemaTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = Database::connect($config);
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    #[DataProvider('schemas')]
    public function test_columns_types_nullability_defaults_and_storage(string $table, array $expected): void
    {
        $this->tableExists($table);
        $q = $this->pdo->prepare('SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $q->execute([$table]);
        self::assertSame(['ENGINE' => 'InnoDB', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci'], $q->fetch());
        $columns = $this->pdo->query("SHOW FULL COLUMNS FROM {$table}")->fetchAll();
        self::assertSame(array_keys($expected), array_column($columns, 'Field'));
        foreach ($columns as $column) {
            $type = preg_replace('/\b(bigint|int)\(\d+\)/', '$1', $column['Type']);
            $default = $column['Default'];
            if (is_string($default)) {
                $default = trim(strtolower($default) === 'current_timestamp()' ? 'CURRENT_TIMESTAMP' : $default, "'");
            }
            self::assertSame($expected[$column['Field']], [$type, $column['Null'], $default], $table . '.' . $column['Field']);
            if ($column['Field'] === 'id') {
                self::assertStringContainsString('auto_increment', $column['Extra']);
            }
            if ($column['Field'] === 'updated_at') {
                self::assertStringContainsString('on update current_timestamp', strtolower($column['Extra']));
            }
        }
    }

    public static function schemas(): array
    {
        $id = ['bigint unsigned', 'NO', null];
        $date = ['date', 'YES', null];
        $time = ['datetime', 'NO', 'CURRENT_TIMESTAMP'];
        $nullableTime = ['datetime', 'YES', null];
        $active = ['varchar(20)', 'NO', 'ACTIVE'];
        $profile = [
            'student_code' => ['varchar(50)', 'NO', null], 'national_id' => ['char(13)', 'YES', null],
            'prefix_th' => ['varchar(50)', 'NO', null], 'first_name_th' => ['varchar(100)', 'NO', null],
            'last_name_th' => ['varchar(100)', 'NO', null], 'gender_code' => ['varchar(20)', 'YES', null], 'birth_date' => $date,
        ];
        return [
            ['students', ['id' => $id, 'school_id' => $id, ...$profile, 'status' => $active, 'created_at' => $time, 'updated_at' => $time]],
            ['student_enrollments', ['id' => $id, 'school_id' => $id, 'academic_year_id' => $id, 'student_id' => $id,
                'grade_level_id' => $id, 'entry_date' => $date, 'exit_date' => $date, 'status' => ['varchar(30)', 'NO', 'ACTIVE'],
                'created_at' => $time, 'updated_at' => $time]],
            ['student_classroom_placements', ['id' => $id, 'school_id' => $id, 'academic_year_id' => $id, 'grade_level_id' => $id,
                'enrollment_id' => $id, 'classroom_id' => $id, 'status' => $active, 'started_at' => $time, 'ended_at' => $nullableTime]],
            ['student_import_batches', ['id' => $id, 'school_id' => $id, 'academic_year_id' => $id, 'created_by' => $id,
                'source_name' => ['varchar(190)', 'NO', null], 'source_sha256' => ['char(64)', 'NO', null],
                'status' => ['varchar(20)', 'NO', 'PREVIEW'], 'row_count' => ['int unsigned', 'NO', '0'],
                'create_student_count' => ['int unsigned', 'NO', '0'], 'create_enrollment_count' => ['int unsigned', 'NO', '0'],
                'noop_count' => ['int unsigned', 'NO', '0'], 'error_count' => ['int unsigned', 'NO', '0'],
                'created_at' => $time, 'expires_at' => ['datetime', 'NO', null], 'applied_at' => $nullableTime, 'cancelled_at' => $nullableTime]],
            ['student_import_rows', ['id' => $id, 'batch_id' => $id, 'school_id' => $id, 'academic_year_id' => $id,
                'row_no' => ['int unsigned', 'NO', null], ...$profile, 'grade_level_code' => ['varchar(20)', 'NO', null],
                'classroom_code' => ['varchar(50)', 'YES', null], 'entry_date' => $date, 'matched_student_id' => ['bigint unsigned', 'YES', null],
                'student_action' => ['varchar(20)', 'NO', null], 'enrollment_action' => ['varchar(20)', 'NO', null],
                'error_code' => ['varchar(50)', 'YES', null], 'error_message' => ['varchar(500)', 'YES', null]]],
        ];
    }

    #[DataProvider('keys')]
    public function test_required_unique_and_lookup_keys(string $table, array $unique, array $lookup): void
    {
        $this->tableExists($table);
        $actual = [];
        foreach ($this->pdo->query("SHOW INDEX FROM {$table}")->fetchAll() as $row) {
            $name = $row['Key_name'];
            $actual[$name]['unique'] = (int) $row['Non_unique'] === 0;
            $actual[$name]['columns'][(int) $row['Seq_in_index']] = $row['Column_name'];
        }
        foreach ($actual as &$index) { ksort($index['columns']); $index['columns'] = array_values($index['columns']); }
        unset($index);
        $actualUnique = array_map(static fn (array $i): array => $i['columns'], array_filter($actual, static fn (array $i): bool => $i['unique']));
        self::assertEqualsCanonicalizing($unique, array_values($actualUnique));
        foreach ($lookup as $name => $columns) {
            self::assertSame(['unique' => false, 'columns' => $columns], $actual[$name] ?? null);
        }
    }

    public static function keys(): array
    {
        return [
            ['students', [['id'], ['school_id', 'student_code'], ['school_id', 'national_id'], ['id', 'school_id']], []],
            ['student_enrollments', [['id'], ['school_id', 'academic_year_id', 'student_id'], ['id', 'school_id', 'academic_year_id', 'grade_level_id']],
                ['idx_student_enrollment_year_grade_status' => ['school_id', 'academic_year_id', 'grade_level_id', 'status']]],
            ['classrooms', [['id'], ['school_id', 'academic_year_id', 'code'], ['id', 'school_id', 'academic_year_id'], ['id', 'school_id', 'academic_year_id', 'grade_level_id']], []],
            ['student_classroom_placements', [['id']], ['idx_student_placement_enrollment_status' => ['school_id', 'enrollment_id', 'status']]],
            ['student_import_batches', [['id'], ['id', 'school_id', 'academic_year_id']], [
                'idx_student_import_school_year_status' => ['school_id', 'academic_year_id', 'status'],
                'idx_student_import_school_status_expiry' => ['school_id', 'status', 'expires_at'],
                'idx_student_import_applied_hash' => ['school_id', 'academic_year_id', 'source_sha256', 'status']]],
            ['student_import_rows', [['id'], ['batch_id', 'row_no']], ['idx_student_import_row_batch' => ['school_id', 'batch_id', 'row_no']]],
        ];
    }

    #[DataProvider('foreignKeys')]
    public function test_ordered_composite_foreign_keys_and_retention_rules(string $table, string $name, array $columns, string $parent, array $parentColumns): void
    {
        $q = $this->pdo->prepare('SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
            ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
            WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.CONSTRAINT_NAME=? ORDER BY k.ORDINAL_POSITION');
        $q->execute([$table, $name]); $rows = $q->fetchAll();
        self::assertCount(count($columns), $rows, 'Missing FK ' . $name);
        self::assertSame($columns, array_column($rows, 'COLUMN_NAME'));
        self::assertSame($parentColumns, array_column($rows, 'REFERENCED_COLUMN_NAME'));
        foreach ($rows as $r) {
            self::assertSame($parent, $r['REFERENCED_TABLE_NAME']);
            self::assertSame('RESTRICT', $r['DELETE_RULE']); self::assertSame('CASCADE', $r['UPDATE_RULE']);
        }
    }

    public static function foreignKeys(): array
    {
        return [
            ['students', 'fk_student_school', ['school_id'], 'schools', ['id']],
            ['student_enrollments', 'fk_student_enrollment_year_school', ['academic_year_id', 'school_id'], 'academic_years', ['id', 'school_id']],
            ['student_enrollments', 'fk_student_enrollment_student_school', ['student_id', 'school_id'], 'students', ['id', 'school_id']],
            ['student_enrollments', 'fk_student_enrollment_grade', ['grade_level_id'], 'grade_levels', ['id']],
            ['student_classroom_placements', 'fk_student_placement_enrollment_scope', ['enrollment_id', 'school_id', 'academic_year_id', 'grade_level_id'], 'student_enrollments', ['id', 'school_id', 'academic_year_id', 'grade_level_id']],
            ['student_classroom_placements', 'fk_student_placement_classroom_scope', ['classroom_id', 'school_id', 'academic_year_id', 'grade_level_id'], 'classrooms', ['id', 'school_id', 'academic_year_id', 'grade_level_id']],
            ['student_import_batches', 'fk_student_import_batch_year_school', ['academic_year_id', 'school_id'], 'academic_years', ['id', 'school_id']],
            ['student_import_batches', 'fk_student_import_batch_user', ['created_by'], 'users', ['id']],
            ['student_import_rows', 'fk_student_import_row_batch_scope', ['batch_id', 'school_id', 'academic_year_id'], 'student_import_batches', ['id', 'school_id', 'academic_year_id']],
            ['student_import_rows', 'fk_student_import_row_matched_student', ['matched_student_id', 'school_id'], 'students', ['id', 'school_id']],
        ];
    }

    #[DataProvider('invalidRelationships')]
    public function test_database_rejects_foreign_school_year_grade_and_missing_parents(string $table, array $changes, string $constraint): void
    {
        $f = $this->fixtures();
        $values = $f['values'][$table];
        foreach ($changes as $column => $fixture) { $values[$column] = $fixture === 'missing' ? 0 : $f[$fixture]; }
        $this->reject($table, $values, 1452, $constraint);
    }

    public static function invalidRelationships(): array
    {
        return [
            ['students', ['school_id' => 'missing'], 'fk_student_school'],
            ['student_enrollments', ['student_id' => 'studentB'], 'fk_student_enrollment_student_school'],
            ['student_enrollments', ['academic_year_id' => 'yearB'], 'fk_student_enrollment_year_school'],
            ['student_enrollments', ['grade_level_id' => 'missing'], 'fk_student_enrollment_grade'],
            ['student_classroom_placements', ['enrollment_id' => 'enrollmentB'], 'fk_student_placement_enrollment_scope'],
            ['student_classroom_placements', ['enrollment_id' => 'enrollmentNext'], 'fk_student_placement_enrollment_scope'],
            ['student_classroom_placements', ['enrollment_id' => 'enrollmentGrade2'], 'fk_student_placement_enrollment_scope'],
            ['student_classroom_placements', ['classroom_id' => 'roomB'], 'fk_student_placement_classroom_scope'],
            ['student_classroom_placements', ['classroom_id' => 'roomNext'], 'fk_student_placement_classroom_scope'],
            ['student_classroom_placements', ['classroom_id' => 'roomGrade2'], 'fk_student_placement_classroom_scope'],
            ['student_import_batches', ['academic_year_id' => 'yearB'], 'fk_student_import_batch_year_school'],
            ['student_import_batches', ['created_by' => 'missing'], 'fk_student_import_batch_user'],
            ['student_import_rows', ['batch_id' => 'batchB'], 'fk_student_import_row_batch_scope'],
            ['student_import_rows', ['batch_id' => 'batchNext'], 'fk_student_import_row_batch_scope'],
            ['student_import_rows', ['matched_student_id' => 'studentB'], 'fk_student_import_row_matched_student'],
        ];
    }

    #[DataProvider('duplicates')]
    public function test_database_rejects_duplicate_business_identity(string $table, string $constraint, array $change): void
    {
        $f = $this->fixtures(); $values = $f['values'][$table];
        $this->insert($table, $values);
        $this->reject($table, array_replace($values, $change), 1062, $constraint);
    }

    public static function duplicates(): array
    {
        return [
            ['students', 'uq_students_school_code', ['national_id' => null]],
            ['students', 'uq_students_school_national_id', ['student_code' => 'different-code']],
            ['student_enrollments', 'uq_student_enrollment_school_year_student', []],
            ['student_import_rows', 'uq_student_import_row_batch_row', []],
        ];
    }

    public function test_valid_scopes_nullable_identity_and_history_defaults(): void
    {
        $f = $this->fixtures();
        $a = $this->insert('students', $f['values']['students']);
        $b = $this->insert('students', array_replace($f['values']['students'], ['school_id' => $f['schoolB']]));
        self::assertNotSame($a, $b);
        foreach (['null-one', 'null-two'] as $code) {
            $id = $this->insert('students', array_replace($f['values']['students'], ['student_code' => $code, 'national_id' => null]));
            $r = $this->row('students', $id);
            self::assertNull($r['national_id']); self::assertNull($r['gender_code']); self::assertNull($r['birth_date']);
            self::assertSame('ACTIVE', $r['status']); self::assertSame($r['created_at'], $r['updated_at']);
        }
        // Student identity survives across years and enrollment may be unplaced.
        $e = $this->insert('student_enrollments', $f['values']['student_enrollments']);
        $next = $this->insert('student_enrollments', array_replace($f['values']['student_enrollments'], ['academic_year_id' => $f['yearNext']]));
        self::assertNotSame($e, $next);
        $r = $this->row('student_enrollments', $e);
        self::assertSame('ACTIVE', $r['status']); self::assertNull($r['entry_date']); self::assertNull($r['exit_date']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM student_classroom_placements')->fetchColumn());
        $placement = $this->insert('student_classroom_placements', $f['values']['student_classroom_placements']);
        $r = $this->row('student_classroom_placements', $placement);
        self::assertSame('ACTIVE', $r['status']); self::assertNotNull($r['started_at']); self::assertNull($r['ended_at']);
        $ended = array_replace($f['values']['student_classroom_placements'], ['status' => 'ENDED', 'ended_at' => '2026-09-12 12:00:00']);
        self::assertNotSame($placement, $this->insert('student_classroom_placements', $ended));
        $batch = $this->row('student_import_batches', $f['batchA']);
        self::assertSame('PREVIEW', $batch['status']); self::assertNull($batch['applied_at']); self::assertNull($batch['cancelled_at']);
        foreach (['row_count', 'create_student_count', 'create_enrollment_count', 'noop_count', 'error_count'] as $field) { self::assertSame(0, $batch[$field]); }
        $row = $this->insert('student_import_rows', array_replace($f['values']['student_import_rows'], ['matched_student_id' => null]));
        $r = $this->row('student_import_rows', $row);
        foreach (['national_id', 'gender_code', 'birth_date', 'classroom_code', 'entry_date', 'matched_student_id', 'error_code', 'error_message'] as $field) { self::assertNull($r[$field]); }
        $this->insert('student_import_rows', array_replace($f['values']['student_import_rows'], ['batch_id' => $f['batchNext'], 'academic_year_id' => $f['yearNext']]));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn());
    }

    private function fixtures(): array
    {
        foreach (['students', 'student_enrollments', 'student_classroom_placements', 'student_import_batches', 'student_import_rows'] as $t) { $this->tableExists($t); }
        $f = [];
        foreach (['A', 'B'] as $key) {
            $f['school' . $key] = $this->insert('schools', ['school_code' => 'm4-schema-' . $key, 'name_th' => 'โรงเรียนทดสอบ ' . $key]);
            $f['year' . $key] = $this->insert('academic_years', ['school_id' => $f['school' . $key], 'year_be' => 2569]);
            $f['student' . $key] = $this->insert('students', ['school_id' => $f['school' . $key], 'student_code' => 'fixture', 'prefix_th' => 'ด.ช.', 'first_name_th' => 'ทดสอบ', 'last_name_th' => 'สมมติ']);
        }
        $f['yearNext'] = $this->insert('academic_years', ['school_id' => $f['schoolA'], 'year_be' => 2570]);
        $grades = $this->pdo->query("SELECT id FROM grade_levels WHERE code IN ('P1','P2') ORDER BY code")->fetchAll(PDO::FETCH_COLUMN);
        [$g1, $g2] = $grades;
        $f['user'] = $this->insert('users', ['username' => 'm4-schema-user', 'password_hash' => 'unused-test-hash', 'display_name' => 'Schema test']);
        foreach (['A' => [$f['schoolA'], $f['yearA'], $g1, $f['studentA']], 'B' => [$f['schoolB'], $f['yearB'], $g1, $f['studentB']],
            'Next' => [$f['schoolA'], $f['yearNext'], $g1, $f['studentA']], 'Grade2' => [$f['schoolA'], $f['yearA'], $g2, $f['studentA']]] as $key => [$school, $year, $grade, $student]) {
            $f['room' . $key] = $this->insert('classrooms', ['school_id' => $school, 'academic_year_id' => $year, 'grade_level_id' => $grade, 'code' => $key, 'name_th' => 'ห้องทดสอบ']);
            // A different student lets the wrong-grade enrollment coexist in the same year.
            if ($key === 'Grade2') { $student = $this->insert('students', ['school_id' => $school, 'student_code' => 'grade2', 'prefix_th' => 'ด.ญ.', 'first_name_th' => 'ทดสอบ', 'last_name_th' => 'สมมติ']); }
            $f['enrollment' . $key] = $this->insert('student_enrollments', ['school_id' => $school, 'academic_year_id' => $year, 'grade_level_id' => $grade, 'student_id' => $student]);
        }
        foreach (['A', 'B', 'Next'] as $key) {
            $f['batch' . $key] = $this->insert('student_import_batches', ['school_id' => $key === 'B' ? $f['schoolB'] : $f['schoolA'], 'academic_year_id' => $f['year' . $key],
                'created_by' => $f['user'], 'source_name' => 'synthetic.csv', 'source_sha256' => str_repeat('a', 64), 'expires_at' => '2026-09-14 12:00:00']);
        }
        $freshStudent = $this->insert('students', ['school_id' => $f['schoolA'], 'student_code' => 'unenrolled', 'prefix_th' => 'ด.ช.', 'first_name_th' => 'ทดสอบ', 'last_name_th' => 'สมมติ']);
        $f['values'] = [
            'students' => ['school_id' => $f['schoolA'], 'student_code' => 'ทดสอบ-001', 'national_id' => '0000000000000', 'prefix_th' => 'ด.ญ.', 'first_name_th' => 'ทดสอบ', 'last_name_th' => 'สมมติ'],
            'student_enrollments' => ['school_id' => $f['schoolA'], 'academic_year_id' => $f['yearA'], 'student_id' => $freshStudent, 'grade_level_id' => $g1],
            'student_classroom_placements' => ['school_id' => $f['schoolA'], 'academic_year_id' => $f['yearA'], 'grade_level_id' => $g1, 'enrollment_id' => $f['enrollmentA'], 'classroom_id' => $f['roomA']],
            'student_import_batches' => ['school_id' => $f['schoolA'], 'academic_year_id' => $f['yearA'], 'created_by' => $f['user'], 'source_name' => 'synthetic.csv', 'source_sha256' => str_repeat('b', 64), 'expires_at' => '2026-09-14 12:00:00'],
            'student_import_rows' => ['batch_id' => $f['batchA'], 'school_id' => $f['schoolA'], 'academic_year_id' => $f['yearA'], 'row_no' => 1,
                'student_code' => 'ทดสอบ', 'prefix_th' => 'ด.ช.', 'first_name_th' => 'ทดสอบ', 'last_name_th' => 'สมมติ', 'grade_level_code' => 'P1',
                'matched_student_id' => $f['studentA'], 'student_action' => 'MATCH', 'enrollment_action' => 'NOOP'],
        ];
        return $f;
    }

    private function tableExists(string $table): void
    {
        self::assertContains($table, $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), 'Missing Milestone 4 table: ' . $table);
    }

    private function insert(string $table, array $values): int
    {
        $q = $this->pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($values)) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')');
        $q->execute(array_values($values));
        return (int) $this->pdo->lastInsertId();
    }

    private function row(string $table, int $id): array
    {
        $q = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = ?"); $q->execute([$id]); return $q->fetch();
    }

    private function reject(string $table, array $values, int $code, string $constraint): void
    {
        $before = $this->pdo->query("SELECT * FROM {$table} ORDER BY id")->fetchAll();
        try { $this->insert($table, $values); }
        catch (PDOException $e) {
            self::assertSame('23000', $e->errorInfo[0]); self::assertSame($code, $e->errorInfo[1]);
            self::assertStringContainsString($constraint, $e->getMessage());
            self::assertSame($before, $this->pdo->query("SELECT * FROM {$table} ORDER BY id")->fetchAll());
            return;
        }
        self::fail('Expected rejection by ' . $constraint);
    }
}
