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

final class AcademicIsolationTest extends TestCase
{
    private AcademicIsolationPDO $pdo;
    private Application $app;
    private array $a;
    private array $b;
    private int $wrongYearRoom;
    private int $grade;

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new AcademicIsolationPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->grade = (int) $this->pdo->query("SELECT id FROM grade_levels WHERE code = 'P1'")->fetchColumn();
        self::assertGreaterThan(0, $this->grade);
        $this->a = $this->fixtureSchool('A', 2569);
        $this->b = $this->fixtureSchool('SECRET_B', 2699);
        $nextYear = $this->insert('INSERT INTO academic_years (school_id, year_be) VALUES (?, ?)', [$this->a['school'], 2570]);
        $this->wrongYearRoom = $this->insert('INSERT INTO classrooms (school_id, academic_year_id, grade_level_id, code, name_th) VALUES (?, ?, ?, ?, ?)',
            [$this->a['school'], $nextYear, $this->grade, 'A-NEXT', 'ห้องปีถัดไป']);
        $user = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['academic-isolation-admin', 'unused', 'Academic admin A']);
        $membership = $this->insert('INSERT INTO school_memberships (school_id, user_id) VALUES (?, ?)', [$this->a['school'], $user]);
        $this->pdo->prepare("INSERT INTO user_role_assignments (school_id, user_id, role_id) SELECT ?, ?, id FROM roles WHERE code = 'SCHOOL_ADMIN'")
            ->execute([$this->a['school'], $user]);
        $_SESSION = ['user_id' => $user, 'school_id' => $this->a['school'], 'school_membership_id' => $membership, 'context_type' => 'SCHOOL'];
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

    public function test_year_index_links_reach_all_academic_lists_and_permission_still_gates_entry(): void
    {
        $response = $this->request('GET', '/academic/years');
        self::assertSame(200, $response->status());
        foreach (['classrooms' => 'ห้องเรียน', 'subjects' => 'รายวิชา', 'offerings' => 'การเปิดรายวิชา'] as $resource => $label) {
            self::assertSame(1, substr_count($response->body(), '<a href="/academic/' . $resource . '">' . $label . '</a>'), 'Academic page navigation link missing: ' . $resource);
            self::assertSame(200, $this->request('GET', '/academic/' . $resource)->status());
        }
        $this->pdo->prepare("DELETE rp FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id
            WHERE r.code = 'SCHOOL_ADMIN' AND p.code = 'ACADEMIC_SETUP_VIEW'")->execute();
        foreach (['years', 'classrooms', 'subjects', 'offerings'] as $resource) {
            $denied = $this->request('GET', '/academic/' . $resource);
            self::assertSame(403, $denied->status());
            self::assertStringNotContainsString('href="/academic/', $denied->body());
        }
    }

    #[DataProvider('resources')]
    public function test_lists_ignore_forged_school_and_never_show_foreign_resources(string $resource): void
    {
        $before = $this->snapshot();
        foreach ([$this->a['school'], $this->b['school']] as $school) {
            $response = $this->request('GET', '/academic/' . $resource, ['school_id' => $school], ['school_id' => $school]);
            self::assertSame(200, $response->status());
            self::assertStringContainsString('/academic/' . $resource . '/' . $this->a[$resource] . '/edit', $response->body());
            self::assertStringNotContainsString('/academic/' . $resource . '/' . $this->b[$resource] . '/edit', $response->body());
            $this->assertSafe($response);
            self::assertSame($before, $this->snapshot());
        }
    }

    public static function resources(): array { return [['years'], ['classrooms'], ['subjects'], ['offerings']]; }

    #[DataProvider('targets')]
    public function test_foreign_and_missing_targets_are_identical_after_proven_own_school_success(string $resource, string $method, string $suffix): void
    {
        $payload = $this->payload($resource);
        $this->assertOwnSuccess($method, '/academic/' . $resource . '/' . $this->a[$resource] . $suffix, $payload);
        $before = $this->snapshot();
        foreach ([$this->a['school'], $this->b['school']] as $forgedSchool) {
            $responses = [];
            foreach ([$this->b[$resource], 0] as $target) {
                $response = $this->request($method, '/academic/' . $resource . '/' . $target . $suffix,
                    array_replace($payload, ['school_id' => $forgedSchool]), ['school_id' => $forgedSchool]);
                self::assertSame($method === 'GET' ? 404 : 422, $response->status());
                $this->assertSafe($response);
                self::assertStringNotContainsString('<form', $response->body());
                self::assertSame($before, $this->snapshot());
                $responses[] = $response;
            }
            self::assertEquals($responses[0], $responses[1]);
        }
    }

    public static function targets(): array
    {
        $cases = [];
        foreach (self::resources() as [$resource]) {
            foreach ([['GET', '/edit'], ['POST', ''], ['POST', '/status']] as [$method, $suffix]) {
                $cases[$resource . ' ' . $method . $suffix] = [$resource, $method, $suffix];
            }
        }
        return $cases;
    }

    #[DataProvider('parentSubstitutions')]
    public function test_foreign_or_wrong_year_parent_cannot_mutate_any_resource(string $resource, bool $update, string $field, string $parent): void
    {
        $path = '/academic/' . $resource . ($update ? '/' . $this->a[$resource] : '');
        $payload = $this->payload($resource);
        $this->assertOwnSuccess('POST', $path, $payload);
        $before = $this->snapshot();
        $invalidId = $parent === 'wrongYearRoom' ? $this->wrongYearRoom : $this->b[$parent];
        foreach ([$this->a['school'], $this->b['school']] as $forgedSchool) {
            $responses = [];
            foreach ([$invalidId, 999999999] as $id) {
                $response = $this->request('POST', $path, array_replace($payload, [$field => $id, 'school_id' => $forgedSchool]), ['school_id' => $forgedSchool]);
                self::assertSame(422, $response->status());
                $this->assertSafe($response);
                self::assertSame($before, $this->snapshot());
                $responses[] = $response;
            }
            self::assertEquals($responses[0], $responses[1]);
        }
    }

    public static function parentSubstitutions(): array
    {
        return [
            'A offering update + B subject' => ['offerings', true, 'subject_id', 'subjects'],
            'A offering update + B classroom' => ['offerings', true, 'classroom_id', 'classrooms'],
            'A offering update + A wrong-year classroom' => ['offerings', true, 'classroom_id', 'wrongYearRoom'],
            'A classroom create + B year' => ['classrooms', false, 'academic_year_id', 'years'],
            'A offering create + B year' => ['offerings', false, 'academic_year_id', 'years'],
            'A offering create + B classroom' => ['offerings', false, 'classroom_id', 'classrooms'],
            'A offering create + B subject' => ['offerings', false, 'subject_id', 'subjects'],
            'A offering create + A wrong-year classroom' => ['offerings', false, 'classroom_id', 'wrongYearRoom'],
        ];
    }

    #[DataProvider('yearQueries')]
    public function test_foreign_year_filter_or_create_preselection_is_identical_to_missing(string $path): void
    {
        $own = $this->request('GET', $path, [], ['academic_year_id' => $this->a['years']]);
        self::assertSame(200, $own->status());
        $this->assertSafe($own);
        $before = $this->snapshot();
        foreach ([$this->a['school'], $this->b['school']] as $forgedSchool) {
            $foreign = $this->request('GET', $path, [], ['academic_year_id' => $this->b['years'], 'school_id' => $forgedSchool]);
            $missing = $this->request('GET', $path, [], ['academic_year_id' => 999999999, 'school_id' => $forgedSchool]);
            self::assertSame(404, $foreign->status());
            self::assertEquals($missing, $foreign);
            $this->assertSafe($foreign);
            self::assertSame($before, $this->snapshot());
        }
    }

    public static function yearQueries(): array
    {
        return [['/academic/classrooms'], ['/academic/classrooms/create'], ['/academic/offerings'], ['/academic/offerings/create']];
    }

    /** Prove permissions and valid input reach a successful write, then restore the fixture. */
    private function assertOwnSuccess(string $method, string $path, array $payload): void
    {
        $before = $this->snapshot();
        $this->pdo->beginTransaction();
        try {
            $auditCount = count($this->rows('audit_logs'));
            $response = $this->request($method, $path, $payload);
            self::assertSame($method === 'GET' ? 200 : 302, $response->status(), 'Own-school control must succeed before testing tenant rejection');
            $this->assertSafe($response);
            if ($method === 'POST') {
                self::assertNotSame($before, $this->snapshot());
                self::assertCount($auditCount + 1, $this->rows('audit_logs'));
            }
        } finally {
            $this->pdo->rollBack();
        }
        self::assertSame($before, $this->snapshot());
    }

    private function payload(string $resource): array
    {
        return ['_token' => $_SESSION['csrf_token']] + match ($resource) {
            'years' => ['year_be' => '2571', 'start_date' => '2028-05-16', 'end_date' => '2029-03-31', 'status' => 'ACTIVE'],
            'classrooms' => ['academic_year_id' => $this->a['years'], 'grade_level_id' => $this->grade, 'code' => 'NEW-ROOM', 'name_th' => 'ห้องใหม่', 'status' => 'INACTIVE'],
            'subjects' => ['code' => 'NEW-SUBJECT', 'name_th' => 'วิชาใหม่', 'status' => 'INACTIVE'],
            'offerings' => ['academic_year_id' => $this->a['years'], 'classroom_id' => $this->a['classrooms'], 'subject_id' => $this->a['subjects'], 'term_no' => 2, 'status' => 'INACTIVE'],
        };
    }

    private function fixtureSchool(string $label, int $yearBe): array
    {
        $school = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['academic-isolation-' . $label, $label . '_SCHOOL']);
        $yearCe = $yearBe - 543;
        $year = $this->insert('INSERT INTO academic_years (school_id, year_be, start_date, end_date) VALUES (?, ?, ?, ?)', [$school, $yearBe, $yearCe . '-05-16', ($yearCe + 1) . '-03-31']);
        $room = $this->insert('INSERT INTO classrooms (school_id, academic_year_id, grade_level_id, code, name_th) VALUES (?, ?, ?, ?, ?)', [$school, $year, $this->grade, $label . '_ROOM', $label . '_ROOM_NAME']);
        $subject = $this->insert('INSERT INTO subjects (school_id, code, name_th) VALUES (?, ?, ?)', [$school, $label . '_SUBJECT', $label . '_SUBJECT_NAME']);
        $offering = $this->insert('INSERT INTO subject_offerings (school_id, academic_year_id, classroom_id, subject_id, term_no) VALUES (?, ?, ?, ?, 1)', [$school, $year, $room, $subject]);
        return ['school' => $school, 'years' => $year, 'classrooms' => $room, 'subjects' => $subject, 'offerings' => $offering];
    }

    private function request(string $method, string $path, array $post = [], array $query = []): Response
    {
        return $this->app->handle(new Request($method, $path, $query, $post, []));
    }

    private function insert(string $sql, array $params): int
    {
        $this->pdo->prepare($sql)->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    private function rows(string $table): array
    {
        return $this->pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll();
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['academic_years', 'classrooms', 'subjects', 'subject_offerings', 'audit_logs'] as $table) {
            $snapshot[$table] = $this->rows($table);
        }
        return $snapshot;
    }

    private function assertSafe(Response $response): void
    {
        foreach (['SECRET_B', '>2699<', '2156-05-16', '2157-03-31', 'SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', '/Applications/MAMP/', 'htdocs/app/'] as $secret) {
            self::assertStringNotContainsString($secret, $response->body());
        }
    }
}

/** Real service commits are contained within each test's rollback. */
final class AcademicIsolationPDO extends PDO
{
    private int $depth = 0;

    public function beginTransaction(): bool
    {
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT academic_isolation_' . $this->depth) !== false;
        ++$this->depth;
        return $ok;
    }

    public function commit(): bool
    {
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT academic_isolation_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $ok;
    }

    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else {
            $ok = $this->exec('ROLLBACK TO SAVEPOINT academic_isolation_' . ($this->depth - 1)) !== false;
            $this->exec('RELEASE SAVEPOINT academic_isolation_' . ($this->depth - 1));
        }
        --$this->depth;
        return $ok;
    }
}
