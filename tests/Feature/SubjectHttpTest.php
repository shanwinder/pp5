<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Support\Csrf;
use App\Support\Database;
use FastRoute\Dispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function FastRoute\simpleDispatcher;

final class SubjectHttpTest extends TestCase
{
    private SubjectHttpPDO $pdo;
    private Application $app;
    private int $school;
    private int $foreignSchool;
    private int $subject;
    private int $foreignSubject;
    private array $users;
    private const HOSTILE = '<b>"XSS"</b>';

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new SubjectHttpPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->school = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['subject-http-a', 'School A']);
        $this->foreignSchool = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['subject-http-b', 'Foreign School Secret']);
        $this->subject = $this->fixture($this->school, 'OWN');
        $this->foreignSubject = $this->fixture($this->foreignSchool, 'FOREIGN_SECRET');
        foreach (['SCHOOL_ADMIN', 'ACADEMIC_ADMIN', 'VIEWER', 'SYSTEM_ADMIN'] as $role) {
            $id = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['subject-http-' . $role, 'unused', $role]);
            $school = $role === 'SYSTEM_ADMIN' ? null : $this->school;
            if ($school !== null) { $this->insert('INSERT INTO school_memberships (school_id, user_id) VALUES (?, ?)', [$school, $id]); }
            $this->insert('INSERT INTO user_role_assignments (school_id, user_id, role_id) SELECT ?, ?, id FROM roles WHERE code = ?', [$school, $id, $role]);
            $this->users[$role] = $id;
        }
        $this->app = new Application($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->failPrepare = null;
            while ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
        }
        $_SESSION = [];
    }

    public static function routes(): array
    {
        return [['GET', '/academic/subjects', 'ACADEMIC_SETUP_VIEW'], ['GET', '/academic/subjects/create', 'SUBJECT_MANAGE'],
            ['POST', '/academic/subjects', 'SUBJECT_MANAGE'], ['GET', '/academic/subjects/{id}/edit', 'SUBJECT_MANAGE'],
            ['POST', '/academic/subjects/{id}', 'SUBJECT_MANAGE'], ['POST', '/academic/subjects/{id}/status', 'SUBJECT_MANAGE']];
    }

    #[DataProvider('routes')]
    public function test_exact_routes_permissions_context_and_numeric_ids(string $method, string $path, string $permission): void
    {
        $dispatcher = simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php');
        $route = $dispatcher->dispatch($method, $this->path($path));
        self::assertSame(Dispatcher::FOUND, $route[0], 'Task 5 subject route missing');
        self::assertTrue($route[1]['protected']);
        self::assertSame('SCHOOL', $route[1]['context']);
        self::assertSame($permission, $route[1]['permission']);
        self::assertArrayNotHasKey('school_id', $route[2]);
        if (str_contains($path, '{id}')) {
            self::assertSame((string) $this->subject, $route[2]['id']);
            self::assertSame(Dispatcher::NOT_FOUND, $dispatcher->dispatch($method, str_replace('{id}', 'abc', $path))[0]);
        }
    }

    #[DataProvider('routes')]
    public function test_guest_redirect_and_system_denial_precede_permission_csrf_and_writes(string $method, string $path, string $permission): void
    {
        $before = $this->snapshot();
        self::assertEquals(Response::redirect('/login'), $this->request($method, $this->path($path)));
        $this->login('SYSTEM_ADMIN');
        $this->grant('SYSTEM_ADMIN', $permission);
        $this->pdo->failPrepare = 'FROM user_role_assignments ura';
        self::assertSame(403, $this->request($method, $this->path($path), $this->payload())->status());
        self::assertFalse($this->pdo->failureTriggered);
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('routes')]
    public function test_school_and_academic_admin_require_exact_permission_mapping(string $method, string $path, string $permission): void
    {
        foreach (['SCHOOL_ADMIN', 'ACADEMIC_ADMIN'] as $role) {
            $this->login($role);
            $this->pdo->beginTransaction();
            self::assertSame($method === 'GET' ? 200 : 302, $this->request($method, $this->path($path), $this->payload())->status());
            $this->pdo->rollBack();
            $this->pdo->prepare('DELETE rp FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.code = ? AND p.code = ?')->execute([$role, $permission]);
            $before = $this->snapshot();
            self::assertSame(403, $this->request($method, $this->path($path), $this->payload())->status());
            self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('routes')]
    public function test_viewer_denied_until_direct_exact_permission_mapping(string $method, string $path, string $permission): void
    {
        $this->login('VIEWER');
        $before = $this->snapshot();
        self::assertSame(403, $this->request($method, $this->path($path), $this->payload())->status());
        self::assertSame($before, $this->snapshot());
        $this->grant('VIEWER', $permission);
        self::assertSame($method === 'GET' ? 200 : 302, $this->request($method, $this->path($path), $this->payload())->status());
    }

    #[DataProvider('routes')]
    public function test_tampered_session_school_cannot_be_restored_by_browser_input(string $method, string $path, string $permission): void
    {
        $this->login();
        $_SESSION['school_id'] = $this->foreignSchool;
        $before = $this->snapshot();
        self::assertSame(403, $this->request($method, $this->path($path), $this->payload(['school_id' => $this->school]), ['school_id' => $this->school])->status());
        self::assertSame($before, $this->snapshot());
    }

    public function test_list_contains_only_own_subjects_in_code_order_including_inactive(): void
    {
        $this->login();
        $a = $this->fixture($this->school, 'AAA', 'INACTIVE');
        $response = $this->request('GET', '/academic/subjects', [], ['school_id' => $this->foreignSchool]);
        self::assertSame(200, $response->status());
        self::assertSame(['AAA', 'OWN'], array_map(static fn (DOMNode $n): string => trim($n->textContent), iterator_to_array($this->xpath($response->body())->query('//tbody/tr/td[1]'))));
        foreach (['ชื่อรายวิชา', 'INACTIVE', '/academic/subjects/' . $a . '/edit'] as $value) { self::assertStringContainsString($value, $response->body()); }
        $this->assertSafe($response);
    }

    public function test_create_form_has_exact_fields_and_current_csrf_token(): void
    {
        $this->login();
        $response = $this->request('GET', '/academic/subjects/create');
        self::assertSame(200, $response->status());
        $names = $this->values($this->xpath($response->body())->query('//form//*[@name]/@name'));
        self::assertEqualsCanonicalizing(['code', 'name_th', '_token'], $names);
        $this->assertForms($response);
    }

    #[DataProvider('subjectStatuses')]
    public function test_edit_has_editable_business_fields_and_exact_toggle_only(string $state): void
    {
        $this->login();
        $this->pdo->prepare('UPDATE subjects SET status = ? WHERE id = ?')->execute([$state, $this->subject]);
        $response = $this->request('GET', $this->path('/academic/subjects/{id}/edit'));
        self::assertSame(200, $response->status());
        $xpath = $this->xpath($response->body());
        foreach (['code', 'name_th'] as $field) { self::assertSame(1, $xpath->query('//input[@name="' . $field . '" and not(@readonly) and not(@disabled)]')->length); }
        self::assertSame($state === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE', $xpath->evaluate('string(//input[@name="status"]/@value)'));
        self::assertSame(0, $xpath->query('//select')->length);
        self::assertSame(2, $xpath->query('//form')->length);
        foreach (['OWN', 'ชื่อรายวิชา', $state] as $value) { self::assertStringContainsString($value, $response->body()); }
        $this->assertForms($response);
    }

    public static function subjectStatuses(): array { return [['ACTIVE'], ['INACTIVE']]; }
    public static function mutations(): array { return [['/academic/subjects'], ['/academic/subjects/{id}'], ['/academic/subjects/{id}/status']]; }

    #[DataProvider('mutations')]
    public function test_every_post_checks_csrf_before_malicious_input_and_makes_no_writes(string $path): void
    {
        $this->login();
        $before = $this->snapshot();
        foreach ([null, 'bad', ['bad'], new stdClass()] as $token) {
            $this->pdo->writeAttempts = 0;
            $response = $this->request('POST', $this->path($path), $this->payload(['_token' => $token, 'code' => new stdClass(), 'name_th' => ['bad'], 'status' => ['bad']]));
            self::assertSame(419, $response->status());
            self::assertSame('CSRF token mismatch', $response->body());
            self::assertSame(0, $this->pdo->writeAttempts);
            self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('mutations')]
    public function test_mutations_use_session_context_and_valid_remote_addr_only(string $path): void
    {
        $this->login();
        $foreignBefore = $this->row('SELECT * FROM subjects WHERE id = ?', [$this->foreignSubject]);
        $forged = ['school_id' => $this->foreignSchool, 'user_id' => $this->users['SYSTEM_ADMIN'], 'actor' => $this->users['SYSTEM_ADMIN'],
            'actor_user_id' => $this->users['SYSTEM_ADMIN'], 'created_by' => $this->users['SYSTEM_ADMIN'], 'academic_year_id' => ['bad'],
            'classroom_id' => new stdClass(), 'term_no' => 2, 'teacher_id' => 1, 'ip_address' => '203.0.113.1', 'REMOTE_ADDR' => '203.0.113.2'];
        $response = $this->request('POST', $this->path($path), $this->payload($forged), $forged, ['REMOTE_ADDR' => '192.0.2.51', 'HTTP_X_FORWARDED_FOR' => '203.0.113.3']);
        self::assertEquals(Response::redirect($path === '/academic/subjects' ? '/academic/subjects' : $this->path('/academic/subjects/{id}/edit')), $response);
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame([$this->school, $this->users['SCHOOL_ADMIN'], 'subjects', '192.0.2.51', null],
            array_map(static fn (string $k): mixed => $audit[$k], ['school_id', 'user_id', 'entity_type', 'ip_address', 'reason']));
        self::assertSame(match ($path) { '/academic/subjects' => 'SUBJECT_CREATED', '/academic/subjects/{id}' => 'SUBJECT_UPDATED', default => 'SUBJECT_STATUS_CHANGED' }, $audit['action']);
        $row = $this->row('SELECT * FROM subjects WHERE id = ?', [$audit['entity_id']]);
        if ($path !== '/academic/subjects') { self::assertSame($this->subject, $audit['entity_id']); }
        self::assertSame($this->school, $row['school_id']);
        self::assertSame($path === '/academic/subjects/{id}/status' ? 'INACTIVE' : 'ACTIVE', $row['status']);
        self::assertSame($foreignBefore, $this->row('SELECT * FROM subjects WHERE id = ?', [$this->foreignSubject]));
        self::assertArrayNotHasKey('academic_year_id', $row);
    }

    #[DataProvider('badInputs')]
    public function test_malformed_types_and_domain_values_return_safe_422_for_store_update(string $field, mixed $value): void
    {
        $this->login();
        $before = $this->snapshot();
        foreach (['/academic/subjects', $this->path('/academic/subjects/{id}')] as $path) {
            $response = $this->request('POST', $path, $this->payload([$field => $value]));
            self::assertSame(422, $response->status());
            $this->assertSafe($response);
            self::assertSame($before, $this->snapshot());
        }
    }

    public static function badInputs(): array
    {
        $cases = [];
        foreach (['code' => 50, 'name_th' => 190] as $field => $max) {
            foreach ([['bad'], new stdClass(), true, 1, 1.5, null, '', "\u{00A0} ", str_repeat('ก', $max + 1), "\xFF", "bad\0", "bad\t", "bad\n", "bad\r", "bad\x7F", "bad\u{0085}"] as $i => $value) { $cases[$field . $i] = [$field, $value]; }
        }
        return $cases;
    }

    public function test_thai_create_without_year_and_duplicate_create_update_are_safe(): void
    {
        $this->login();
        self::assertSame([], $this->rows('SELECT id FROM academic_years WHERE school_id = ?', [$this->school]));
        self::assertEquals(Response::redirect('/academic/subjects'), $this->request('POST', '/academic/subjects', $this->payload(['code' => ' ค11101 ', 'name_th' => ' ภาษาไทย '])));
        $created = $this->row('SELECT * FROM subjects WHERE school_id = ? AND code = ?', [$this->school, 'ค11101']);
        self::assertSame('ภาษาไทย', $created['name_th']);
        self::assertSame('ACTIVE', $created['status']);
        $before = $this->snapshot();
        foreach (['/academic/subjects', $this->path('/academic/subjects/{id}')] as $path) {
            $response = $this->request('POST', $path, $this->payload(['code' => 'ค11101']));
            self::assertSame(422, $response->status());
            self::assertStringContainsString('รหัสรายวิชานี้มีอยู่แล้วในโรงเรียน', $response->body());
            $this->assertSafe($response);
            self::assertSame($before, $this->snapshot());
        }
    }

    public function test_foreign_missing_overflow_targets_are_identical_without_disclosure_or_writes(): void
    {
        $this->login();
        $before = $this->snapshot();
        $foreign = $this->request('GET', '/academic/subjects/' . $this->foreignSubject . '/edit', [], ['school_id' => $this->foreignSchool]);
        self::assertSame(404, $foreign->status());
        self::assertEquals($this->request('GET', '/academic/subjects/0/edit'), $foreign);
        $this->assertSafe($foreign);
        foreach (['/academic/subjects/{id}', '/academic/subjects/{id}/status'] as $path) {
            $responses = [];
            foreach ([(string) $this->foreignSubject, '0', '999999999999999999999999'] as $id) {
                $response = $this->request('POST', str_replace('{id}', $id, $path), $this->payload(['school_id' => $this->foreignSchool]), ['school_id' => $this->foreignSchool]);
                self::assertSame(422, $response->status());
                $this->assertSafe($response);
                self::assertSame(0, $this->xpath($response->body())->query('//form')->length);
                self::assertSame($before, $this->snapshot());
                $responses[] = $response;
            }
            self::assertEquals($responses[0], $responses[1]);
            self::assertEquals($responses[0], $responses[2]);
        }
    }

    public function test_status_toggles_and_normalized_noops_preserve_audit_and_data(): void
    {
        $this->login();
        $path = $this->path('/academic/subjects/{id}/status');
        foreach (['INACTIVE', 'ACTIVE'] as $status) {
            self::assertEquals(Response::redirect($this->path('/academic/subjects/{id}/edit')), $this->request('POST', $path, $this->payload(['status' => $status])));
            self::assertSame($status, $this->row('SELECT status FROM subjects WHERE id = ?', [$this->subject])['status']);
            $before = $this->snapshot();
            $this->pdo->writeAttempts = 0;
            self::assertSame(302, $this->request('POST', $path, $this->payload(['status' => $status]))->status());
            self::assertSame(0, $this->pdo->writeAttempts);
            self::assertSame($before, $this->snapshot());
        }
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        self::assertSame(302, $this->request('POST', $this->path('/academic/subjects/{id}'), $this->payload(['code' => "\u{00A0}OWN ", 'name_th' => "\u{2003}ชื่อรายวิชา\u{3000}"]))->status());
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
        self::assertCount(2, $this->rows('SELECT * FROM audit_logs'));
    }

    #[DataProvider('invalidStatuses')]
    public function test_invalid_status_type_or_value_is_safe_422(mixed $status): void
    {
        $this->login();
        $before = $this->snapshot();
        $response = $this->request('POST', $this->path('/academic/subjects/{id}/status'), $this->payload(['status' => $status]));
        self::assertSame(422, $response->status());
        $this->assertSafe($response);
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidStatuses(): array
    {
        return [['UNKNOWN'], ['CLOSED'], ['DRAFT'], ['SUSPENDED'], ['active'], ['inactive'], [''], [['ACTIVE']], [new stdClass()], [true], [1], [1.5], [null]];
    }

    public function test_inactive_suspended_school_cannot_mutate_over_http(): void
    {
        $this->login();
        foreach (['INACTIVE', 'SUSPENDED'] as $state) {
            $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?')->execute([$state, $this->school]);
            $before = $this->snapshot();
            $this->pdo->writeAttempts = 0;
            foreach (self::mutations() as [$path]) { self::assertSame(403, $this->request('POST', $this->path($path), $this->payload())->status()); }
            self::assertSame(0, $this->pdo->writeAttempts);
            self::assertSame($before, $this->snapshot());
        }
    }

    public function test_hostile_stored_and_redisplayed_values_and_csrf_are_escaped(): void
    {
        $this->login();
        $_SESSION['csrf_token'] = '" onfocus="alert(1)';
        $this->pdo->prepare('UPDATE subjects SET code = ?, name_th = ? WHERE id = ?')->execute([self::HOSTILE, self::HOSTILE, $this->subject]);
        foreach (['/academic/subjects', $this->path('/academic/subjects/{id}/edit')] as $path) {
            $response = $this->request('GET', $path);
            self::assertSame(200, $response->status());
            self::assertStringContainsString(htmlspecialchars(self::HOSTILE, ENT_QUOTES, 'UTF-8'), $response->body());
            self::assertStringNotContainsString(self::HOSTILE, $response->body());
            self::assertSame(0, $this->xpath($response->body())->query('//*[@onfocus]')->length);
        }
        $response = $this->request('POST', '/academic/subjects', $this->payload(['code' => self::HOSTILE, 'name_th' => self::HOSTILE]));
        self::assertSame(422, $response->status());
        self::assertStringContainsString(htmlspecialchars(self::HOSTILE, ENT_QUOTES, 'UTF-8'), $response->body());
        self::assertStringNotContainsString(self::HOSTILE, $response->body());
        self::assertStringContainsString('&quot; onfocus=&quot;alert(1)', $response->body());
        self::assertSame(0, $this->xpath($response->body())->query('//*[@onfocus]')->length);
        $this->assertForms($response);
    }

    #[DataProvider('mutations')]
    public function test_write_audit_failure_is_safe_422_with_atomic_rollback(string $path): void
    {
        $this->login();
        foreach (['INSERT INTO audit_logs', $path === '/academic/subjects' ? 'INSERT INTO subjects' : 'UPDATE subjects'] as $failure) {
            $before = $this->snapshot();
            $this->pdo->failPrepare = $failure;
            $this->pdo->failureTriggered = false;
            $response = $this->request('POST', $this->path($path), $this->payload());
            self::assertSame(422, $response->status());
            self::assertTrue($this->pdo->failureTriggered);
            $this->assertSafe($response);
            self::assertSame(1, $this->pdo->depth);
            self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('ipAddresses')]
    public function test_only_valid_remote_addr_is_used_for_audit(mixed $address, ?string $expected): void
    {
        $this->login();
        self::assertSame(302, $this->request('POST', '/academic/subjects', $this->payload(['ip_address' => '203.0.113.1']), ['ip_address' => '203.0.113.2'], ['REMOTE_ADDR' => $address, 'HTTP_X_FORWARDED_FOR' => '203.0.113.3'])->status());
        self::assertSame($expected, $this->row('SELECT ip_address FROM audit_logs')['ip_address']);
    }
    public static function ipAddresses(): array { return [['192.0.2.52', '192.0.2.52'], ['2001:db8::1', '2001:db8::1'], [null, null], ['invalid', null], [['bad'], null], [new stdClass(), null]]; }

    public function test_no_system_or_hard_delete_routes_exist(): void
    {
        $this->login();
        $before = $this->snapshot();
        self::assertSame(405, $this->request('DELETE', $this->path('/academic/subjects/{id}'))->status());
        self::assertSame(404, $this->request('POST', $this->path('/academic/subjects/{id}/delete'))->status());
        self::assertSame(404, $this->request('GET', '/system/subjects')->status());
        self::assertSame($before, $this->snapshot());
    }

    private function login(string $role = 'SCHOOL_ADMIN'): void
    {
        $user = $this->users[$role];
        $_SESSION = ['user_id' => $user, 'context_type' => $role === 'SYSTEM_ADMIN' ? 'SYSTEM' : 'SCHOOL'];
        if ($role !== 'SYSTEM_ADMIN') {
            $_SESSION['school_id'] = $this->school;
            $_SESSION['school_membership_id'] = $this->row('SELECT id FROM school_memberships WHERE school_id = ? AND user_id = ?', [$this->school, $user])['id'];
        }
        $this->token();
    }
    private function token(): string { return (new Csrf())->token(new Session()); }
    private function payload(array $overrides = []): array
    {
        return array_replace(['_token' => $this->token(), 'code' => 'NEW', 'name_th' => 'วิทยาศาสตร์', 'status' => 'INACTIVE'], $overrides);
    }
    private function path(string $path): string { return str_replace('{id}', (string) $this->subject, $path); }
    private function request(string $method, string $path, array $post = [], array $query = [], array $server = []): Response
    {
        return $this->app->handle(new Request($method, $path, $query, $post, $server));
    }
    private function fixture(int $school, string $code, string $status = 'ACTIVE'): int
    {
        return $this->insert('INSERT INTO subjects (school_id, code, name_th, status) VALUES (?, ?, ?, ?)', [$school, $code, 'ชื่อรายวิชา', $status]);
    }
    private function grant(string $role, string $permission): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = ? AND p.code = ?')->execute([$role, $permission]);
    }
    private function snapshot(): array { return [$this->rows('SELECT * FROM subjects ORDER BY id'), $this->rows('SELECT * FROM audit_logs ORDER BY id')]; }
    private function row(string $sql, array $params = []): array { $rows = $this->rows($sql, $params); self::assertCount(1, $rows); return $rows[0]; }
    private function rows(string $sql, array $params = []): array { $q = $this->pdo->prepare($sql); $q->execute($params); return $q->fetchAll(); }
    private function insert(string $sql, array $params): int { $this->pdo->prepare($sql)->execute($params); return (int) $this->pdo->lastInsertId(); }
    private function values(DOMNodeList $nodes): array { return array_map(static fn (DOMNode $n): string => $n->nodeValue, iterator_to_array($nodes)); }
    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML('<?xml encoding="UTF-8">' . $html); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        return new DOMXPath($document);
    }
    private function assertForms(Response $response): void
    {
        $xpath = $this->xpath($response->body());
        foreach (['school_id', 'academic_year_id', 'classroom_id', 'term_no', 'teacher_id', 'user_id', 'actor', 'actor_user_id'] as $field) { self::assertSame(0, $xpath->query('//*[@name="' . $field . '"]')->length); }
        self::assertSame(0, $xpath->query('//form[contains(@action,"delete")]')->length);
        foreach ($xpath->query('//form[@method="post"]') as $form) { self::assertSame($this->token(), $xpath->evaluate('string(.//input[@name="_token"]/@value)', $form)); }
    }
    private function assertSafe(Response $response): void
    {
        foreach (['SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', '/Applications/MAMP/', 'htdocs/app/', 'uq_subject', 'fk_subject', 'private-db-details', 'FOREIGN_SECRET', 'Foreign School Secret'] as $secret) { self::assertStringNotContainsString($secret, $response->body()); }
    }
}

/** Service commits stay inside the HTTP fixture rollback. */
final class SubjectHttpPDO extends PDO
{
    public int $depth = 0;
    public int $writeAttempts = 0;
    public ?string $failPrepare = null;
    public bool $failureTriggered = false;
    public function beginTransaction(): bool
    {
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT subject_http_' . $this->depth) !== false;
        ++$this->depth;
        return $ok;
    }
    public function commit(): bool
    {
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT subject_http_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else { $ok = $this->exec('ROLLBACK TO SAVEPOINT subject_http_' . ($this->depth - 1)) !== false; $this->exec('RELEASE SAVEPOINT subject_http_' . ($this->depth - 1)); }
        --$this->depth;
        return $ok;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $query)) { ++$this->writeAttempts; }
        if ($this->failPrepare !== null && str_contains($query, $this->failPrepare)) {
            $this->failPrepare = null; $this->failureTriggered = true;
            throw new PDOException('SQLSTATE private-db-details SELECT /Applications/MAMP/htdocs/app/secret.php');
        }
        return parent::prepare($query, $options);
    }
}
