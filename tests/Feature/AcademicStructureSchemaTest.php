<?php
declare(strict_types=1);

use App\Repositories\AuthorizationRepository;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AcademicStructureSchemaTest extends TestCase
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

    #[DataProvider('tableSchemas')]
    public function test_tables_have_expected_columns_engine_and_primary_key(string $table, array $expected): void
    {
        $this->assertTableExists($table);
        $statement = $this->pdo->prepare(
            'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute([$table]);
        self::assertSame(['ENGINE' => 'InnoDB', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci'], $statement->fetch());
        $columns = $this->pdo->query("SHOW FULL COLUMNS FROM {$table}")->fetchAll();
        self::assertSame(array_keys($expected), array_column($columns, 'Field'));
        foreach ($columns as $column) {
            [$type, $nullable, $default] = $expected[$column['Field']];
            // MariaDB may report integer display widths and quote string defaults.
            $actualType = preg_replace('/\b(bigint|smallint|tinyint)\(\d+\)/', '$1', $column['Type']);
            $actualDefault = $column['Default'];
            if (is_string($actualDefault)) {
                $actualDefault = trim(strtolower($actualDefault) === 'current_timestamp()' ? 'CURRENT_TIMESTAMP' : $actualDefault, "'");
            }
            self::assertSame([$type, $nullable, $default], [$actualType, $column['Null'], $actualDefault], "{$table}.{$column['Field']}");
            if ($column['Field'] === 'id') {
                self::assertStringContainsString('auto_increment', $column['Extra']);
            }
            if ($column['Field'] === 'updated_at') {
                self::assertStringContainsString('on update current_timestamp', strtolower($column['Extra']));
            }
        }
        self::assertSame(['id'], $this->indexes($table)['PRIMARY']);
    }

    public static function tableSchemas(): array
    {
        $id = ['bigint unsigned', 'NO', null];
        $active = ['varchar(20)', 'NO', 'ACTIVE'];
        $timestamps = [
            'created_at' => ['datetime', 'NO', 'CURRENT_TIMESTAMP'],
            'updated_at' => ['datetime', 'NO', 'CURRENT_TIMESTAMP'],
        ];
        return [
            'grade levels' => ['grade_levels', [
                'id' => $id, 'code' => ['varchar(20)', 'NO', null], 'name_th' => ['varchar(100)', 'NO', null],
                'sort_order' => ['smallint unsigned', 'NO', null], 'status' => $active,
            ]],
            'academic years' => ['academic_years', [
                'id' => $id, 'school_id' => $id, 'year_be' => ['smallint unsigned', 'NO', null],
                'start_date' => ['date', 'YES', null], 'end_date' => ['date', 'YES', null],
                'status' => ['varchar(20)', 'NO', 'DRAFT'], ...$timestamps,
            ]],
            'classrooms' => ['classrooms', [
                'id' => $id, 'school_id' => $id, 'academic_year_id' => $id, 'grade_level_id' => $id,
                'code' => ['varchar(50)', 'NO', null], 'name_th' => ['varchar(120)', 'NO', null],
                'status' => $active, ...$timestamps,
            ]],
            'subjects' => ['subjects', [
                'id' => $id, 'school_id' => $id, 'code' => ['varchar(50)', 'NO', null],
                'name_th' => ['varchar(190)', 'NO', null], 'status' => $active, ...$timestamps,
            ]],
            'offerings' => ['subject_offerings', [
                'id' => $id, 'school_id' => $id, 'academic_year_id' => $id, 'classroom_id' => $id,
                'subject_id' => $id, 'term_no' => ['tinyint unsigned', 'NO', null], 'status' => $active, ...$timestamps,
            ]],
        ];
    }

    #[DataProvider('uniqueKeys')]
    public function test_unique_candidate_keys_preserve_tenant_identity(string $table, array $expected): void
    {
        $this->assertTableExists($table);
        self::assertEqualsCanonicalizing($expected, array_values($this->indexes($table)));
    }

    public static function uniqueKeys(): array
    {
        return [
            ['grade_levels', [['id'], ['code']]],
            ['academic_years', [['id'], ['school_id', 'year_be'], ['id', 'school_id']]],
            ['classrooms', [['id'], ['school_id', 'academic_year_id', 'code'], ['id', 'school_id', 'academic_year_id'], ['id', 'school_id', 'academic_year_id', 'grade_level_id']]],
            ['subjects', [['id'], ['school_id', 'code'], ['id', 'school_id']]],
            ['subject_offerings', [['id'], ['school_id', 'academic_year_id', 'classroom_id', 'subject_id', 'term_no']]],
        ];
    }

    #[DataProvider('foreignKeys')]
    public function test_foreign_keys_enforce_ordered_parent_columns_and_restrict_cascade_rules(
        string $table, string $constraint, array $columns, string $parent, array $parentColumns
    ): void {
        $statement = $this->pdo->prepare(
            'SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.REFERENTIAL_CONSTRAINTS r
               ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
             WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.CONSTRAINT_NAME = ?
             ORDER BY k.ORDINAL_POSITION'
        );
        $statement->execute([$table, $constraint]);
        $rows = $statement->fetchAll();
        self::assertCount(count($columns), $rows, "Missing {$constraint}");
        self::assertSame($columns, array_column($rows, 'COLUMN_NAME'));
        self::assertSame($parentColumns, array_column($rows, 'REFERENCED_COLUMN_NAME'));
        foreach ($rows as $row) {
            self::assertSame($parent, $row['REFERENCED_TABLE_NAME']);
            self::assertSame('RESTRICT', $row['DELETE_RULE']);
            self::assertSame('CASCADE', $row['UPDATE_RULE']);
        }
    }

    public static function foreignKeys(): array
    {
        return [
            ['academic_years', 'fk_academic_year_school', ['school_id'], 'schools', ['id']],
            ['classrooms', 'fk_classroom_year_school', ['academic_year_id', 'school_id'], 'academic_years', ['id', 'school_id']],
            ['classrooms', 'fk_classroom_grade_level', ['grade_level_id'], 'grade_levels', ['id']],
            ['subjects', 'fk_subject_school', ['school_id'], 'schools', ['id']],
            ['subject_offerings', 'fk_offering_classroom_school_year', ['classroom_id', 'school_id', 'academic_year_id'], 'classrooms', ['id', 'school_id', 'academic_year_id']],
            ['subject_offerings', 'fk_offering_subject_school', ['subject_id', 'school_id'], 'subjects', ['id', 'school_id']],
            ['user_role_assignments', 'fk_assignment_academic_year_school', ['academic_year_id', 'school_id'], 'academic_years', ['id', 'school_id']],
        ];
    }

    public function test_valid_parent_combinations_accept_defaults_and_school_scoped_identities(): void
    {
        $f = $this->fixtures();
        $offering = $this->insert(
            'INSERT INTO subject_offerings (school_id, academic_year_id, classroom_id, subject_id, term_no) VALUES (?, ?, ?, ?, ?)',
            [$f['schoolA'], $f['yearA1'], $f['classA1'], $f['subjectA'], 1]
        );
        $this->insert(
            'INSERT INTO subject_offerings (school_id, academic_year_id, classroom_id, subject_id, term_no) VALUES (?, ?, ?, ?, ?)',
            [$f['schoolA'], $f['yearA1'], $f['classA1'], $f['subjectA'], 2]
        );
        $this->insert(
            'INSERT INTO subject_offerings (school_id, academic_year_id, classroom_id, subject_id, term_no) VALUES (?, ?, ?, ?, ?)',
            [$f['schoolB'], $f['yearB'], $f['classB'], $f['subjectB'], 1]
        );
        self::assertSame(['start_date' => null, 'end_date' => null, 'status' => 'DRAFT'],
            $this->row('SELECT start_date, end_date, status FROM academic_years WHERE id = ?', [$f['yearA1']]));
        foreach (['grade_levels' => $f['grade'], 'classrooms' => $f['classA1'], 'subjects' => $f['subjectA'], 'subject_offerings' => $offering] as $table => $id) {
            self::assertSame('ACTIVE', $this->row("SELECT status FROM {$table} WHERE id = ?", [$id])['status']);
        }
        foreach (['academic_years' => $f['yearA1'], 'classrooms' => $f['classA1'], 'subjects' => $f['subjectA'], 'subject_offerings' => $offering] as $table => $id) {
            $row = $this->row("SELECT created_at, updated_at FROM {$table} WHERE id = ?", [$id]);
            self::assertNotNull($row['created_at']);
            self::assertSame($row['created_at'], $row['updated_at']);
        }
    }

    #[DataProvider('invalidParents')]
    public function test_database_rejects_cross_school_and_wrong_year_parents(string $probe, string $constraint): void
    {
        $f = $this->fixtures();
        [$sql, $parameters, $table] = match ($probe) {
            'classroom year' => [
                'INSERT INTO classrooms (school_id, academic_year_id, grade_level_id, code, name_th) VALUES (?, ?, ?, ?, ?)',
                [$f['schoolA'], $f['yearB'], $f['grade'], 'INVALID', 'Invalid classroom'], 'classrooms',
            ],
            'assignment year' => [
                'INSERT INTO user_role_assignments (user_id, school_id, role_id, academic_year_id) VALUES (?, ?, ?, ?)',
                [$f['user'], $f['schoolA'], $f['role'], $f['yearB']], 'user_role_assignments',
            ],
            default => [
                'INSERT INTO subject_offerings (school_id, academic_year_id, classroom_id, subject_id, term_no) VALUES (?, ?, ?, ?, ?)',
                [$f['schoolA'], $f['yearA1'], match ($probe) {
                    'foreign classroom' => $f['classB'], 'wrong year classroom' => $f['classA2'], default => $f['classA1'],
                }, $probe === 'foreign subject' ? $f['subjectB'] : $f['subjectA'], 1], 'subject_offerings',
            ],
        };
        $before = $this->pdo->query("SELECT * FROM {$table} ORDER BY id")->fetchAll();
        $this->assertRejected($sql, $parameters, 1452, $constraint);
        self::assertSame($before, $this->pdo->query("SELECT * FROM {$table} ORDER BY id")->fetchAll());
    }

    public static function invalidParents(): array
    {
        return [
            ['classroom year', 'fk_classroom_year_school'],
            ['foreign classroom', 'fk_offering_classroom_school_year'],
            ['wrong year classroom', 'fk_offering_classroom_school_year'],
            ['foreign subject', 'fk_offering_subject_school'],
            ['assignment year', 'fk_assignment_academic_year_school'],
        ];
    }

    #[DataProvider('duplicateIdentities')]
    public function test_database_rejects_duplicate_identities(string $table, string $key): void
    {
        $f = $this->fixtures();
        [$sql, $parameters] = match ($table) {
            'grade_levels' => ['INSERT INTO grade_levels (code, name_th, sort_order) VALUES (?, ?, ?)', ['SCHEMA_TEST', 'Duplicate', 999]],
            'academic_years' => ['INSERT INTO academic_years (school_id, year_be) VALUES (?, ?)', [$f['schoolA'], 2569]],
            'classrooms' => ['INSERT INTO classrooms (school_id, academic_year_id, grade_level_id, code, name_th) VALUES (?, ?, ?, ?, ?)', [$f['schoolA'], $f['yearA1'], $f['grade'], 'P1-1', 'Duplicate']],
            'subjects' => ['INSERT INTO subjects (school_id, code, name_th) VALUES (?, ?, ?)', [$f['schoolA'], 'ท11101', 'Duplicate']],
            'subject_offerings' => ['INSERT INTO subject_offerings (school_id, academic_year_id, classroom_id, subject_id, term_no) VALUES (?, ?, ?, ?, ?)', [$f['schoolA'], $f['yearA1'], $f['classA1'], $f['subjectA'], 1]],
        };
        if ($table === 'subject_offerings') {
            $this->insert($sql, $parameters);
        }
        $this->assertRejected($sql, $parameters, 1062, $key);
    }

    public static function duplicateIdentities(): array
    {
        return [
            ['grade_levels', 'uq_grade_levels_code'], ['academic_years', 'uq_academic_year_school_year'],
            ['classrooms', 'uq_classroom_school_year_code'], ['subjects', 'uq_subject_school_code'],
            ['subject_offerings', 'uq_offering_identity'],
        ];
    }

    public function test_valid_year_assignment_is_stored_but_does_not_authorize_school_permissions(): void
    {
        $f = $this->fixtures();
        $id = $this->insert(
            'INSERT INTO user_role_assignments (user_id, school_id, role_id, academic_year_id) VALUES (?, ?, ?, ?)',
            [$f['user'], $f['schoolA'], $f['role'], $f['yearA1']]
        );
        self::assertSame($f['yearA1'], $this->row('SELECT academic_year_id FROM user_role_assignments WHERE id = ?', [$id])['academic_year_id']);
        $authorization = new AuthorizationRepository($this->pdo);
        self::assertFalse($authorization->hasActiveSchoolRole($f['user'], $f['schoolA']));
        self::assertFalse($authorization->hasSchoolPermission($f['user'], $f['schoolA'], 'SCHOOL_USER_VIEW'));
        self::assertFalse($authorization->hasSchoolPermission($f['user'], $f['schoolA'], 'ACADEMIC_SETUP_VIEW'));
        $this->pdo->prepare('UPDATE user_role_assignments SET academic_year_id = NULL WHERE id = ?')->execute([$id]);
        self::assertTrue($authorization->hasActiveSchoolRole($f['user'], $f['schoolA']));
        self::assertTrue($authorization->hasSchoolPermission($f['user'], $f['schoolA'], 'SCHOOL_USER_VIEW'));
        self::assertTrue($authorization->hasSchoolPermission($f['user'], $f['schoolA'], 'ACADEMIC_SETUP_VIEW'));
    }

    private function fixtures(): array
    {
        foreach (['grade_levels', 'academic_years', 'classrooms', 'subjects', 'subject_offerings'] as $table) {
            $this->assertTableExists($table);
        }
        $schoolA = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['academic-schema-a', 'School A']);
        $schoolB = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['academic-schema-b', 'School B']);
        $grade = $this->insert('INSERT INTO grade_levels (code, name_th, sort_order) VALUES (?, ?, ?)', ['SCHEMA_TEST', 'Test grade', 999]);
        $yearA1 = $this->insert('INSERT INTO academic_years (school_id, year_be) VALUES (?, ?)', [$schoolA, 2569]);
        $yearA2 = $this->insert('INSERT INTO academic_years (school_id, year_be) VALUES (?, ?)', [$schoolA, 2570]);
        $yearB = $this->insert('INSERT INTO academic_years (school_id, year_be) VALUES (?, ?)', [$schoolB, 2569]);
        $classSql = 'INSERT INTO classrooms (school_id, academic_year_id, grade_level_id, code, name_th) VALUES (?, ?, ?, ?, ?)';
        $classA1 = $this->insert($classSql, [$schoolA, $yearA1, $grade, 'P1-1', 'Class A1']);
        $classA2 = $this->insert($classSql, [$schoolA, $yearA2, $grade, 'P1-1', 'Class A2']);
        $classB = $this->insert($classSql, [$schoolB, $yearB, $grade, 'P1-1', 'Class B']);
        $subjectA = $this->insert('INSERT INTO subjects (school_id, code, name_th) VALUES (?, ?, ?)', [$schoolA, 'ท11101', 'ภาษาไทย']);
        $subjectB = $this->insert('INSERT INTO subjects (school_id, code, name_th) VALUES (?, ?, ?)', [$schoolB, 'ท11101', 'ภาษาไทย']);
        $user = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['academic-schema-user', 'unused-test-hash', 'Schema User']);
        $this->insert('INSERT INTO school_memberships (user_id, school_id) VALUES (?, ?)', [$user, $schoolA]);
        $role = $this->row('SELECT id FROM roles WHERE code = ?', ['SCHOOL_ADMIN'])['id'];
        return compact('schoolA', 'schoolB', 'grade', 'yearA1', 'yearA2', 'yearB', 'classA1', 'classA2', 'classB', 'subjectA', 'subjectB', 'user', 'role');
    }

    private function assertTableExists(string $table): void
    {
        self::assertContains($table, $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), "Missing academic table: {$table}");
    }

    private function indexes(string $table): array
    {
        $indexes = [];
        foreach ($this->pdo->query("SHOW INDEX FROM {$table}")->fetchAll() as $row) {
            if ((int) $row['Non_unique'] === 0) {
                $indexes[$row['Key_name']][(int) $row['Seq_in_index']] = $row['Column_name'];
            }
        }
        foreach ($indexes as &$columns) {
            ksort($columns);
            $columns = array_values($columns);
        }
        return $indexes;
    }

    private function assertRejected(string $sql, array $parameters, int $code, string $constraint): void
    {
        try {
            $this->insert($sql, $parameters);
        } catch (PDOException $exception) {
            self::assertSame('23000', $exception->errorInfo[0]);
            self::assertSame($code, $exception->errorInfo[1]);
            self::assertStringContainsString($constraint, $exception->getMessage());
            return;
        }
        self::fail("Expected database rejection by {$constraint}");
    }

    private function row(string $sql, array $parameters): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetch();
    }

    private function insert(string $sql, array $parameters): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return (int) $this->pdo->lastInsertId();
    }
}
