<?php
declare(strict_types=1);

use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TeachingGradebookSchemaTest extends TestCase
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
            $type = preg_replace('/\b(bigint|int|smallint)\(\d+\)/', '$1', $column['Type']);
            $default = $column['Default'];
            if (is_string($default)) {
                $default = $default === 'NULL' ? null : trim(strtolower($default) === 'current_timestamp()' ? 'CURRENT_TIMESTAMP' : $default, "'");
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
            self::assertSame('RESTRICT', $r['DELETE_RULE']); self::assertSame('RESTRICT', $r['UPDATE_RULE']);
        }
    }

    public static function schemas(): array
    {
        $id = ['bigint unsigned', 'NO', null];
        $time = ['datetime', 'NO', 'CURRENT_TIMESTAMP'];
        $identity = ['id' => $id, 'school_id' => $id, 'academic_year_id' => $id];
        $timestamps = ['created_at' => $time, 'updated_at' => $time];
        return [
            'permission scopes' => ['permission_scopes', [...$identity, 'user_role_assignment_id' => $id,
                'subject_offering_id' => $id, 'status' => ['varchar(20)', 'NO', 'ACTIVE'], 'assigned_by' => $id, ...$timestamps]],
            'components' => ['gradebook_components', [...$identity, 'subject_offering_id' => $id,
                'code' => ['varchar(50)', 'NO', null], 'name_th' => ['varchar(190)', 'NO', null],
                'max_score' => ['decimal(7,2)', 'NO', null], 'sort_order' => ['smallint unsigned', 'NO', null],
                'status' => ['varchar(20)', 'NO', 'ACTIVE'], ...$timestamps]],
            'scores' => ['gradebook_scores', [...$identity, 'subject_offering_id' => $id, 'enrollment_id' => $id,
                'component_id' => $id, 'score' => ['decimal(7,2)', 'YES', null], 'updated_by' => $id, ...$timestamps]],
        ];
    }

    public static function keys(): array
    {
        return [
            'role assignment parent' => ['user_role_assignments', [['id'], ['id', 'school_id']], []],
            'offering parent' => ['subject_offerings', [['id'], ['school_id', 'academic_year_id', 'classroom_id', 'subject_id', 'term_no'],
                ['id', 'school_id', 'academic_year_id']], []],
            'enrollment parent' => ['student_enrollments', [['id'], ['school_id', 'academic_year_id', 'student_id'],
                ['id', 'school_id', 'academic_year_id', 'grade_level_id'], ['id', 'school_id', 'academic_year_id']], []],
            'scope pair' => ['permission_scopes', [['id'], ['user_role_assignment_id', 'subject_offering_id']], []],
            'component identity' => ['gradebook_components', [['id'], ['school_id', 'subject_offering_id', 'code'],
                ['id', 'school_id', 'academic_year_id', 'subject_offering_id']], []],
            'score cell' => ['gradebook_scores', [['id'], ['subject_offering_id', 'enrollment_id', 'component_id']], []],
        ];
    }

    public static function foreignKeys(): array
    {
        return [
            ['permission_scopes', 'fk_permission_scope_assignment_school', ['user_role_assignment_id', 'school_id'], 'user_role_assignments', ['id', 'school_id']],
            ['permission_scopes', 'fk_permission_scope_offering_school_year', ['subject_offering_id', 'school_id', 'academic_year_id'], 'subject_offerings', ['id', 'school_id', 'academic_year_id']],
            ['permission_scopes', 'fk_permission_scope_assigner', ['assigned_by'], 'users', ['id']],
            ['gradebook_components', 'fk_gradebook_component_offering', ['subject_offering_id', 'school_id', 'academic_year_id'], 'subject_offerings', ['id', 'school_id', 'academic_year_id']],
            ['gradebook_scores', 'fk_gradebook_score_component', ['component_id', 'school_id', 'academic_year_id', 'subject_offering_id'], 'gradebook_components', ['id', 'school_id', 'academic_year_id', 'subject_offering_id']],
            ['gradebook_scores', 'fk_gradebook_score_enrollment', ['enrollment_id', 'school_id', 'academic_year_id'], 'student_enrollments', ['id', 'school_id', 'academic_year_id']],
            ['gradebook_scores', 'fk_gradebook_score_updater', ['updated_by'], 'users', ['id']],
        ];
    }

    public function test_role_permission_scope_metadata_is_nullable_and_defaults_to_unscoped(): void
    {
        $columns = $this->pdo->query('SHOW FULL COLUMNS FROM role_permissions')->fetchAll(PDO::FETCH_UNIQUE);
        self::assertArrayHasKey('resource_scope_type', $columns);
        $column = $columns['resource_scope_type'];
        self::assertSame('varchar(30)', $column['Type']);
        self::assertSame('YES', $column['Null']);
        self::assertContains($column['Default'], [null, 'NULL']);
        $role = $this->pdo->query("SELECT id FROM roles WHERE code='VIEWER'")->fetchColumn();
        $permission = $this->pdo->query("SELECT id FROM permissions WHERE code='SCHOOL_USER_VIEW'")->fetchColumn();
        $this->insert('role_permissions', ['role_id' => $role, 'permission_id' => $permission]);
        $q = $this->pdo->prepare('SELECT resource_scope_type FROM role_permissions WHERE role_id=? AND permission_id=?');
        $q->execute([$role, $permission]);
        self::assertNull($q->fetchColumn());
    }

    public function test_migration_chain_records_the_new_file_once(): void
    {
        self::assertFileExists(dirname(__DIR__, 2) . '/database/migrations/20260915_001_teaching_gradebook_core.sql');
        self::assertSame([
            '20260908_001_foundation_identity.sql', '20260908_002_foundation_authorization.sql',
            '20260908_003_foundation_audit.sql', '20260910_001_academic_structure.sql',
            '20260912_001_student_core_enrollment.sql', '20260915_001_teaching_gradebook_core.sql',
        ], $this->pdo->query('SELECT migration FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN));
    }

    #[DataProvider('invalidRelationships')]
    public function test_invalid_parent_insert_and_update_are_rejected(string $table, array $changes, string $constraint): void
    {
        $f = $this->fixtures();
        $valid = $f['values'][$table];
        $invalid = $valid;
        foreach ($changes as $column => $fixture) {
            $invalid[$column] = $fixture === 'missing' ? 0 : $f[$fixture];
        }
        $this->reject($table, $invalid, 1452, $constraint);
        $id = $this->insert($table, $valid);
        $before = $this->row($table, $id);
        $assignments = implode(', ', array_map(static fn (string $c): string => $c . '=?', array_keys($invalid)));
        try {
            $this->pdo->prepare("UPDATE {$table} SET {$assignments} WHERE id=?")->execute([...array_values($invalid), $id]);
        } catch (PDOException $e) {
            self::assertSame('23000', $e->errorInfo[0]);
            self::assertSame(1452, $e->errorInfo[1]);
            self::assertStringContainsString($constraint, $e->getMessage());
            self::assertSame($before, $this->row($table, $id));
            return;
        }
        self::fail('Expected update rejection by ' . $constraint);
    }

    public static function invalidRelationships(): array
    {
        $cases = [];
        foreach (['assignmentB', 'assignmentSystem', 'missing'] as $fixture) {
            $cases['scope assignment ' . $fixture] = ['permission_scopes', ['user_role_assignment_id' => $fixture], 'fk_permission_scope_assignment_school'];
        }
        foreach (['offeringB', 'offeringNext', 'missing'] as $fixture) {
            $cases['scope offering ' . $fixture] = ['permission_scopes', ['subject_offering_id' => $fixture], 'fk_permission_scope_offering_school_year'];
            $cases['component offering ' . $fixture] = ['gradebook_components', ['subject_offering_id' => $fixture], 'fk_gradebook_component_offering'];
        }
        foreach (['yearB', 'yearNext', 'missing'] as $fixture) {
            $cases['scope year ' . $fixture] = ['permission_scopes', ['academic_year_id' => $fixture], 'fk_permission_scope_offering_school_year'];
            $cases['component year ' . $fixture] = ['gradebook_components', ['academic_year_id' => $fixture], 'fk_gradebook_component_offering'];
            $cases['score year ' . $fixture] = ['gradebook_scores', ['academic_year_id' => $fixture], 'fk_gradebook_score_component'];
        }
        $cases['scope cross school'] = ['permission_scopes', ['school_id' => 'schoolB', 'user_role_assignment_id' => 'assignmentB'], 'fk_permission_scope_offering_school_year'];
        $cases['component cross school'] = ['gradebook_components', ['school_id' => 'schoolB'], 'fk_gradebook_component_offering'];
        $cases['score cross school'] = ['gradebook_scores', ['school_id' => 'schoolB'], 'fk_gradebook_score_component'];
        foreach (['componentB', 'componentNext', 'componentOther', 'missing'] as $fixture) {
            $cases['score component ' . $fixture] = ['gradebook_scores', ['component_id' => $fixture], 'fk_gradebook_score_component'];
        }
        foreach (['offeringB', 'offeringNext', 'offeringOther', 'missing'] as $fixture) {
            $cases['score offering ' . $fixture] = ['gradebook_scores', ['subject_offering_id' => $fixture], 'fk_gradebook_score_component'];
        }
        foreach (['enrollmentB', 'enrollmentNext', 'missing'] as $fixture) {
            $cases['score enrollment ' . $fixture] = ['gradebook_scores', ['enrollment_id' => $fixture], 'fk_gradebook_score_enrollment'];
        }
        $cases['missing assigner'] = ['permission_scopes', ['assigned_by' => 'missing'], 'fk_permission_scope_assigner'];
        $cases['missing updater'] = ['gradebook_scores', ['updated_by' => 'missing'], 'fk_gradebook_score_updater'];
        return $cases;
    }

    #[DataProvider('duplicates')]
    public function test_duplicate_identities_are_rejected(string $table, string $constraint, array $change): void
    {
        $f = $this->fixtures();
        $values = $f['values'][$table];
        $this->insert($table, $values);
        $this->reject($table, array_replace($values, $change), 1062, $constraint);
    }

    public static function duplicates(): array
    {
        return [
            'scope active' => ['permission_scopes', 'uq_permission_scope_assignment_offering', []],
            'scope inactive' => ['permission_scopes', 'uq_permission_scope_assignment_offering', ['status' => 'INACTIVE']],
            'component active' => ['gradebook_components', 'uq_gradebook_component_code', []],
            'component inactive' => ['gradebook_components', 'uq_gradebook_component_code', ['status' => 'INACTIVE']],
            'score null' => ['gradebook_scores', 'uq_gradebook_score_cell', []],
            'score zero' => ['gradebook_scores', 'uq_gradebook_score_cell', ['score' => '0.00']],
        ];
    }

    #[DataProvider('equivalentCodes')]
    public function test_component_code_uniqueness_matches_database_collation(string $first, string $second): void
    {
        $f = $this->fixtures();
        $q = $this->pdo->prepare('SELECT CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci');
        $q->execute([$first, $second]);
        self::assertSame(1, (int) $q->fetchColumn());
        $values = $f['values']['gradebook_components'];
        $this->insert('gradebook_components', array_replace($values, ['code' => $first]));
        $this->reject('gradebook_components', array_replace($values, ['code' => $second]), 1062, 'uq_gradebook_component_code');
        $other = $this->insert('gradebook_components', array_replace($values, ['code' => $second, 'subject_offering_id' => $f['offeringOther']]));
        self::assertSame($second, $this->row('gradebook_components', $other)['code']);
    }

    public static function equivalentCodes(): array
    {
        return [
            'case' => ['CODE', 'code'], 'accent' => ['eleve', 'élève'],
            'Unicode composition' => ['é1', "e\u{0301}1"], 'collation expansion' => ['STRASSE', 'Straße'],
        ];
    }

    public function test_null_zero_and_decimal_scores_persist_distinctly_and_can_be_cleared(): void
    {
        $f = $this->fixtures();
        $values = $f['values']['gradebook_scores'];
        $nullId = $this->insert('gradebook_scores', $values);
        self::assertNull($this->row('gradebook_scores', $nullId)['score']);
        $secondComponent = $this->insert('gradebook_components', $f['values']['gradebook_components']);
        $zeroId = $this->insert('gradebook_scores', array_replace($values, ['component_id' => $secondComponent, 'score' => '0.00']));
        self::assertSame('0.00', $this->row('gradebook_scores', $zeroId)['score']);
        self::assertNull($this->row('gradebook_scores', $nullId)['score']);
        $q = $this->pdo->prepare('UPDATE gradebook_scores SET score=? WHERE id=?');
        $q->execute(['12.34', $nullId]);
        self::assertSame('12.34', $this->row('gradebook_scores', $nullId)['score']);
        $q->execute([null, $zeroId]);
        self::assertNull($this->row('gradebook_scores', $zeroId)['score']);
        // Omitting a score also means not entered, never DEFAULT 0.
        unset($values['score']);
        $values['enrollment_id'] = $f['enrollmentSecond'];
        $omittedId = $this->insert('gradebook_scores', $values);
        self::assertNull($this->row('gradebook_scores', $omittedId)['score']);
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM gradebook_scores')->fetchColumn());
    }

    public function test_valid_scope_pairs_components_and_unplaced_enrollments_are_supported(): void
    {
        $f = $this->fixtures();
        $scope = $this->insert('permission_scopes', $f['values']['permission_scopes']);
        self::assertSame('ACTIVE', $this->row('permission_scopes', $scope)['status']);
        $this->pdo->prepare("UPDATE permission_scopes SET status='INACTIVE' WHERE id=?")->execute([$scope]);
        self::assertSame('INACTIVE', $this->row('permission_scopes', $scope)['status']);
        $this->insert('permission_scopes', array_replace($f['values']['permission_scopes'], ['subject_offering_id' => $f['offeringOther']]));
        $this->insert('permission_scopes', array_replace($f['values']['permission_scopes'], ['user_role_assignment_id' => $f['assignmentSecond']]));
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM permission_scopes')->fetchColumn());
        foreach (['A', 'B', 'Next', 'Other'] as $key) {
            $row = $this->row('gradebook_components', $f['component' . $key]);
            self::assertSame('ACTIVE', $row['status']);
            self::assertSame('100.00', $row['max_score']);
            self::assertNotNull($row['created_at']);
            self::assertSame($row['created_at'], $row['updated_at']);
            $this->insert('gradebook_scores', ['school_id' => $row['school_id'], 'academic_year_id' => $row['academic_year_id'],
                'subject_offering_id' => $row['subject_offering_id'], 'component_id' => $row['id'],
                'enrollment_id' => $f['enrollment' . ($key === 'Other' ? 'A' : $key)], 'updated_by' => $f['user']]);
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM student_classroom_placements')->fetchColumn());
        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM gradebook_scores')->fetchColumn());
    }

    #[DataProvider('retainedParents')]
    public function test_referenced_parents_cannot_be_deleted(string $table, string $fixture, string $constraint): void
    {
        $f = $this->fixtures();
        $this->insert('permission_scopes', $f['values']['permission_scopes']);
        $this->insert('gradebook_scores', $f['values']['gradebook_scores']);
        $before = $this->pdo->query('SELECT * FROM gradebook_scores ORDER BY id')->fetchAll();
        try {
            $this->pdo->prepare("DELETE FROM {$table} WHERE id=?")->execute([$f[$fixture]]);
        } catch (PDOException $e) {
            self::assertSame('23000', $e->errorInfo[0]);
            self::assertSame(1451, $e->errorInfo[1]);
            self::assertStringContainsString($constraint, $e->getMessage());
            self::assertSame($before, $this->pdo->query('SELECT * FROM gradebook_scores ORDER BY id')->fetchAll());
            self::assertSame($f[$fixture], $this->row($table, $f[$fixture])['id']);
            return;
        }
        self::fail('Expected retained parent ' . $table);
    }

    public static function retainedParents(): array
    {
        return [
            ['user_role_assignments', 'assignmentA', 'fk_permission_scope_assignment_school'],
            ['gradebook_components', 'componentA', 'fk_gradebook_score_component'],
            ['student_enrollments', 'enrollmentA', 'fk_gradebook_score_enrollment'],
            ['subject_offerings', 'offeringOther', 'fk_gradebook_component_offering'],
            ['users', 'user', 'fk_gradebook_score_updater'],
        ];
    }

    private function fixtures(): array
    {
        foreach (['permission_scopes', 'gradebook_components', 'gradebook_scores'] as $table) {
            $this->tableExists($table);
        }
        $f = [];
        $f['user'] = $this->insert('users', ['username' => 'm5-schema-actor', 'password_hash' => 'unused', 'display_name' => 'Schema actor']);
        $teacher = $this->insert('users', ['username' => 'm5-schema-teacher', 'password_hash' => 'unused', 'display_name' => 'Schema teacher']);
        $role = $this->pdo->query("SELECT id FROM roles WHERE code='SUBJECT_TEACHER'")->fetchColumn();
        $grade = $this->pdo->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        foreach (['A', 'B'] as $key) {
            $school = $f['school' . $key] = $this->insert('schools', ['school_code' => 'm5-schema-' . $key, 'name_th' => 'โรงเรียนทดสอบ']);
            $f['student' . $key] = $this->insert('students', ['school_id' => $school, 'student_code' => 'fixture',
                'prefix_th' => 'ด.ช.', 'first_name_th' => 'ทดสอบ', 'last_name_th' => 'สมมติ']);
            $f['subject' . $key] = $this->insert('subjects', ['school_id' => $school, 'code' => 'ท11101', 'name_th' => 'ภาษาไทย']);
            $this->insert('school_memberships', ['school_id' => $school, 'user_id' => $teacher]);
            $f['assignment' . $key] = $this->insert('user_role_assignments', ['school_id' => $school, 'user_id' => $teacher, 'role_id' => $role]);
        }
        $f['assignmentSecond'] = $this->insert('user_role_assignments', ['school_id' => $f['schoolA'], 'user_id' => $teacher, 'role_id' => $role]);
        $systemRole = $this->pdo->query("SELECT id FROM roles WHERE code='SYSTEM_ADMIN'")->fetchColumn();
        $f['assignmentSystem'] = $this->insert('user_role_assignments', ['user_id' => $teacher, 'role_id' => $systemRole]);
        foreach (['A', 'B', 'Next'] as $key) {
            $schoolKey = $key === 'B' ? 'B' : 'A';
            $school = $f['school' . $schoolKey];
            $year = $f['year' . $key] = $this->insert('academic_years', ['school_id' => $school, 'year_be' => $key === 'Next' ? 2570 : 2569]);
            $room = $this->insert('classrooms', ['school_id' => $school, 'academic_year_id' => $year, 'grade_level_id' => $grade, 'code' => 'P1', 'name_th' => 'ห้องทดสอบ']);
            $offeringValues = ['school_id' => $school, 'academic_year_id' => $year, 'classroom_id' => $room, 'subject_id' => $f['subject' . $schoolKey], 'term_no' => 1];
            $offering = $f['offering' . $key] = $this->insert('subject_offerings', $offeringValues);
            $f['enrollment' . $key] = $this->insert('student_enrollments', ['school_id' => $school, 'academic_year_id' => $year, 'grade_level_id' => $grade, 'student_id' => $f['student' . $schoolKey]]);
            $componentValues = ['school_id' => $school, 'academic_year_id' => $year, 'subject_offering_id' => $offering,
                'code' => 'EXISTING', 'name_th' => 'คะแนนทดสอบ', 'max_score' => '100.00', 'sort_order' => 10];
            $f['component' . $key] = $this->insert('gradebook_components', $componentValues);
            if ($key === 'A') {
                $f['offeringOther'] = $this->insert('subject_offerings', array_replace($offeringValues, ['term_no' => 2]));
                $f['componentOther'] = $this->insert('gradebook_components', array_replace($componentValues, ['subject_offering_id' => $f['offeringOther']]));
            }
        }
        $student = $this->insert('students', ['school_id' => $f['schoolA'], 'student_code' => 'second',
            'prefix_th' => 'ด.ญ.', 'first_name_th' => 'ทดสอบ', 'last_name_th' => 'สมมติ']);
        $f['enrollmentSecond'] = $this->insert('student_enrollments', ['school_id' => $f['schoolA'], 'academic_year_id' => $f['yearA'], 'grade_level_id' => $grade, 'student_id' => $student]);
        $identity = ['school_id' => $f['schoolA'], 'academic_year_id' => $f['yearA'], 'subject_offering_id' => $f['offeringA']];
        $f['values'] = [
            'permission_scopes' => $identity + ['user_role_assignment_id' => $f['assignmentA'], 'assigned_by' => $f['user']],
            'gradebook_components' => $identity + ['code' => 'NEW', 'name_th' => 'คะแนนใหม่', 'max_score' => '20.00', 'sort_order' => 20],
            'gradebook_scores' => $identity + ['enrollment_id' => $f['enrollmentA'], 'component_id' => $f['componentA'], 'score' => null, 'updated_by' => $f['user']],
        ];
        return $f;
    }

    private function tableExists(string $table): void
    {
        self::assertContains($table, $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), 'Missing Milestone 5 table: ' . $table);
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
