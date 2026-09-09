<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchoolIsolationTest extends TestCase
{
    private PDO $pdo;
    private Application $app;
    private int $userId;
    private int $schoolId;
    private int $otherSchoolId;
    private int $membershipId;

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = Database::connect($config);
        $this->pdo->beginTransaction();
        $this->userId = $this->insert(
            'INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)',
            ['isolation-task6-user', password_hash('correct-password', PASSWORD_DEFAULT), 'ครูทดสอบ']
        );
        $this->schoolId = $this->insert(
            'INSERT INTO schools (school_code, name_th) VALUES (?, ?)',
            ['isolation-task6-a', 'โรงเรียน A']
        );
        $this->otherSchoolId = $this->insert(
            'INSERT INTO schools (school_code, name_th) VALUES (?, ?)',
            ['isolation-task6-b', 'โรงเรียน B']
        );
        $this->membershipId = $this->insert(
            'INSERT INTO school_memberships (user_id, school_id) VALUES (?, ?)',
            [$this->userId, $this->schoolId]
        );
        $this->pdo->prepare("INSERT INTO user_role_assignments (user_id, school_id, role_id)
            SELECT ?, ?, id FROM roles WHERE code = 'SCHOOL_ADMIN'")->execute([$this->userId, $this->schoolId]);
        $this->app = new Application($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $_SESSION = [];
    }

    public function test_valid_school_session_can_reach_protected_handler(): void
    {
        $this->login();

        $response = $this->request('GET', '/dashboard');

        self::assertSame(200, $response->status());
        self::assertStringContainsString('โรงเรียน A', $response->body());
    }

    public function test_tampered_school_session_is_forbidden(): void
    {
        $this->login();
        $_SESSION['school_id'] = $this->otherSchoolId;

        self::assertSame(403, $this->request('GET', '/dashboard')->status());
    }

    #[DataProvider('disabledEntities')]
    public function test_status_change_denies_the_next_protected_request(string $entity, string $status): void
    {
        $this->login();
        self::assertSame(200, $this->request('GET', '/dashboard')->status());
        [$sql, $id] = match ($entity) {
            'membership' => ['UPDATE school_memberships SET status = ? WHERE id = ?', $this->membershipId],
            'school' => ['UPDATE schools SET status = ? WHERE id = ?', $this->schoolId],
            'user' => ['UPDATE users SET status = ? WHERE id = ?', $this->userId],
        };
        $this->pdo->prepare($sql)->execute([$status, $id]);

        self::assertSame(403, $this->request('GET', '/dashboard')->status());
    }

    public static function disabledEntities(): array
    {
        return [
            'membership suspended' => ['membership', 'SUSPENDED'],
            'membership inactive' => ['membership', 'INACTIVE'],
            'school suspended' => ['school', 'SUSPENDED'],
            'school inactive' => ['school', 'INACTIVE'],
            'user suspended' => ['user', 'SUSPENDED'],
            'user inactive' => ['user', 'INACTIVE'],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        self::assertEquals(Response::redirect('/login'), $this->request('GET', '/dashboard'));
    }

    #[DataProvider('incompleteSessions')]
    public function test_missing_or_malformed_session_identity_is_denied(string $key, mixed $value): void
    {
        $this->login();
        $_SESSION[$key] = $value;

        self::assertEquals(Response::redirect('/login'), $this->request('GET', '/dashboard'));
    }

    public static function incompleteSessions(): array
    {
        return [
            'missing user' => ['user_id', null],
            'missing school' => ['school_id', null],
            'missing membership' => ['school_membership_id', null],
            'malformed user' => ['user_id', '1'],
            'malformed school' => ['school_id', []],
            'malformed membership' => ['school_membership_id', '1'],
        ];
    }

    #[DataProvider('invalidCsrfRequests')]
    public function test_invalid_csrf_is_rejected_without_changing_session(string $path, mixed $token): void
    {
        $this->login();
        $before = $_SESSION;

        $response = $this->request('POST', $path, ['_token' => $token]);

        self::assertSame(419, $response->status());
        self::assertSame($before, $_SESSION);
    }

    public static function invalidCsrfRequests(): array
    {
        return [
            'login wrong token' => ['/login', 'wrong'],
            'login missing token' => ['/login', null],
            'login array token' => ['/login', ['wrong']],
            'logout wrong token' => ['/logout', 'wrong'],
            'logout missing token' => ['/logout', null],
            'logout array token' => ['/logout', ['wrong']],
        ];
    }

    public function test_login_form_has_csrf_and_no_school_chooser_or_password_value(): void
    {
        $response = $this->request('GET', '/login');

        self::assertSame(200, $response->status());
        self::assertStringContainsString('name="_token"', $response->body());
        self::assertStringContainsString((new Csrf())->token(new Session()), $response->body());
        self::assertStringNotContainsString('school_id', $response->body());
        self::assertStringNotContainsString('<select', $response->body());
        self::assertDoesNotMatchRegularExpression('/<input[^>]*type="password"[^>]*value=/i', $response->body());
    }

    public function test_login_stores_only_server_resolved_identity_and_activity(): void
    {
        $before = time();
        $this->login(['school_id' => $this->otherSchoolId, 'school_membership_id' => 0, 'context_type' => 'SYSTEM'],
            ['school_id' => $this->otherSchoolId, 'school_membership_id' => 0, 'context_type' => 'SYSTEM']);

        self::assertSame($this->userId, $_SESSION['user_id']);
        self::assertSame('SCHOOL', $_SESSION['context_type'] ?? null);
        self::assertSame($this->schoolId, $_SESSION['school_id']);
        self::assertSame($this->membershipId, $_SESSION['school_membership_id']);
        self::assertSame('ครูทดสอบ', $_SESSION['display_name']);
        self::assertIsInt($_SESSION['last_activity']);
        self::assertGreaterThanOrEqual($before, $_SESSION['last_activity']);
        self::assertLessThanOrEqual(time(), $_SESSION['last_activity']);
        self::assertArrayNotHasKey('password_hash', $_SESSION);
        self::assertArrayNotHasKey('password', $_SESSION);
    }

    public function test_invalid_credentials_do_not_echo_password_or_hash(): void
    {
        $response = $this->request('POST', '/login', [
            '_token' => (new Csrf())->token(new Session()),
            'username' => 'isolation-task6-user',
            'password' => '<script>secret-password</script>',
        ]);

        self::assertSame(422, $response->status());
        self::assertStringNotContainsString('secret-password', $response->body());
        self::assertStringNotContainsString('password_hash', $response->body());
        self::assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_array_credentials_are_rejected_without_runtime_warnings(): void
    {
        $response = $this->request('POST', '/login', [
            '_token' => (new Csrf())->token(new Session()),
            'username' => ['invalid'],
            'password' => ['invalid'],
        ]);

        self::assertSame(422, $response->status());
        self::assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_login_view_escapes_dynamic_html(): void
    {
        $html = View::render('auth/login', [
            'error' => '<script>"error"</script>',
            'csrfToken' => '" onfocus="alert(1)',
        ]);

        self::assertStringContainsString('&lt;script&gt;&quot;error&quot;&lt;/script&gt;', $html);
        self::assertStringContainsString('&quot; onfocus=&quot;alert(1)', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function test_logout_clears_session_and_redirects_to_login(): void
    {
        $this->login();

        $response = $this->request('POST', '/logout', ['_token' => $_SESSION['csrf_token']]);

        self::assertEquals(Response::redirect('/login'), $response);
        self::assertSame([], $_SESSION);
        self::assertEquals(Response::redirect('/login'), $this->request('GET', '/dashboard'));
    }

    public function test_get_logout_is_not_allowed(): void
    {
        self::assertSame(405, $this->request('GET', '/logout')->status());
    }

    public function test_database_errors_do_not_expose_sql_or_stack_traces(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('SQLSTATE private SQL and credentials'));
        $app = new Application($pdo);
        $_SESSION = [
            'user_id' => $this->userId,
            'school_id' => $this->schoolId,
            'school_membership_id' => $this->membershipId,
        ];

        $response = $app->handle(new Request('GET', '/dashboard', [], [], []));

        self::assertSame(500, $response->status());
        self::assertSame('Internal Server Error', $response->body());
    }

    #[DataProvider('nonSchoolContexts')]
    public function test_non_school_context_cannot_use_injected_valid_school_ids(mixed $context): void
    {
        $this->login();
        $_SESSION['context_type'] = $context;

        $response = $this->request('GET', '/dashboard');

        self::assertSame(403, $response->status());
        self::assertStringContainsString('ไม่มีสิทธิ์เข้าใช้งาน', $response->body());
        self::assertStringNotContainsString('โรงเรียน A', $response->body());
    }

    public static function nonSchoolContexts(): array
    {
        return ['system' => ['SYSTEM'], 'missing' => [null], 'unknown' => ['UNKNOWN'], 'malformed' => [[]]];
    }

    public function test_system_login_clears_stale_school_keys_and_ignores_browser_context(): void
    {
        $this->login();
        $systemUserId = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)',
            ['isolation-task3-system', password_hash('system-password', PASSWORD_DEFAULT), 'System Admin']);
        $this->pdo->prepare("INSERT INTO user_role_assignments (user_id, school_id, role_id)
            SELECT ?, NULL, id FROM roles WHERE code = 'SYSTEM_ADMIN'")->execute([$systemUserId]);
        $before = time();
        $forged = ['context_type' => 'SCHOOL', 'school_id' => $this->schoolId, 'school_membership_id' => $this->membershipId];

        $response = $this->request('POST', '/login', array_merge($forged, [
            '_token' => $_SESSION['csrf_token'],
            'username' => 'isolation-task3-system',
            'password' => 'system-password',
        ]), $forged);

        self::assertEquals(Response::redirect('/system/schools'), $response);
        self::assertSame($systemUserId, $_SESSION['user_id']);
        self::assertSame('SYSTEM', $_SESSION['context_type'] ?? null);
        self::assertSame('System Admin', $_SESSION['display_name']);
        self::assertArrayNotHasKey('school_id', $_SESSION);
        self::assertArrayNotHasKey('school_membership_id', $_SESSION);
        self::assertIsInt($_SESSION['last_activity']);
        self::assertGreaterThanOrEqual($before, $_SESSION['last_activity']);
        self::assertLessThanOrEqual(time(), $_SESSION['last_activity']);
        self::assertArrayHasKey('csrf_token', $_SESSION);
        self::assertSame(403, $this->request('GET', '/dashboard')->status());

        $this->login();
        self::assertSame('SCHOOL', $_SESSION['context_type']);
        self::assertSame($this->schoolId, $_SESSION['school_id']);
        self::assertSame($this->membershipId, $_SESSION['school_membership_id']);
    }

    public function test_browser_school_cannot_choose_between_two_active_membership_rows(): void
    {
        $this->insert('INSERT INTO school_memberships (user_id, school_id) VALUES (?, ?)', [$this->userId, $this->otherSchoolId]);
        $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?')->execute(['SUSPENDED', $this->otherSchoolId]);
        $forged = ['school_id' => $this->schoolId, 'school_membership_id' => $this->membershipId, 'context_type' => 'SCHOOL'];

        $response = $this->request('POST', '/login', array_merge($forged, [
            '_token' => (new Csrf())->token(new Session()),
            'username' => 'isolation-task6-user',
            'password' => 'correct-password',
        ]), $forged);

        self::assertSame(422, $response->status());
        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertArrayNotHasKey('context_type', $_SESSION);
        self::assertArrayNotHasKey('school_id', $_SESSION);
        self::assertArrayNotHasKey('school_membership_id', $_SESSION);
    }

    private function login(array $extra = [], array $query = []): void
    {
        $response = $this->request('POST', '/login', array_merge([
            '_token' => (new Csrf())->token(new Session()),
            'username' => 'isolation-task6-user',
            'password' => 'correct-password',
        ], $extra), $query);

        self::assertEquals(Response::redirect('/dashboard'), $response);
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
}
