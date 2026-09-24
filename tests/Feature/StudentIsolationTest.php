<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Support\Csrf;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StudentIsolationTest extends TestCase
{
    private StudentIsolationPDO $pdo;
    private Application $app;
    private array $a;
    private array $b;
    private int $grade;
    private int $candidate;
    private int $nextRoom;
    private int $wrongYearRoom;
    private int $wrongGradeRoom;
    private const NATIONAL = '9876543210123';
    private const MISSING = 999999999;

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new StudentIsolationPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        // Grade levels are global seeded reference data, not school-owned resources.
        $this->grade = (int) $this->pdo->query("SELECT id FROM grade_levels WHERE code = 'P1'")->fetchColumn();
        $otherGrade = (int) $this->pdo->query("SELECT id FROM grade_levels WHERE code = 'P2'")->fetchColumn();
        self::assertGreaterThan(0, $this->grade);
        self::assertGreaterThan(0, $otherGrade);
        $this->a = $this->fixtureSchool('OWN');
        $this->b = $this->fixtureSchool('FOREIGN_SECRET');
        $this->candidate = $this->student($this->a['school'], 'OWN_CANDIDATE');
        $nextYear = $this->insert('INSERT INTO academic_years (school_id, year_be) VALUES (?, ?)', [$this->a['school'], 2570]);
        $this->nextRoom = $this->room($this->a['school'], $this->a['year'], $this->grade, 'OWN_NEXT');
        $this->wrongYearRoom = $this->room($this->a['school'], $nextYear, $this->grade, 'OWN_WRONG_YEAR');
        $this->wrongGradeRoom = $this->room($this->a['school'], $this->a['year'], $otherGrade, 'OWN_WRONG_GRADE');
        $_SESSION = ['user_id' => $this->a['user'], 'school_id' => $this->a['school'],
            'school_membership_id' => $this->a['membership'], 'context_type' => 'SCHOOL'];
        (new Csrf())->token(new Session());
        $this->app = new Application($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            while ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
        }
        $_SESSION = [];
    }

    #[DataProvider('controls')]
    public function test_valid_own_school_mutation_uses_session_tenant_and_actor_despite_browser_authority(string $operation): void
    {
        $this->assertOwnControl($operation);
    }

    public static function controls(): array { return [['create'], ['move']]; }

    #[DataProvider('parents')]
    public function test_forged_parent_cannot_change_business_or_audit_after_valid_control(string $operation, string $field, string $reference): void
    {
        $this->assertOwnControl($operation);
        $invalid = match ($reference) {
            'wrongYearRoom' => $this->wrongYearRoom,
            'wrongGradeRoom' => $this->wrongGradeRoom,
            default => $this->b[$reference],
        };
        foreach ([$this->a['school'], $this->b['school']] as $school) {
            $responses = [];
            foreach ([$invalid, self::MISSING] as $id) {
                $payload = array_replace($this->operationPayload($operation), $this->browserAuthority($school), [$field => $id]);
                $before = $this->snapshot();
                $response = $this->request('POST', $this->operationPath($operation), $payload, $this->browserAuthority($school));
                self::assertSame(422, $response->status());
                self::assertSame($before, $this->snapshot());
                $this->assertSafe($response);
                $responses[] = $response;
            }
            self::assertEquals($responses[0], $responses[1], 'Foreign/missing parents must not reveal existence');
        }
    }

    public static function parents(): array
    {
        return [
            'create + B student' => ['create', 'student_id', 'student'],
            'create + B academic year' => ['create', 'academic_year_id', 'year'],
            'create + B classroom' => ['create', 'classroom_id', 'room'],
            'create + A classroom wrong year' => ['create', 'classroom_id', 'wrongYearRoom'],
            'create + A classroom wrong grade' => ['create', 'classroom_id', 'wrongGradeRoom'],
            'move + B classroom' => ['move', 'classroom_id', 'room'],
            'move + A classroom wrong year' => ['move', 'classroom_id', 'wrongYearRoom'],
            'move + A classroom wrong grade' => ['move', 'classroom_id', 'wrongGradeRoom'],
        ];
    }

    #[DataProvider('targets')]
    public function test_foreign_student_or_enrollment_url_is_identical_to_missing(string $resource, string $method, string $suffix): void
    {
        $this->assertOwnControl('move');
        $base = $resource === 'student' ? '/students/' : '/academic/enrollments/';
        $own = $this->request('GET', $base . $this->a[$resource] . ($resource === 'student' ? '' : '/edit'));
        self::assertSame(200, $own->status());
        self::assertStringContainsString('OWN_CODE', $own->body());
        $this->assertSafe($own);
        foreach ([$this->a['school'], $this->b['school']] as $school) {
            $responses = [];
            foreach ([$this->b[$resource], self::MISSING] as $id) {
                $payload = array_replace($this->operationPayload('move'), $this->browserAuthority($school), [
                    'student_code' => 'SAFE_UPDATE', 'prefix_th' => 'ด.ช.', 'first_name_th' => 'Safe', 'last_name_th' => 'Update',
                    'status' => $resource === 'student' ? 'INACTIVE' : 'WITHDRAWN', 'exit_date' => '2026-09-01',
                    'academic_year_id' => $this->b['year'], 'student_id' => $this->b['student'],
                ]);
                $before = $this->snapshot();
                $response = $this->request($method, $base . $id . $suffix, $payload, $this->browserAuthority($school));
                self::assertSame($method === 'GET' ? 404 : 422, $response->status());
                self::assertSame($before, $this->snapshot());
                $this->assertSafe($response);
                $document = new DOMDocument();
                @$document->loadHTML($response->body());
                // The shell may contain logout; no resource mutation form may appear.
                self::assertSame(0, (new DOMXPath($document))->query('//form[not(@action="/logout")]')->length);
                $responses[] = $response;
            }
            self::assertEquals($responses[0], $responses[1]);
        }
    }

    public static function targets(): array
    {
        return [
            'student detail/history' => ['student', 'GET', ''],
            'student edit' => ['student', 'GET', '/edit'],
            'student update' => ['student', 'POST', ''],
            'student status' => ['student', 'POST', '/status'],
            'enrollment edit/history' => ['enrollment', 'GET', '/edit'],
            'enrollment placement' => ['enrollment', 'POST', '/placement'],
            'enrollment status' => ['enrollment', 'POST', '/status'],
        ];
    }

    #[DataProvider('filters')]
    public function test_foreign_filter_or_preselection_is_friendly_missing(string $path, string $field, string $reference): void
    {
        $ownQuery = ['academic_year_id' => $this->a['year'], 'classroom_id' => $this->a['room']];
        self::assertSame(200, $this->request('GET', $path, [], $ownQuery)->status());
        foreach ([$this->a['school'], $this->b['school']] as $school) {
            $responses = [];
            foreach ([$this->b[$reference], self::MISSING] as $id) {
                $query = array_replace($ownQuery, $this->browserAuthority($school), [$field => $id]);
                $before = $this->snapshot();
                $response = $this->request('GET', $path, $query, $query);
                self::assertSame(404, $response->status());
                self::assertStringContainsString('ไม่พบหน้า', $response->body());
                self::assertSame($before, $this->snapshot());
                $this->assertSafe($response);
                $responses[] = $response;
            }
            self::assertEquals($responses[0], $responses[1]);
        }
    }

    public static function filters(): array
    {
        return [
            'enrollment list foreign year' => ['/academic/enrollments', 'academic_year_id', 'year'],
            'enrollment list foreign classroom' => ['/academic/enrollments', 'classroom_id', 'room'],
            'enrollment create foreign year' => ['/academic/enrollments/create', 'academic_year_id', 'year'],
        ];
    }

    public function test_lists_history_and_choices_never_expose_foreign_data_with_forged_browser_fields(): void
    {
        foreach ([$this->a['school'], $this->b['school']] as $school) {
            $forged = $this->browserAuthority($school) + ['student_id' => $this->b['student']];
            foreach (['/students', '/students/' . $this->a['student'], '/academic/enrollments',
                '/academic/enrollments/create', '/academic/enrollments/' . $this->a['enrollment'] . '/edit'] as $path) {
                $before = $this->snapshot();
                $response = $this->request('GET', $path, $forged, $forged + ['academic_year_id' => $this->a['year'], 'grade_level_id' => $this->grade]);
                self::assertSame(200, $response->status());
                self::assertStringContainsString('OWN_CODE', $response->body());
                $this->assertSafe($response);
                self::assertSame($before, $this->snapshot());
            }
        }
    }

    public function test_tampered_session_school_cannot_be_repaired_by_browser_fields_or_mutate_foreign_resources(): void
    {
        $_SESSION['school_id'] = $this->b['school'];
        $forged = $this->browserAuthority($this->a['school']);
        foreach ([['GET', '/students/' . $this->b['student']], ['GET', '/academic/enrollments'],
            ['POST', '/academic/enrollments'], ['POST', '/academic/enrollments/' . $this->b['enrollment'] . '/placement'],
            ['POST', '/academic/enrollments/' . $this->b['enrollment'] . '/status'],
            ['POST', '/students/' . $this->b['student'] . '/status']] as [$method, $path]) {
            $before = $this->snapshot();
            $response = $this->request($method, $path, array_replace($this->operationPayload('create'), $forged), $forged);
            self::assertSame(403, $response->status());
            self::assertSame($before, $this->snapshot());
            $this->assertSafe($response);
        }
    }

    public function test_import_batch_reads_apply_and_cancel_are_tenant_scoped_and_non_enumerating(): void
    {
        $session = $_SESSION;
        $_SESSION = ['user_id' => $this->b['user'], 'school_id' => $this->b['school'],
            'school_membership_id' => $this->b['membership'], 'context_type' => 'SCHOOL'];
        (new Csrf())->token(new Session());
        $foreign = $this->importPreview($this->b, ['student_code' => 'IMPORT_B', 'first_name_th' => 'FOREIGN_SECRET_IMPORT']);
        $_SESSION = $session;
        $own = $this->importPreview($this->a);
        self::assertSame(302, $this->request('POST', '/academic/student-import/' . $own . '/apply', ['_token' => $_SESSION['csrf_token']])->status());
        foreach ([$this->a['school'], $this->b['school']] as $school) {
            $forged = $this->browserAuthority($school);
            foreach ([['GET', ''], ['POST', '/apply'], ['POST', '/cancel']] as [$method, $suffix]) {
                $responses = [];
                foreach ([$foreign, self::MISSING] as $id) {
                    $before = $this->importSnapshot();
                    $response = $this->request($method, '/academic/student-import/' . $id . $suffix,
                        ['_token' => $_SESSION['csrf_token']] + $forged, $forged);
                    self::assertSame($method === 'GET' ? 404 : 422, $response->status());
                    self::assertSame($before, $this->importSnapshot());
                    $this->assertSafe($response);
                    $responses[] = $response;
                }
                self::assertEquals($responses[0], $responses[1]);
            }
        }
    }

    public function test_import_foreign_year_is_rejected_and_foreign_classroom_cannot_resolve(): void
    {
        foreach ([$this->a['school'], $this->b['school']] as $school) {
            $responses = [];
            foreach ([$this->b['year'], self::MISSING] as $year) {
                $before = $this->importSnapshot();
                $response = $this->importRequest($this->a, [], $this->browserAuthority($school), $year);
                self::assertSame(422, $response->status()); self::assertSame($before, $this->importSnapshot());
                $this->assertSafe($response); $responses[] = $response;
            }
            self::assertEquals($responses[0], $responses[1]);
            $before = $this->snapshot();
            $batch = $this->importPreview($this->a, ['classroom_code' => 'FOREIGN_SECRET_ROOM'], $this->browserAuthority($school));
            self::assertSame($before, $this->snapshot());
            $rows = (new App\Repositories\StudentImportRowRepository($this->pdo))->listForBatch($this->a['school'], $batch);
            self::assertSame('ERROR', $rows[0]['error_code']);
            $before = $this->importSnapshot();
            $response = $this->request('POST', '/academic/student-import/' . $batch . '/apply', ['_token' => $_SESSION['csrf_token']] + $this->browserAuthority($school));
            self::assertSame(422, $response->status()); self::assertSame($before, $this->importSnapshot()); $this->assertSafe($response);
        }
    }

    public function test_import_matching_never_uses_foreign_student_and_malformed_authority_cannot_override_session(): void
    {
        foreach ([$this->a['school'], $this->b['school'], [], new stdClass()] as $school) {
            $forged = ['school_id' => $school, 'user_id' => [], 'actor_user_id' => new stdClass(), 'role' => ['SCHOOL_ADMIN'], 'context_type' => new stdClass()];
            $before = $this->snapshot();
            $batch = $this->importPreview($this->a, ['student_code' => 'FOREIGN_SECRET_CODE', 'national_id' => self::NATIONAL], $forged);
            self::assertSame($before, $this->snapshot());
            $staged = (new App\Repositories\StudentImportRowRepository($this->pdo))->listForBatch($this->a['school'], $batch)[0];
            self::assertSame(null, $staged['matched_student_id']);
            self::assertSame('CREATE', $staged['student_action']); self::assertSame('CREATE', $staged['enrollment_action']);
            $meta = (new App\Repositories\StudentImportBatchRepository($this->pdo))->findForSchool($this->a['school'], $batch);
            self::assertSame($this->a['school'], $meta['school_id']); self::assertSame($this->a['user'], $meta['created_by']);
        }
    }

    private function importPreview(array $scope, array $row = [], array $forged = []): int
    {
        $before = $this->snapshot();
        $response = $this->importRequest($scope, $row, $forged);
        self::assertSame(302, $response->status()); self::assertSame($before, $this->snapshot());
        $this->assertSafe($response);
        return (int) $this->pdo->query('SELECT MAX(id) FROM student_import_batches')->fetchColumn();
    }

    private function importRequest(array $scope, array $row = [], array $forged = [], ?int $year = null): Response
    {
        $values = array_replace(['student_code' => 'OWN_IMPORT', 'national_id' => '', 'prefix_th' => 'ด.ช.', 'first_name_th' => 'Import',
            'last_name_th' => 'Student', 'gender_code' => '', 'birth_date' => '', 'grade_level_code' => 'P1',
            'classroom_code' => $scope['school'] === $this->a['school'] ? 'OWN_ROOM' : 'FOREIGN_SECRET_ROOM', 'entry_date' => '2026-05-01'], $row);
        $path = tempnam(sys_get_temp_dir(), 'pp5-isolation-import-');
        try {
            $stream = fopen($path, 'w'); fputcsv($stream, array_keys($values), ',', '"', ''); fputcsv($stream, array_values($values), ',', '"', ''); fclose($stream);
            return $this->app->handle(new Request('POST', '/academic/student-import/preview', $forged,
                ['_token' => $_SESSION['csrf_token'], 'academic_year_id' => $year ?? $scope['year']] + $forged, [],
                ['student_file' => ['name' => 'isolation.csv', 'tmp_name' => $path, 'size' => filesize($path), 'error' => UPLOAD_ERR_OK]]));
        } finally { unlink($path); }
    }

    private function importSnapshot(): array
    {
        $snapshot = $this->snapshot();
        foreach (['student_import_batches', 'student_import_rows'] as $table) {
            $snapshot[$table] = $this->pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll();
        }
        return $snapshot;
    }

    /** Real HTTP writes succeed, with a real audit actor, then a savepoint restores every row. */
    private function assertOwnControl(string $operation): void
    {
        $before = $this->snapshot();
        $session = $_SESSION;
        $this->pdo->beginTransaction();
        try {
            $payload = array_replace($this->operationPayload($operation), $this->browserAuthority($this->b['school']));
            if ($operation === 'move') {
                // These are not editable enrollment attributes on the placement route.
                $payload += ['academic_year_id' => $this->b['year'], 'student_id' => $this->b['student']];
            }
            $response = $this->request('POST', $this->operationPath($operation), $payload, $this->browserAuthority($this->b['school']));
            self::assertSame(302, $response->status(), 'Own-school control must really succeed');
            $after = $this->snapshot();
            self::assertSame($session, $_SESSION);
            self::assertSame($before['students'], $after['students']);
            self::assertCount(count($before['student_classroom_placements']) + 1, $after['student_classroom_placements']);
            $placement = $after['student_classroom_placements'][array_key_last($after['student_classroom_placements'])];
            self::assertSame($this->a['school'], $placement['school_id']);
            self::assertSame($this->a['year'], $placement['academic_year_id']);
            self::assertSame($this->grade, $placement['grade_level_id']);
            self::assertSame('ACTIVE', $placement['status']);
            if ($operation === 'create') {
                self::assertCount(count($before['student_enrollments']) + 1, $after['student_enrollments']);
                $enrollment = $after['student_enrollments'][array_key_last($after['student_enrollments'])];
                self::assertSame($this->a['school'], $enrollment['school_id']);
                self::assertSame($this->a['year'], $enrollment['academic_year_id']);
                self::assertSame($this->candidate, $enrollment['student_id']);
                self::assertSame($this->grade, $enrollment['grade_level_id']);
                self::assertSame($enrollment['id'], $placement['enrollment_id']);
                self::assertSame($this->a['room'], $placement['classroom_id']);
            } else {
                self::assertSame($before['student_enrollments'], $after['student_enrollments']);
                self::assertSame($this->a['enrollment'], $placement['enrollment_id']);
                self::assertSame($this->nextRoom, $placement['classroom_id']);
                $old = array_values(array_filter($after['student_classroom_placements'], fn (array $row): bool => $row['id'] === $this->a['placement']))[0];
                self::assertSame('ENDED', $old['status']);
                self::assertNotNull($old['ended_at']);
            }
            $audits = array_slice($after['audit_logs'], count($before['audit_logs']));
            self::assertSame($before['audit_logs'], array_slice($after['audit_logs'], 0, count($before['audit_logs'])));
            self::assertSame($operation === 'create' ? ['STUDENT_ENROLLMENT_CREATED', 'STUDENT_CLASSROOM_PLACEMENT_CHANGED']
                : ['STUDENT_CLASSROOM_PLACEMENT_CHANGED'], array_column($audits, 'action'));
            foreach ($audits as $audit) {
                self::assertSame($this->a['school'], $audit['school_id']);
                self::assertSame($this->a['user'], $audit['user_id']);
                self::assertSame($placement['enrollment_id'], $audit['entity_id']);
                self::assertStringNotContainsString(self::NATIONAL, json_encode($audit, JSON_THROW_ON_ERROR));
            }
            foreach (['students', 'student_enrollments', 'student_classroom_placements'] as $table) {
                $foreign = fn (array $row): bool => $row['school_id'] === $this->b['school'];
                self::assertSame(array_values(array_filter($before[$table], $foreign)), array_values(array_filter($after[$table], $foreign)));
            }
        } finally {
            $this->pdo->rollBack();
        }
        self::assertSame($before, $this->snapshot());
    }

    private function operationPath(string $operation): string
    {
        return $operation === 'create' ? '/academic/enrollments' : '/academic/enrollments/' . $this->a['enrollment'] . '/placement';
    }

    private function operationPayload(string $operation): array
    {
        return ['_token' => $_SESSION['csrf_token']] + ($operation === 'create'
            ? ['academic_year_id' => $this->a['year'], 'student_id' => $this->candidate,
                'grade_level_id' => $this->grade, 'classroom_id' => $this->a['room'], 'entry_date' => '2026-05-01']
            : ['classroom_id' => $this->nextRoom]);
    }

    private function browserAuthority(int $school): array
    {
        return ['school_id' => $school, 'user_id' => $this->b['user'], 'actor_user_id' => $this->b['user'],
            'role' => 'SYSTEM_ADMIN', 'context_type' => 'SYSTEM'];
    }

    private function fixtureSchool(string $label): array
    {
        $school = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['si-' . $label, $label . '_SCHOOL']);
        $year = $this->insert('INSERT INTO academic_years (school_id, year_be, status, start_date, end_date) VALUES (?, ?, ?, ?, ?)',
            [$school, 2569, 'ACTIVE', '2026-05-01', '2027-03-31']);
        $room = $this->room($school, $year, $this->grade, $label . '_ROOM');
        $student = $this->student($school, $label);
        $enrollment = $this->insert('INSERT INTO student_enrollments (school_id, academic_year_id, student_id, grade_level_id, entry_date) VALUES (?, ?, ?, ?, ?)',
            [$school, $year, $student, $this->grade, '2026-05-01']);
        $placement = $this->insert('INSERT INTO student_classroom_placements (school_id, academic_year_id, grade_level_id, enrollment_id, classroom_id) VALUES (?, ?, ?, ?, ?)',
            [$school, $year, $this->grade, $enrollment, $room]);
        $user = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['student-isolation-' . $label, 'unused', $label . '_USER']);
        $membership = $this->insert('INSERT INTO school_memberships (school_id, user_id) VALUES (?, ?)', [$school, $user]);
        $this->insert("INSERT INTO user_role_assignments (school_id, user_id, role_id) SELECT ?, ?, id FROM roles WHERE code = 'SCHOOL_ADMIN'", [$school, $user]);
        return compact('school', 'year', 'room', 'student', 'enrollment', 'placement', 'user', 'membership');
    }

    private function student(int $school, string $label): int
    {
        return $this->insert('INSERT INTO students (school_id, student_code, national_id, prefix_th, first_name_th, last_name_th) VALUES (?, ?, ?, ?, ?, ?)',
            [$school, $label . '_CODE', $label === 'FOREIGN_SECRET' ? self::NATIONAL : null, 'ด.ช.', $label . '_NAME', $label . '_SURNAME']);
    }

    private function room(int $school, int $year, int $grade, string $label): int
    {
        return $this->insert('INSERT INTO classrooms (school_id, academic_year_id, grade_level_id, code, name_th) VALUES (?, ?, ?, ?, ?)',
            [$school, $year, $grade, $label, $label . '_NAME']);
    }

    private function request(string $method, string $path, array $post = [], array $query = []): Response
    {
        return $this->app->handle(new Request($method, $path, $query, $post, []));
    }

    private function insert(string $sql, array $parameters): int
    {
        $this->pdo->prepare($sql)->execute($parameters);
        return (int) $this->pdo->lastInsertId();
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['students', 'student_enrollments', 'student_classroom_placements', 'audit_logs'] as $table) {
            $snapshot[$table] = $this->pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll();
        }
        return $snapshot;
    }

    private function assertSafe(Response $response): void
    {
        foreach (['FOREIGN_SECRET', self::NATIONAL, 'SQLSTATE', 'PDOException',
            'CONSTRAINT', 'fk_student_', 'uq_student', 'Stack trace', '#0 ', '/Applications/', '/Users/', '/var/www/', 'htdocs/app/'] as $secret) {
            self::assertStringNotContainsString(strtolower($secret), strtolower($response->body()));
        }
        // Inspect visible error text without mistaking an HTML <select> for SQL.
        self::assertDoesNotMatchRegularExpression('/\b(?:SELECT\s|INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM)/i',
            html_entity_decode(strip_tags($response->body()), ENT_QUOTES, 'UTF-8'));
    }
}

/** Preserve real PDO/service transaction behavior while containing commits in fixture savepoints. */
final class StudentIsolationPDO extends PDO
{
    private int $depth = 0;

    public function beginTransaction(): bool
    {
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT student_isolation_' . $this->depth) !== false;
        ++$this->depth;
        return $ok;
    }

    public function commit(): bool
    {
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT student_isolation_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $ok;
    }

    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else {
            $ok = $this->exec('ROLLBACK TO SAVEPOINT student_isolation_' . ($this->depth - 1)) !== false;
            $this->exec('RELEASE SAVEPOINT student_isolation_' . ($this->depth - 1));
        }
        --$this->depth;
        return $ok;
    }
}
