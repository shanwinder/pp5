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

final class SystemAdminHttpTest extends TestCase
{
    private SystemAdminHttpTestPDO $pdo;
    private Application $app;
    private int $systemId;
    private int $schoolUserId;
    private int $schoolId;
    private int $otherSchoolId;
    private int $membershipId;
    private static ?string $fixtureHash = null;
    private const PASSWORD = 'http-fixture-password';
    private const ADMIN_PASSWORD = 'new-school-secret-password';

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new SystemAdminHttpTestPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        self::$fixtureHash ??= password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $this->systemId = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['http-system', self::$fixtureHash, 'System Admin']);
        $this->schoolUserId = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['http-school-admin', self::$fixtureHash, 'School Admin']);
        $this->schoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['http-school-a', 'โรงเรียน A']);
        $this->otherSchoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['http-school-b', 'โรงเรียน B']);
        $this->membershipId = $this->insert('INSERT INTO school_memberships (user_id, school_id) VALUES (?, ?)', [$this->schoolUserId, $this->schoolId]);
        $this->insert("INSERT INTO user_role_assignments (user_id, role_id) SELECT ?, id FROM roles WHERE code = 'SYSTEM_ADMIN'", [$this->systemId]);
        $this->insert("INSERT INTO user_role_assignments (user_id, school_id, role_id) SELECT ?, ?, id FROM roles WHERE code = 'SCHOOL_ADMIN'", [$this->schoolUserId, $this->schoolId]);
        $this->app = new Application($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            while ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
        $_SESSION = [];
    }

    public function test_system_login_redirects_to_a_reachable_school_list_without_school_context(): void
    {
        $response = $this->request('POST', '/login', ['_token' => $this->token(), 'username' => 'http-system', 'password' => self::PASSWORD]);
        self::assertEquals(Response::redirect('/system/schools'), $response);
        self::assertSame('SYSTEM', $_SESSION['context_type']);
        self::assertArrayNotHasKey('school_id', $_SESSION);
        self::assertArrayNotHasKey('school_membership_id', $_SESSION);
        $response = $this->request('GET', '/system/schools');
        self::assertSame(200, $response->status());
        self::assertStringContainsString('http-school-a', $response->body());
        self::assertStringContainsString('โรงเรียน A', $response->body());
        self::assertStringContainsString('ACTIVE', $response->body());
        self::assertSame(403, $this->request('GET', '/dashboard')->status());
    }

    #[DataProvider('systemRoutes')]
    public function test_every_system_route_requires_authenticated_identity(string $method, string $path, string $permission): void
    {
        $before = $this->snapshot();
        self::assertEquals(Response::redirect('/login'), $this->request($method, $this->path($path), $this->createPayload()));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('systemRoutes')]
    public function test_school_context_cannot_enter_any_system_route_even_with_a_system_permission_mapping(string $method, string $path, string $permission): void
    {
        $this->schoolSession();
        // Route context remains a boundary even if a SCHOOL role is misconfigured with SYSTEM permission.
        $this->pdo->prepare("INSERT INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'SCHOOL_ADMIN' AND p.code = ?")->execute([$permission]);
        $before = $this->snapshot();
        $response = $this->request($method, $this->path($path), $this->createPayload(['status' => 'SUSPENDED']));
        self::assertSame(403, $response->status());
        self::assertStringContainsString('ไม่มีสิทธิ์เข้าใช้งาน', $response->body());
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('systemRoutes')]
    public function test_each_system_route_checks_its_required_permission(string $method, string $path, string $permission): void
    {
        $this->systemSession();
        $this->pdo->prepare("DELETE rp FROM role_permissions rp JOIN roles r ON r.id = rp.role_id
            JOIN permissions p ON p.id = rp.permission_id WHERE r.code = 'SYSTEM_ADMIN' AND p.code = ?")->execute([$permission]);
        $before = $this->snapshot();
        $response = $this->request($method, $this->path($path), $this->createPayload(['status' => 'SUSPENDED']));
        self::assertSame(403, $response->status());
        self::assertSame($before, $this->snapshot());
    }

    public static function systemRoutes(): array
    {
        return [
            'list' => ['GET', '/system/schools', 'SYSTEM_SCHOOL_VIEW'],
            'create form' => ['GET', '/system/schools/create', 'SYSTEM_SCHOOL_CREATE'],
            'store' => ['POST', '/system/schools', 'SYSTEM_SCHOOL_CREATE'],
            'status' => ['POST', '/system/schools/{id}/status', 'SYSTEM_SCHOOL_STATUS_MANAGE'],
        ];
    }

    public function test_inactive_system_user_is_denied_before_handler(): void
    {
        $this->systemSession();
        $this->pdo->prepare("UPDATE users SET status = 'INACTIVE' WHERE id = ?")->execute([$this->systemId]);
        self::assertSame(403, $this->request('GET', '/system/schools')->status());
    }

    public function test_create_form_has_expected_fields_csrf_and_no_password_value_or_authorization_chooser(): void
    {
        $this->systemSession();
        $response = $this->request('GET', '/system/schools/create');
        self::assertSame(200, $response->status());
        $xpath = $this->xpath($response->body());
        foreach (['school_code', 'name_th', 'admin_username', 'admin_display_name', 'admin_email', 'admin_password'] as $field) {
            self::assertSame(1, $xpath->query('//input[@name="' . $field . '"]')->length);
        }
        self::assertSame($this->token(), $xpath->evaluate('string(//form[@action="/system/schools"]//input[@name="_token"]/@value)'));
        self::assertSame(1, $xpath->query('//input[@name="admin_password" and @type="password"]')->length);
        self::assertSame(0, $xpath->query('//input[@name="admin_password"]/@value')->length);
        self::assertSame(0, $xpath->query('//*[@name="school_id" or @name="context_type"]')->length);
        self::assertSame(0, $xpath->query('//select')->length);
        self::assertStringNotContainsString(self::ADMIN_PASSWORD, $response->body());
    }

    public function test_list_status_forms_include_csrf_and_route_targets_for_each_school(): void
    {
        $this->systemSession();
        $response = $this->request('GET', '/system/schools');
        self::assertSame(200, $response->status());
        $xpath = $this->xpath($response->body());
        foreach ([$this->schoolId, $this->otherSchoolId] as $schoolId) {
            $forms = $xpath->query('//form[@method="post" and @action="/system/schools/' . $schoolId . '/status"]');
            self::assertGreaterThanOrEqual(1, $forms->length);
            foreach ($forms as $form) {
                self::assertSame($this->token(), $xpath->evaluate('string(.//input[@name="_token"]/@value)', $form));
                self::assertGreaterThanOrEqual(1, $xpath->query('.//*[@name="status"]', $form)->length);
            }
        }
    }

    #[DataProvider('invalidCsrf')]
    public function test_invalid_csrf_cannot_mutate_create_or_status(string $path, mixed $token): void
    {
        $this->systemSession();
        $before = $this->snapshot();
        $session = $_SESSION;
        $response = $this->request('POST', $this->path($path), $this->createPayload(['_token' => $token, 'status' => 'SUSPENDED']));
        self::assertSame(419, $response->status());
        self::assertSame($before, $this->snapshot());
        self::assertSame($session, $_SESSION);
    }

    public static function invalidCsrf(): array
    {
        return [
            'create bad' => ['/system/schools', 'bad'], 'create missing' => ['/system/schools', null], 'create malformed' => ['/system/schools', ['bad']],
            'status bad' => ['/system/schools/{id}/status', 'bad'], 'status missing' => ['/system/schools/{id}/status', null], 'status malformed' => ['/system/schools/{id}/status', ['bad']],
        ];
    }

    public function test_create_school_http_flow_uses_session_actor_and_remote_addr_and_new_admin_can_login(): void
    {
        $this->systemSession();
        $session = $_SESSION;
        $forged = ['school_id' => $this->otherSchoolId, 'context_type' => 'SCHOOL', 'user_id' => $this->schoolUserId,
            'actor_user_id' => $this->schoolUserId, 'ip_address' => '203.0.113.7', 'REMOTE_ADDR' => '203.0.113.8'];
        $response = $this->request('POST', '/system/schools', $this->createPayload($forged), $forged, [
            'REMOTE_ADDR' => '192.0.2.5', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9', 'HTTP_FORWARDED' => 'for=203.0.113.10',
        ]);
        self::assertEquals(Response::redirect('/system/schools'), $response);
        self::assertSame($session, $_SESSION);
        self::assertSame(1, $this->pdo->depth);
        $school = $this->row("SELECT * FROM schools WHERE school_code = 'http-new-school'");
        $user = $this->row("SELECT * FROM users WHERE username = 'http-new-admin'");
        self::assertTrue(password_verify(self::ADMIN_PASSWORD, $user['password_hash']));
        self::assertSame('ACTIVE', $school['status']);
        $membership = $this->row('SELECT * FROM school_memberships WHERE user_id = ?', [$user['id']]);
        self::assertSame($school['id'], $membership['school_id']);
        self::assertSame('ACTIVE', $membership['status']);
        $assignment = $this->row('SELECT ura.*, r.code FROM user_role_assignments ura JOIN roles r ON r.id = ura.role_id WHERE ura.user_id = ?', [$user['id']]);
        self::assertSame('SCHOOL_ADMIN', $assignment['code']);
        self::assertSame('ACTIVE', $assignment['status']);
        self::assertNull($assignment['academic_year_id']);
        $audits = $this->rows('SELECT * FROM audit_logs ORDER BY id');
        self::assertSame(['SCHOOL_CREATED', 'SCHOOL_ADMIN_CREATED'], array_column($audits, 'action'));
        foreach ($audits as $audit) {
            self::assertSame($this->systemId, $audit['user_id']);
            self::assertSame('192.0.2.5', $audit['ip_address']);
            self::assertSame($school['id'], $audit['school_id']);
        }
        self::assertStringNotContainsString(self::ADMIN_PASSWORD, json_encode($audits));
        self::assertStringNotContainsString($user['password_hash'], json_encode($audits));
        $_SESSION = [];
        self::assertEquals(Response::redirect('/dashboard'), $this->request('POST', '/login', [
            '_token' => $this->token(), 'username' => 'http-new-admin', 'password' => self::ADMIN_PASSWORD,
        ]));
        self::assertSame('SCHOOL', $_SESSION['context_type']);
        self::assertSame($school['id'], $_SESSION['school_id']);
        self::assertSame($membership['id'], $_SESSION['school_membership_id']);
        self::assertSame(200, $this->request('GET', '/dashboard')->status());
        self::assertSame(403, $this->request('GET', '/system/schools')->status());
    }

    #[DataProvider('remoteAddresses')]
    public function test_audit_ip_uses_only_valid_remote_addr(mixed $remote, ?string $expected): void
    {
        $this->systemSession();
        $response = $this->request('POST', $this->path('/system/schools/{id}/status'), [
            '_token' => $this->token(), 'status' => 'SUSPENDED', 'ip_address' => '203.0.113.1', 'REMOTE_ADDR' => '203.0.113.2',
        ], ['ip_address' => '203.0.113.3', 'REMOTE_ADDR' => '203.0.113.4'], [
            'REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_FOR' => '203.0.113.5', 'HTTP_FORWARDED' => 'for=203.0.113.6',
        ]);
        self::assertEquals(Response::redirect('/system/schools'), $response);
        self::assertSame($expected, $this->row('SELECT ip_address FROM audit_logs')['ip_address']);
    }

    public static function remoteAddresses(): array
    {
        return ['IPv4' => ['192.0.2.1', '192.0.2.1'], 'IPv6' => ['2001:db8::1', '2001:db8::1'],
            'missing' => [null, null], 'malformed' => [['203.0.113.5'], null], 'invalid' => ['invalid-ip', null]];
    }

    #[DataProvider('statusTransitions')]
    public function test_status_http_flow_uses_route_target_and_preserves_system_context(string $oldStatus, string $newStatus): void
    {
        $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?')->execute([$oldStatus, $this->schoolId]);
        $this->systemSession();
        $session = $_SESSION;
        $response = $this->request('POST', $this->path('/system/schools/{id}/status'), [
            '_token' => $this->token(), 'status' => $newStatus, 'school_id' => $this->otherSchoolId,
            'context_type' => 'SCHOOL', 'actor_user_id' => $this->schoolUserId,
        ], ['school_id' => $this->otherSchoolId, 'context_type' => 'SCHOOL'], ['REMOTE_ADDR' => '192.0.2.10']);
        self::assertEquals(Response::redirect('/system/schools'), $response);
        self::assertSame($session, $_SESSION);
        self::assertSame($newStatus, $this->row('SELECT status FROM schools WHERE id = ?', [$this->schoolId])['status']);
        self::assertSame('ACTIVE', $this->row('SELECT status FROM schools WHERE id = ?', [$this->otherSchoolId])['status']);
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame('SCHOOL_STATUS_CHANGED', $audit['action']);
        self::assertSame($this->schoolId, $audit['school_id']);
        self::assertSame($this->schoolId, $audit['entity_id']);
        self::assertSame($this->systemId, $audit['user_id']);
        self::assertSame(['status' => $oldStatus], json_decode($audit['old_value'], true));
        self::assertSame(['status' => $newStatus], json_decode($audit['new_value'], true));
        self::assertSame(1, $this->pdo->depth);
    }

    public static function statusTransitions(): array
    {
        return ['suspend' => ['ACTIVE', 'SUSPENDED'], 'reactivate' => ['SUSPENDED', 'ACTIVE'],
            'deactivate' => ['ACTIVE', 'INACTIVE'], 'reactivate inactive' => ['INACTIVE', 'ACTIVE']];
    }

    public function test_same_status_post_is_a_no_op_without_audit(): void
    {
        $this->systemSession();
        $before = $this->snapshot();
        self::assertEquals(Response::redirect('/system/schools'), $this->request('POST', $this->path('/system/schools/{id}/status'), ['_token' => $this->token(), 'status' => 'ACTIVE']));
        self::assertSame($before, $this->snapshot());
    }

    public function test_school_dashboard_rechecks_school_after_system_suspends_and_reactivates_it(): void
    {
        $this->schoolSession();
        self::assertSame(200, $this->request('GET', '/dashboard')->status());
        $schoolSession = $_SESSION;
        $this->systemSession();
        self::assertEquals(Response::redirect('/system/schools'), $this->request('POST', $this->path('/system/schools/{id}/status'), ['_token' => $this->token(), 'status' => 'SUSPENDED']));
        $_SESSION = $schoolSession;
        self::assertSame(403, $this->request('GET', '/dashboard')->status());
        $this->systemSession();
        self::assertEquals(Response::redirect('/system/schools'), $this->request('POST', $this->path('/system/schools/{id}/status'), ['_token' => $this->token(), 'status' => 'ACTIVE']));
        $_SESSION = $schoolSession;
        self::assertSame(200, $this->request('GET', '/dashboard')->status());
        $_SESSION['school_id'] = $this->otherSchoolId;
        self::assertSame(403, $this->request('GET', '/dashboard')->status());
    }

    #[DataProvider('invalidStatusTargets')]
    public function test_invalid_target_or_status_returns_friendly_422_without_mutation(string $target, mixed $status): void
    {
        $this->systemSession();
        $before = $this->snapshot();
        $response = $this->request('POST', '/system/schools/' . ($target === 'existing' ? $this->schoolId : $target) . '/status', ['_token' => $this->token(), 'status' => $status]);
        self::assertSame(422, $response->status());
        $this->assertSafeError($response);
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidStatusTargets(): array
    {
        return ['missing school' => ['0', 'ACTIVE'], 'overflow id' => [str_repeat('9', 30), 'ACTIVE'],
            'invalid status' => ['existing', 'DELETED'], 'missing status' => ['existing', null], 'malformed status' => ['existing', ['ACTIVE']]];
    }

    public function test_status_validation_error_does_not_disclose_school_list_without_view_permission(): void
    {
        $this->systemSession();
        $this->pdo->prepare("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE p.code = 'SYSTEM_SCHOOL_VIEW'")->execute();
        self::assertSame(403, $this->request('GET', '/system/schools')->status());

        $response = $this->request('POST', $this->path('/system/schools/{id}/status'), ['_token' => $this->token(), 'status' => 'DELETED']);

        self::assertSame(422, $response->status());
        self::assertStringNotContainsString('http-school-a', $response->body());
        self::assertStringNotContainsString('โรงเรียน A', $response->body());
        self::assertStringNotContainsString('http-school-b', $response->body());
    }

    #[DataProvider('invalidCreateInputs')]
    public function test_invalid_create_is_friendly_and_never_echoes_password(string $field, mixed $value): void
    {
        $this->systemSession();
        $before = $this->snapshot();
        $response = $this->request('POST', '/system/schools', $this->createPayload([$field => $value]));
        self::assertSame(422, $response->status());
        $this->assertSafeError($response);
        self::assertStringNotContainsString(self::ADMIN_PASSWORD, $response->body());
        self::assertDoesNotMatchRegularExpression('/<input[^>]*type="password"[^>]*value=/i', $response->body());
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidCreateInputs(): array
    {
        return ['duplicate school' => ['school_code', 'http-school-a'], 'duplicate username' => ['admin_username', 'http-system'],
            'short password' => ['admin_password', 'shortsecret'], 'array code' => ['school_code', ['bad']],
            'array school name' => ['name_th', ['bad']], 'array username' => ['admin_username', ['bad']],
            'array display name' => ['admin_display_name', ['bad']], 'array email' => ['admin_email', ['bad']],
            'array password' => ['admin_password', ['bad']]];
    }

    public function test_dynamic_list_and_form_values_are_escaped(): void
    {
        $this->systemSession();
        $_SESSION['display_name'] = '<script>system-name</script>';
        $_SESSION['csrf_token'] = '" onfocus="alert(1)';
        $this->pdo->prepare('UPDATE schools SET school_code = ?, name_th = ?, status = ? WHERE id = ?')->execute([
            '<b>school</b>', '<img src=x onerror="alert(1)">', '<b>status</b>', $this->schoolId,
        ]);
        $list = $this->request('GET', '/system/schools');
        self::assertSame(200, $list->status());
        self::assertStringContainsString('&lt;b&gt;school&lt;/b&gt;', $list->body());
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $list->body());
        self::assertStringContainsString('&lt;b&gt;status&lt;/b&gt;', $list->body());
        self::assertStringContainsString('&lt;script&gt;system-name&lt;/script&gt;', $list->body());
        self::assertStringContainsString('&quot; onfocus=&quot;alert(1)', $list->body());
        $form = $this->request('POST', '/system/schools', $this->createPayload(['school_code' => '<script>code</script>', 'name_th' => '<img src=x>']));
        self::assertSame(422, $form->status());
        foreach ([$list, $form] as $response) {
            self::assertStringNotContainsString('<script>', $response->body());
            self::assertStringNotContainsString('<img', $response->body());
            self::assertStringNotContainsString(self::ADMIN_PASSWORD, $response->body());
        }
    }

    public function test_unexpected_database_error_uses_central_generic_500(): void
    {
        $this->systemSession();
        $this->pdo->failSchoolList = true;
        $response = $this->request('GET', '/system/schools');
        self::assertSame(500, $response->status());
        self::assertSame('Internal Server Error', $response->body());
    }

    public function test_request_server_accessor_ignores_post_and_query_and_supports_default(): void
    {
        $request = new Request('POST', '/', ['REMOTE_ADDR' => '203.0.113.1'], ['REMOTE_ADDR' => '203.0.113.2'], ['REMOTE_ADDR' => '192.0.2.1']);
        self::assertTrue(is_callable([$request, 'server']), 'Request server accessor is not implemented yet.');
        self::assertSame('192.0.2.1', $request->server('REMOTE_ADDR'));
        self::assertNull($request->server('missing'));
        self::assertSame('fallback', $request->server('missing', 'fallback'));
    }

    private function systemSession(): void
    {
        $_SESSION = ['user_id' => $this->systemId, 'context_type' => 'SYSTEM', 'display_name' => 'System Admin', 'last_activity' => time()];
        $this->token();
    }

    private function schoolSession(): void
    {
        $_SESSION = ['user_id' => $this->schoolUserId, 'context_type' => 'SCHOOL', 'school_id' => $this->schoolId,
            'school_membership_id' => $this->membershipId, 'display_name' => 'School Admin', 'last_activity' => time()];
        $this->token();
    }

    private function token(): string
    {
        return (new Csrf())->token(new Session());
    }

    private function path(string $path): string
    {
        return str_replace('{id}', (string) $this->schoolId, $path);
    }

    private function createPayload(array $overrides = []): array
    {
        return array_replace(['_token' => $this->token(), 'school_code' => 'http-new-school', 'name_th' => 'โรงเรียนใหม่',
            'admin_username' => 'http-new-admin', 'admin_display_name' => 'ผู้ดูแลใหม่',
            'admin_email' => 'new-admin@example.test', 'admin_password' => self::ADMIN_PASSWORD], $overrides);
    }

    private function request(string $method, string $path, array $post = [], array $query = [], array $server = []): Response
    {
        return $this->app->handle(new Request($method, $path, $query, $post, $server));
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return new DOMXPath($document);
    }

    private function assertSafeError(Response $response): void
    {
        self::assertNotSame('', $response->body());
        foreach (['SQLSTATE', 'Stack trace', 'PDOException', '/Applications/', 'private-db-details', 'password_hash', self::ADMIN_PASSWORD, 'shortsecret'] as $secret) {
            self::assertStringNotContainsString($secret, $response->body());
        }
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['schools', 'users', 'school_memberships', 'user_role_assignments', 'audit_logs', 'roles', 'permissions', 'role_permissions'] as $table) {
            $snapshot[$table] = $this->rows('SELECT * FROM ' . $table . ($table === 'role_permissions' ? ' ORDER BY role_id, permission_id' : ' ORDER BY id'));
        }
        return $snapshot;
    }

    private function row(string $sql, array $parameters = []): array
    {
        $rows = $this->rows($sql, $parameters);
        self::assertCount(1, $rows);
        return $rows[0];
    }

    private function rows(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    private function insert(string $sql, array $parameters): int
    {
        $this->pdo->prepare($sql)->execute($parameters);
        return (int) $this->pdo->lastInsertId();
    }
}

/** Isolate HTTP service commits inside a real MySQL fixture transaction. */
final class SystemAdminHttpTestPDO extends PDO
{
    public int $depth = 0;
    public bool $failSchoolList = false;

    public function beginTransaction(): bool
    {
        $result = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT http_task5_' . $this->depth) !== false;
        ++$this->depth;
        return $result;
    }

    public function commit(): bool
    {
        $result = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT http_task5_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $result;
    }

    public function rollBack(): bool
    {
        if ($this->depth === 1) {
            $result = parent::rollBack();
        } else {
            $result = $this->exec('ROLLBACK TO SAVEPOINT http_task5_' . ($this->depth - 1)) !== false;
            $this->exec('RELEASE SAVEPOINT http_task5_' . ($this->depth - 1));
        }
        --$this->depth;
        return $result;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failSchoolList && $query === 'SELECT id, school_code, name_th, status FROM schools ORDER BY id') {
            throw new PDOException('SQLSTATE private-db-details password_hash http-fixture-password');
        }
        return parent::prepare($query, $options);
    }
}
