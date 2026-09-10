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

final class SchoolAdminHttpTest extends TestCase
{
    private SchoolAdminHttpTestPDO $pdo;
    private Application $app;
    private int $schoolId;
    private int $foreignSchoolId;
    private int $actorId;
    private int $targetId;
    private int $foreignUserId;
    private int $systemId;
    private static ?string $fixtureHash = null;
    private const OLD_PASSWORD = 'http-school-original-password';
    private const PASSWORD = 'http-school-new-secret-password';
    private const HOSTILE = '<script>alert("school")</script> & \'profile\'';

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new SchoolAdminHttpTestPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        self::$fixtureHash ??= password_hash(self::OLD_PASSWORD, PASSWORD_DEFAULT);
        $this->schoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['http7-a', 'School A']);
        $this->foreignSchoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['http7-b', 'School B']);
        $this->actorId = $this->fixtureUser('http7-admin', $this->schoolId, 'SCHOOL_ADMIN');
        $this->targetId = $this->fixtureUser('http7-target', $this->schoolId, 'VIEWER');
        $this->foreignUserId = $this->fixtureUser('http7-foreign', $this->foreignSchoolId, 'SCHOOL_ADMIN');
        $this->systemId = $this->fixtureUser('http7-system', null, 'SYSTEM_ADMIN');
        $this->app = new Application($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->failPrepare = null;
            while ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
        $_SESSION = [];
    }

    public static function routes(): array
    {
        return [
            'list' => ['GET', '/admin/users', 'SCHOOL_USER_VIEW'],
            'create form' => ['GET', '/admin/users/create', 'SCHOOL_USER_CREATE'],
            'create' => ['POST', '/admin/users', 'SCHOOL_USER_CREATE'],
            'edit' => ['GET', '/admin/users/{id}/edit', 'SCHOOL_USER_VIEW'],
            'profile' => ['POST', '/admin/users/{id}/profile', 'SCHOOL_USER_UPDATE'],
            'membership' => ['POST', '/admin/users/{id}/membership-status', 'SCHOOL_MEMBERSHIP_STATUS_MANAGE'],
            'roles' => ['POST', '/admin/users/{id}/roles', 'SCHOOL_ROLE_MANAGE'],
            'password' => ['POST', '/admin/users/{id}/reset-password', 'SCHOOL_PASSWORD_RESET'],
        ];
    }

    #[DataProvider('routes')]
    public function test_route_has_exact_protected_school_permission_metadata(string $method, string $path, string $permission): void
    {
        $dispatcher = simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php');
        $route = $dispatcher->dispatch($method, $this->path($path));
        self::assertSame(Dispatcher::FOUND, $route[0]);
        self::assertTrue($route[1]['protected']);
        self::assertSame('SCHOOL', $route[1]['context']);
        self::assertSame($permission, $route[1]['permission']);
        self::assertArrayNotHasKey('school_id', $route[2]);
        if (str_contains($path, '{id}')) {
            self::assertSame((string) $this->targetId, $route[2]['id']);
            self::assertSame(Dispatcher::NOT_FOUND, $dispatcher->dispatch($method, str_replace('{id}', 'abc', $path))[0]);
        }
    }

    #[DataProvider('routes')]
    public function test_all_routes_require_authentication_before_handler(string $method, string $path, string $permission): void
    {
        $before = $this->snapshot();
        self::assertEquals(Response::redirect('/login'), $this->request($method, $this->path($path), $this->payload()));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('routes')]
    public function test_system_context_is_denied_even_with_school_permissions(string $method, string $path, string $permission): void
    {
        $_SESSION = ['user_id' => $this->systemId, 'context_type' => 'SYSTEM'];
        $this->pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'SYSTEM_ADMIN' AND p.code = ?")->execute([$permission]);
        $before = $this->snapshot();
        $response = $this->request($method, $this->path($path), $this->payload());
        $this->assertDenied($response);
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('routes')]
    public function test_injected_school_session_is_denied_on_every_route(string $method, string $path, string $permission): void
    {
        $this->schoolSession();
        $_SESSION['school_id'] = $this->foreignSchoolId;
        $before = $this->snapshot();
        $this->assertDenied($this->request($method, $this->path($path), $this->payload()));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('routes')]
    public function test_each_route_requires_its_exact_permission_even_for_school_admin(string $method, string $path, string $permission): void
    {
        $this->schoolSession();
        $this->pdo->prepare("DELETE rp FROM role_permissions rp JOIN roles r ON r.id = rp.role_id
            JOIN permissions p ON p.id = rp.permission_id WHERE r.code = 'SCHOOL_ADMIN' AND p.code = ?")->execute([$permission]);
        $before = $this->snapshot();
        $this->assertDenied($this->request($method, $this->path($path), $this->payload()));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('routes')]
    public function test_ordinary_member_cannot_access_admin_routes(string $method, string $path, string $permission): void
    {
        $this->schoolSession($this->targetId);
        $before = $this->snapshot();
        $this->assertDenied($this->request($method, $this->path($path), $this->payload()));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('routes')]
    public function test_authorization_uses_permission_mapping_instead_of_role_name(string $method, string $path, string $permission): void
    {
        $this->pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'VIEWER' AND p.code = ?")->execute([$permission]);
        $this->schoolSession($this->targetId);
        $response = $this->request($method, $this->path($path, $this->actorId), $this->payload());
        self::assertSame($method === 'GET' ? 200 : 302, $response->status());
    }

    #[DataProvider('invalidContexts')]
    public function test_invalid_school_or_membership_remains_blocked(string $kind, int $status): void
    {
        $this->schoolSession();
        if (str_starts_with($kind, 'school ')) {
            $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?')->execute([substr($kind, 7), $this->schoolId]);
        } elseif (str_starts_with($kind, 'membership ')) {
            $this->pdo->prepare('UPDATE school_memberships SET status = ? WHERE user_id = ?')->execute([substr($kind, 11), $this->actorId]);
        } elseif ($kind === 'inactive user') {
            $this->pdo->prepare("UPDATE users SET status = 'INACTIVE' WHERE id = ?")->execute([$this->actorId]);
        } elseif ($kind === 'wrong membership') {
            $_SESSION['school_membership_id'] = $this->membershipId($this->foreignUserId);
        } elseif ($kind === 'missing school') {
            unset($_SESSION['school_id']);
        } else {
            $_SESSION['school_id'] = (string) $this->schoolId;
        }
        $before = $this->snapshot();
        foreach ([['GET', '/admin/users'], ['POST', '/admin/users']] as [$method, $path]) {
            $response = $this->request($method, $path, $this->payload());
            self::assertSame($status, $response->status());
            $this->assertSafe($response->body());
        }
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidContexts(): array
    {
        return ['suspended school' => ['school SUSPENDED', 403], 'inactive school' => ['school INACTIVE', 403],
            'suspended membership' => ['membership SUSPENDED', 403], 'inactive membership' => ['membership INACTIVE', 403],
            'inactive user' => ['inactive user', 403], 'foreign membership' => ['wrong membership', 403],
            'missing school' => ['missing school', 302], 'string school' => ['string school', 302]];
    }

    public static function mutations(): array
    {
        return ['profile' => ['/admin/users/{id}/profile'], 'membership' => ['/admin/users/{id}/membership-status'],
            'roles' => ['/admin/users/{id}/roles'], 'password' => ['/admin/users/{id}/reset-password']];
    }

    #[DataProvider('mutations')]
    public function test_foreign_mutation_is_rejected_with_all_rows_and_audits_unchanged(string $path): void
    {
        $this->schoolSession();
        $before = $this->snapshot();
        $response = $this->request('POST', $this->path($path, $this->foreignUserId), $this->payload(['school_id' => $this->foreignSchoolId]), ['school_id' => $this->foreignSchoolId]);
        self::assertSame(422, $response->status());
        $this->assertErrorOnly($response);
        self::assertSame($before, $this->snapshot());
    }

    public function test_foreign_and_nonexistent_edit_targets_are_indistinguishable_and_safe(): void
    {
        $this->schoolSession();
        $before = $this->snapshot();
        $foreign = $this->request('GET', $this->path('/admin/users/{id}/edit', $this->foreignUserId), [], ['school_id' => $this->foreignSchoolId]);
        self::assertSame(404, $foreign->status());
        $missing = $this->request('GET', '/admin/users/0/edit');
        self::assertSame(404, $missing->status());
        self::assertSame($missing->body(), $foreign->body());
        $this->assertSafe($foreign->body());
        self::assertStringNotContainsString('http7-foreign', $foreign->body());
        self::assertSame($before, $this->snapshot());
        // Prove this is a tenant-aware edit handler, not the missing-route fallback.
        self::assertSame(200, $this->request('GET', $this->path('/admin/users/{id}/edit'))->status());
    }

    public static function badCsrf(): array
    {
        $cases = [];
        foreach (array_merge([['/admin/users']], array_values(self::mutations())) as [$path]) {
            foreach (['missing' => null, 'bad' => 'invalid-token', 'malformed' => ['invalid-token']] as $name => $token) {
                $cases[$path . ' ' . $name] = [$path, $token];
            }
        }
        return $cases;
    }

    #[DataProvider('badCsrf')]
    public function test_every_post_rejects_missing_bad_and_malformed_csrf_without_mutation(string $path, mixed $token): void
    {
        $this->schoolSession();
        $payload = $this->payload(['_token' => $token]);
        if ($token === null) {
            unset($payload['_token']);
        }
        $before = $this->snapshot();
        $response = $this->request('POST', $this->path($path), $payload);
        self::assertSame(419, $response->status());
        $this->assertSafe($response->body());
        self::assertSame($before, $this->snapshot());
    }

    public function test_list_shows_only_current_school_members_profiles_status_and_safe_roles(): void
    {
        $this->schoolSession();
        $this->pdo->prepare('UPDATE users SET display_name = ?, email = ? WHERE id = ?')->execute([self::HOSTILE, 'target@example.test', $this->targetId]);
        $this->pdo->prepare("UPDATE roles SET name_th = ? WHERE code = 'VIEWER'")->execute(['<b>Viewer & "role"</b>']);
        $this->pdo->prepare("UPDATE school_memberships SET status = 'SUSPENDED' WHERE user_id = ?")->execute([$this->targetId]);
        $response = $this->request('GET', '/admin/users', [], ['school_id' => $this->foreignSchoolId]);
        self::assertSame(200, $response->status());
        foreach (['http7-admin', 'http7-target', 'target@example.test', 'SUSPENDED', 'VIEWER', '/admin/users/' . $this->targetId . '/edit'] as $value) {
            self::assertStringContainsString($value, $response->body());
        }
        self::assertStringContainsString(htmlspecialchars(self::HOSTILE, ENT_QUOTES, 'UTF-8'), $response->body());
        self::assertStringNotContainsString(self::HOSTILE, $response->body());
        self::assertStringNotContainsString('<b>Viewer', $response->body());
        foreach (['http7-foreign', 'http7-system'] as $foreign) {
            self::assertStringNotContainsString($foreign, $response->body());
        }
        $this->assertSafe($response->body());
        self::assertSame(self::HOSTILE, $this->row('SELECT display_name FROM users WHERE id = ?', [$this->targetId])['display_name']);
    }

    public function test_create_form_has_csrf_active_school_roles_blank_password_and_no_context_controls(): void
    {
        $this->schoolSession();
        $this->pdo->prepare("UPDATE roles SET status = 'INACTIVE' WHERE code = 'EXECUTIVE'")->execute();
        $this->pdo->prepare("UPDATE roles SET name_th = ? WHERE code = 'VIEWER'")->execute([self::HOSTILE]);
        $response = $this->request('GET', '/admin/users/create');
        self::assertSame(200, $response->status());
        $xpath = $this->xpath($response->body());
        self::assertSame(1, $xpath->query('//form[@method="post" and @action="/admin/users"]')->length);
        foreach (['username', 'display_name', 'email', 'password'] as $field) {
            self::assertSame(1, $xpath->query('//form//input[@name="' . $field . '"]')->length);
        }
        self::assertSame($this->token(), $xpath->evaluate('string(//form//input[@name="_token"]/@value)'));
        self::assertSame($this->activeRoleCodes(), $this->offeredRoles($xpath));
        self::assertStringContainsString(htmlspecialchars(self::HOSTILE, ENT_QUOTES, 'UTF-8'), $response->body());
        $this->assertSafeForm($xpath, $response->body());
    }

    public function test_create_uses_session_actor_school_and_remote_addr_and_service_role_normalization(): void
    {
        $this->schoolSession();
        $session = $_SESSION;
        $forged = ['school_id' => $this->foreignSchoolId, 'actor_user_id' => $this->foreignUserId,
            'user_id' => $this->foreignUserId, 'context_type' => 'SYSTEM', 'ip_address' => '203.0.113.9', 'REMOTE_ADDR' => '203.0.113.10'];
        $response = $this->request('POST', '/admin/users', $this->payload($forged + ['role_codes' => [' VIEWER ', 'HOMEROOM_TEACHER', 'VIEWER', ' ']]), $forged,
            ['REMOTE_ADDR' => '192.0.2.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.11']);
        self::assertEquals(Response::redirect('/admin/users'), $response);
        self::assertSame($session, $_SESSION);
        $user = $this->row("SELECT * FROM users WHERE username = 'http7-new'");
        self::assertTrue(password_verify(self::PASSWORD, $user['password_hash']));
        $membership = $this->row('SELECT * FROM school_memberships WHERE user_id = ?', [$user['id']]);
        self::assertSame($this->schoolId, $membership['school_id']);
        self::assertSame($this->actorId, $membership['created_by']);
        self::assertSame('ACTIVE', $membership['status']);
        $roles = $this->rows('SELECT ura.*, r.code FROM user_role_assignments ura JOIN roles r ON r.id = ura.role_id WHERE ura.user_id = ? ORDER BY r.code', [$user['id']]);
        self::assertSame(['HOMEROOM_TEACHER', 'VIEWER'], array_column($roles, 'code'));
        foreach ($roles as $role) {
            self::assertSame($this->schoolId, $role['school_id']);
            self::assertSame($this->actorId, $role['assigned_by']);
            self::assertSame('ACTIVE', $role['status']);
            self::assertNull($role['academic_year_id']);
        }
        $audits = $this->rows('SELECT * FROM audit_logs ORDER BY id');
        self::assertSame(['SCHOOL_USER_CREATED', 'SCHOOL_ROLES_CHANGED'], array_column($audits, 'action'));
        foreach ($audits as $audit) {
            $this->assertAudit($audit, (int) $user['id'], 'users', '192.0.2.7');
        }
        $this->assertSafe(json_encode($audits, JSON_THROW_ON_ERROR), $user['password_hash']);
        self::assertSame(1, $this->pdo->depth);
        $_SESSION = [];
        self::assertEquals(Response::redirect('/dashboard'), $this->request('POST', '/login', [
            '_token' => $this->token(), 'username' => 'http7-new', 'password' => self::PASSWORD,
        ]));
        self::assertSame($this->schoolId, $_SESSION['school_id']);
        self::assertSame($membership['id'], $_SESSION['school_membership_id']);
    }

    #[DataProvider('invalidCreate')]
    public function test_invalid_create_is_friendly_atomic_and_never_echoes_password(string $field, mixed $value): void
    {
        $this->schoolSession();
        $before = $this->snapshot();
        $response = $this->request('POST', '/admin/users', $this->payload([$field => $value]));
        self::assertSame(422, $response->status());
        $this->assertSafe($response->body());
        self::assertSame(1, $this->xpath($response->body())->query('//form[@action="/admin/users"]')->length);
        $this->assertSafeForm($this->xpath($response->body()), $response->body());
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidCreate(): array
    {
        return ['duplicate username' => ['username', 'http7-foreign'], 'duplicate email' => ['email', 'http7-foreign@example.test'],
            'invalid email' => ['email', 'invalid-email'], 'short password' => ['password', 'shortsecret'],
            'missing username' => ['username', null], 'short username' => ['username', 'ab'], 'invalid username' => ['username', 'bad/name'],
            'long username' => ['username', str_repeat('a', 101)], 'blank display name' => ['display_name', ' '],
            'long display name' => ['display_name', str_repeat('ก', 191)], 'no roles' => ['role_codes', []],
            'unknown role' => ['role_codes', ['NOT_A_ROLE']], 'system role' => ['role_codes', ['SYSTEM_ADMIN']],
            'malformed username' => ['username', ['bad']], 'malformed name' => ['display_name', ['bad']],
            'malformed email' => ['email', ['bad']], 'malformed password' => ['password', ['bad']],
            'malformed roles' => ['role_codes', 'VIEWER'], 'nested role' => ['role_codes', [['VIEWER']]],
            'numeric role' => ['role_codes', [1]], 'missing roles' => ['role_codes', null]];
    }

    public function test_create_error_preserves_safe_values_and_escapes_them_without_password(): void
    {
        $this->schoolSession();
        $response = $this->request('POST', '/admin/users', $this->payload(['display_name' => self::HOSTILE, 'password' => 'shortsecret']));
        self::assertSame(422, $response->status());
        $xpath = $this->xpath($response->body());
        self::assertSame(self::HOSTILE, $xpath->evaluate('string(//input[@name="display_name"]/@value)'));
        self::assertSame('http7-new', $xpath->evaluate('string(//input[@name="username"]/@value)'));
        self::assertSame('new@example.test', $xpath->evaluate('string(//input[@name="email"]/@value)'));
        self::assertStringNotContainsString(self::HOSTILE, $response->body());
        $this->assertSafeForm($xpath, $response->body());
    }

    public function test_edit_has_four_csrf_forms_current_roles_and_escaped_profile_with_blank_password(): void
    {
        $this->schoolSession();
        $this->pdo->prepare('UPDATE users SET display_name = ? WHERE id = ?')->execute([self::HOSTILE, $this->targetId]);
        $this->pdo->prepare("UPDATE roles SET status = 'INACTIVE' WHERE code = 'EXECUTIVE'")->execute();
        $response = $this->request('GET', $this->path('/admin/users/{id}/edit'), [], ['school_id' => $this->foreignSchoolId]);
        self::assertSame(200, $response->status());
        $xpath = $this->xpath($response->body());
        self::assertSame(4, $xpath->query('//form')->length);
        foreach (self::mutations() as [$path]) {
            $form = '//form[@method="post" and @action="' . $this->path($path) . '"]';
            self::assertSame(1, $xpath->query($form)->length);
            self::assertSame($this->token(), $xpath->evaluate('string(' . $form . '//input[@name="_token"]/@value)'));
        }
        self::assertSame(self::HOSTILE, $xpath->evaluate('string(//input[@name="display_name"]/@value)'));
        self::assertSame(0, $xpath->query('//*[@name="username"]')->length);
        self::assertSame($this->activeRoleCodes(), $this->offeredRoles($xpath));
        self::assertSame(1, $xpath->query('//input[@name="role_codes[]" and @value="VIEWER" and @checked] | //select[@name="role_codes[]"]/option[@value="VIEWER" and @selected]')->length);
        self::assertGreaterThanOrEqual(1, $xpath->query('//*[@name="status"]')->length);
        self::assertSame(0, $xpath->query('//*[@name="status" and @value="INACTIVE"] | //select[@name="status"]/option[@value="INACTIVE"]')->length);
        self::assertStringNotContainsString(self::HOSTILE, $response->body());
        self::assertStringNotContainsString('http7-foreign', $response->body());
        $this->assertSafeForm($xpath, $response->body());
    }

    public function test_profile_updates_own_school_trims_values_nulls_blank_email_and_keeps_username(): void
    {
        $this->schoolSession();
        $foreign = $this->row('SELECT * FROM users WHERE id = ?', [$this->foreignUserId]);
        $response = $this->request('POST', $this->path('/admin/users/{id}/profile'), $this->payload([
            'display_name' => '  ' . self::HOSTILE . '  ', 'email' => ' ', 'username' => 'forged-name', 'school_id' => $this->foreignSchoolId,
            'actor_user_id' => $this->foreignUserId,
        ]), ['school_id' => $this->foreignSchoolId], ['REMOTE_ADDR' => '192.0.2.7']);
        self::assertEquals(Response::redirect($this->path('/admin/users/{id}/edit')), $response);
        $user = $this->row('SELECT * FROM users WHERE id = ?', [$this->targetId]);
        self::assertSame(self::HOSTILE, $user['display_name']);
        self::assertNull($user['email']);
        self::assertSame('http7-target', $user['username']);
        self::assertSame(self::$fixtureHash, $user['password_hash']);
        self::assertSame($foreign, $this->row('SELECT * FROM users WHERE id = ?', [$this->foreignUserId]));
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame('SCHOOL_USER_UPDATED', $audit['action']);
        self::assertSame(['display_name' => 'http7-target', 'email' => 'http7-target@example.test'], json_decode($audit['old_value'], true));
        self::assertSame(['display_name' => self::HOSTILE, 'email' => null], json_decode($audit['new_value'], true));
        $this->assertAudit($audit, $this->targetId, 'users', '192.0.2.7');
    }

    #[DataProvider('invalidMutations')]
    public function test_mutation_validation_errors_are_friendly_and_atomic(string $suffix, string $field, mixed $value): void
    {
        $this->schoolSession();
        $before = $this->snapshot();
        $response = $this->request('POST', '/admin/users/' . $this->targetId . '/' . $suffix, $this->payload([$field => $value]));
        self::assertSame(422, $response->status());
        $this->assertErrorOnly($response);
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidMutations(): array
    {
        return ['profile blank' => ['profile', 'display_name', ' '], 'profile malformed' => ['profile', 'display_name', ['bad']],
            'email duplicate' => ['profile', 'email', 'http7-foreign@example.test'], 'email invalid' => ['profile', 'email', 'bad-email'],
            'email malformed' => ['profile', 'email', ['bad']], 'inactive status' => ['membership-status', 'status', 'INACTIVE'],
            'invalid status' => ['membership-status', 'status', 'DELETED'], 'missing status' => ['membership-status', 'status', null],
            'malformed status' => ['membership-status', 'status', ['ACTIVE']], 'empty roles' => ['roles', 'role_codes', []],
            'system role' => ['roles', 'role_codes', ['SYSTEM_ADMIN']], 'unknown role' => ['roles', 'role_codes', ['NO_ROLE']],
            'missing roles' => ['roles', 'role_codes', null], 'malformed roles' => ['roles', 'role_codes', 'VIEWER'],
            'nested role' => ['roles', 'role_codes', [['VIEWER']]], 'short password' => ['reset-password', 'password', 'shortsecret'],
            'missing password' => ['reset-password', 'password', null], 'malformed password' => ['reset-password', 'password', ['secret']]];
    }

    #[DataProvider('mutations')]
    public function test_validation_does_not_disclose_target_or_forms_without_view_permission(string $path): void
    {
        $this->schoolSession();
        $this->pdo->prepare("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE p.code = 'SCHOOL_USER_VIEW'")->execute();
        $before = $this->snapshot();
        $response = $this->request('POST', $this->path($path), $this->payload(['display_name' => '', 'status' => 'DELETED', 'role_codes' => [], 'password' => 'shortsecret']));
        self::assertSame(422, $response->status());
        $this->assertErrorOnly($response);
        self::assertSame($before, $this->snapshot());
    }

    public function test_membership_suspend_reactivate_and_noop_keep_service_audit_behavior(): void
    {
        $this->schoolSession();
        foreach ([['ACTIVE', 'SUSPENDED'], ['SUSPENDED', 'ACTIVE']] as [$old, $new]) {
            $response = $this->request('POST', $this->path('/admin/users/{id}/membership-status'), $this->payload([
                'status' => $new, 'school_id' => $this->foreignSchoolId,
            ]), [], ['REMOTE_ADDR' => '192.0.2.7']);
            self::assertEquals(Response::redirect($this->path('/admin/users/{id}/edit')), $response);
            self::assertSame($new, $this->row('SELECT status FROM school_memberships WHERE user_id = ?', [$this->targetId])['status']);
            $audit = $this->row('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 1');
            self::assertSame('MEMBERSHIP_STATUS_CHANGED', $audit['action']);
            self::assertSame(['status' => $old], json_decode($audit['old_value'], true));
            self::assertSame(['status' => $new], json_decode($audit['new_value'], true));
            $this->assertAudit($audit, $this->membershipId($this->targetId), 'school_memberships', '192.0.2.7');
        }
        $before = $this->snapshot();
        self::assertEquals(Response::redirect($this->path('/admin/users/{id}/edit')), $this->request('POST', $this->path('/admin/users/{id}/membership-status'), $this->payload(['status' => 'ACTIVE'])));
        self::assertSame($before, $this->snapshot());
    }

    public function test_self_suspension_and_self_admin_role_removal_are_denied(): void
    {
        $this->schoolSession();
        $before = $this->snapshot();
        foreach (['membership-status', 'roles'] as $suffix) {
            $response = $this->request('POST', '/admin/users/' . $this->actorId . '/' . $suffix, $this->payload(['role_codes' => ['VIEWER']]));
            self::assertSame(422, $response->status());
            $this->assertErrorOnly($response);
            self::assertSame($before, $this->snapshot());
        }
    }

    public function test_role_replacement_deduplicates_deactivates_and_reuses_existing_assignments(): void
    {
        $this->schoolSession();
        $viewer = $this->row('SELECT * FROM user_role_assignments WHERE user_id = ?', [$this->targetId]);
        $response = $this->request('POST', $this->path('/admin/users/{id}/roles'), $this->payload([
            'role_codes' => [' HOMEROOM_TEACHER ', 'HOMEROOM_TEACHER'], 'school_id' => $this->foreignSchoolId,
        ]), [], ['REMOTE_ADDR' => '192.0.2.7']);
        self::assertEquals(Response::redirect($this->path('/admin/users/{id}/edit')), $response);
        self::assertSame('INACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$viewer['id']])['status']);
        $teacher = $this->row("SELECT ura.* FROM user_role_assignments ura JOIN roles r ON r.id = ura.role_id WHERE ura.user_id = ? AND r.code = 'HOMEROOM_TEACHER'", [$this->targetId]);
        self::assertSame('ACTIVE', $teacher['status']);
        self::assertSame($this->actorId, $teacher['assigned_by']);
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame('SCHOOL_ROLES_CHANGED', $audit['action']);
        self::assertSame(['role_codes' => ['VIEWER']], json_decode($audit['old_value'], true));
        self::assertSame(['role_codes' => ['HOMEROOM_TEACHER']], json_decode($audit['new_value'], true));
        $this->assertAudit($audit, $this->targetId, 'users', '192.0.2.7');
        self::assertEquals(Response::redirect($this->path('/admin/users/{id}/edit')), $this->request('POST', $this->path('/admin/users/{id}/roles'), $this->payload(['role_codes' => ['VIEWER']])));
        self::assertSame('ACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$viewer['id']])['status']);
        self::assertCount(2, $this->rows('SELECT * FROM user_role_assignments WHERE user_id = ?', [$this->targetId]));
    }

    #[DataProvider('staleDefinitions')]
    public function test_http_replacement_cleans_stale_assignments_preserves_other_scopes_and_explicitly_reuses_row(string $status, string $scope): void
    {
        $this->schoolSession();
        $staleId = $this->assignment($this->targetId, $this->schoolId, 'EXECUTIVE');
        $this->insert('INSERT INTO school_memberships (user_id, school_id, status) VALUES (?, ?, ?)', [$this->targetId, $this->foreignSchoolId, 'SUSPENDED']);
        $unrelated = [];
        foreach ([$this->assignment($this->targetId, $this->schoolId, 'EXECUTIVE', 1),
            $this->assignment($this->targetId, $this->foreignSchoolId, 'EXECUTIVE'),
            $this->assignment($this->foreignUserId, $this->foreignSchoolId, 'EXECUTIVE'),
            $this->assignment($this->actorId, $this->schoolId, 'EXECUTIVE'),
            $this->assignment($this->targetId, null, 'EXECUTIVE')] as $id) {
            $unrelated[$id] = $this->row('SELECT * FROM user_role_assignments WHERE id = ?', [$id]);
        }
        $this->pdo->prepare("UPDATE roles SET status = ?, scope_type = ? WHERE code = 'EXECUTIVE'")->execute([$status, $scope]);
        $response = $this->request('POST', $this->path('/admin/users/{id}/roles'), $this->payload(['role_codes' => ['VIEWER']]));
        self::assertEquals(Response::redirect($this->path('/admin/users/{id}/edit')), $response);
        self::assertSame('INACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$staleId])['status']);
        foreach ($unrelated as $id => $row) {
            self::assertSame($row, $this->row('SELECT * FROM user_role_assignments WHERE id = ?', [$id]));
        }
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame('SCHOOL_ROLES_CHANGED', $audit['action']);
        self::assertSame(['role_codes' => ['VIEWER']], json_decode($audit['old_value'], true));
        self::assertSame(['role_codes' => ['VIEWER']], json_decode($audit['new_value'], true));
        $before = $this->snapshot();
        self::assertSame(302, $this->request('POST', $this->path('/admin/users/{id}/roles'), $this->payload(['role_codes' => ['VIEWER']]))->status());
        self::assertSame($before, $this->snapshot());
        $this->pdo->prepare("UPDATE roles SET status = 'ACTIVE', scope_type = 'SCHOOL' WHERE code = 'EXECUTIVE'")->execute();
        self::assertSame('INACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$staleId])['status']);
        self::assertSame(302, $this->request('POST', $this->path('/admin/users/{id}/roles'), $this->payload(['role_codes' => ['VIEWER', 'EXECUTIVE']]))->status());
        self::assertSame('ACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$staleId])['status']);
        self::assertCount(2, $this->rows('SELECT id FROM user_role_assignments WHERE user_id = ? AND school_id = ? AND academic_year_id IS NULL', [$this->targetId, $this->schoolId]));
    }

    public static function staleDefinitions(): array
    {
        return ['inactive' => ['INACTIVE', 'SCHOOL'], 'scope drift' => ['ACTIVE', 'SYSTEM']];
    }

    public function test_inactive_role_cannot_be_created_or_assigned(): void
    {
        $this->schoolSession();
        $this->pdo->prepare("UPDATE roles SET status = 'INACTIVE' WHERE code = 'EXECUTIVE'")->execute();
        $before = $this->snapshot();
        foreach (['/admin/users', $this->path('/admin/users/{id}/roles')] as $path) {
            $response = $this->request('POST', $path, $this->payload(['role_codes' => ['EXECUTIVE']]));
            self::assertSame(422, $response->status());
            $this->assertSafe($response->body());
            self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('resetStatuses')]
    public function test_password_reset_for_active_and_suspended_members_is_event_only_and_secret_safe(string $status): void
    {
        $this->schoolSession();
        $this->pdo->prepare('UPDATE school_memberships SET status = ? WHERE user_id = ?')->execute([$status, $this->targetId]);
        $response = $this->request('POST', $this->path('/admin/users/{id}/reset-password'), $this->payload(['password' => '123456789012', 'school_id' => $this->foreignSchoolId]), [], ['REMOTE_ADDR' => '192.0.2.7']);
        self::assertEquals(Response::redirect($this->path('/admin/users/{id}/edit')), $response);
        $user = $this->row('SELECT * FROM users WHERE id = ?', [$this->targetId]);
        self::assertTrue(password_verify('123456789012', $user['password_hash']));
        self::assertFalse(password_verify(self::OLD_PASSWORD, $user['password_hash']));
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame('USER_PASSWORD_RESET', $audit['action']);
        self::assertNull($audit['old_value']);
        self::assertSame(['password_reset' => true], json_decode($audit['new_value'], true));
        $this->assertAudit($audit, $this->targetId, 'users', '192.0.2.7');
        $this->assertSafe(json_encode($audit, JSON_THROW_ON_ERROR), $user['password_hash']);
        self::assertStringNotContainsString('123456789012', json_encode($audit, JSON_THROW_ON_ERROR));
        $edit = $this->request('GET', $this->path('/admin/users/{id}/edit'));
        self::assertSame(200, $edit->status());
        $this->assertSafe($edit->body(), $user['password_hash']);
        self::assertStringNotContainsString('123456789012', $edit->body());
        $this->assertSafeForm($this->xpath($edit->body()), $edit->body());
    }

    public static function resetStatuses(): array
    {
        return [['ACTIVE'], ['SUSPENDED']];
    }

    #[DataProvider('mutations')]
    public function test_inactive_membership_cannot_be_mutated(string $path): void
    {
        $this->schoolSession();
        $this->pdo->prepare("UPDATE school_memberships SET status = 'INACTIVE' WHERE user_id = ?")->execute([$this->targetId]);
        $before = $this->snapshot();
        $response = $this->request('POST', $this->path($path), $this->payload());
        self::assertSame(422, $response->status());
        $this->assertErrorOnly($response);
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('remoteAddresses')]
    public function test_audit_ip_only_uses_valid_remote_addr(mixed $remote, ?string $expected): void
    {
        $this->schoolSession();
        $response = $this->request('POST', $this->path('/admin/users/{id}/profile'), $this->payload(['ip_address' => '203.0.113.3', 'REMOTE_ADDR' => '203.0.113.4']),
            ['REMOTE_ADDR' => '203.0.113.5'], ['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_FOR' => '203.0.113.6', 'HTTP_FORWARDED' => 'for=203.0.113.7']);
        self::assertSame(302, $response->status());
        self::assertSame($expected, $this->row('SELECT ip_address FROM audit_logs')['ip_address']);
    }

    public static function remoteAddresses(): array
    {
        return [['192.0.2.1', '192.0.2.1'], ['2001:db8::1', '2001:db8::1'], [null, null], [['203.0.113.1'], null], ['invalid', null]];
    }

    public static function allPosts(): array
    {
        return array_merge(['create' => ['/admin/users']], self::mutations());
    }

    #[DataProvider('allPosts')]
    public function test_audit_database_failure_rolls_back_every_mutation_and_returns_safe_validation(string $path): void
    {
        $this->schoolSession();
        $before = $this->snapshot();
        $this->pdo->failPrepare = 'INSERT INTO audit_logs';
        $response = $this->request('POST', $this->path($path), $this->payload());
        self::assertSame(422, $response->status());
        self::assertTrue($this->pdo->failureTriggered);
        $this->assertSafe($response->body());
        self::assertSame($before, $this->snapshot());
        self::assertSame(1, $this->pdo->depth);
    }

    public function test_list_database_failure_is_generic_and_does_not_leak_details(): void
    {
        $this->schoolSession();
        $before = $this->snapshot();
        $this->pdo->failPrepare = 'ORDER BY sm.id';
        $response = $this->request('GET', '/admin/users');
        self::assertSame(500, $response->status());
        self::assertTrue($this->pdo->failureTriggered);
        self::assertSame('Internal Server Error', $response->body());
        $this->assertSafe($response->body());
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('mutations')]
    public function test_overflow_numeric_route_target_is_rejected_safely_without_mutation(string $path): void
    {
        $this->schoolSession();
        $before = $this->snapshot();
        $response = $this->request('POST', str_replace('{id}', str_repeat('9', 30), $path), $this->payload());
        self::assertSame(422, $response->status());
        $this->assertErrorOnly($response);
        self::assertSame($before, $this->snapshot());
    }

    private function fixtureUser(string $username, ?int $schoolId, string $role): int
    {
        $id = $this->insert('INSERT INTO users (username, password_hash, display_name, email) VALUES (?, ?, ?, ?)', [$username, self::$fixtureHash, $username, $username . '@example.test']);
        if ($schoolId !== null) {
            $this->insert('INSERT INTO school_memberships (user_id, school_id) VALUES (?, ?)', [$id, $schoolId]);
        }
        $this->assignment($id, $schoolId, $role);
        return $id;
    }

    private function assignment(int $userId, ?int $schoolId, string $role, ?int $year = null): int
    {
        return $this->insert('INSERT INTO user_role_assignments (user_id, school_id, role_id, academic_year_id) SELECT ?, ?, id, ? FROM roles WHERE code = ?', [$userId, $schoolId, $year, $role]);
    }

    private function membershipId(int $userId): int
    {
        return (int) $this->row('SELECT id FROM school_memberships WHERE user_id = ? AND school_id = ?', [$userId, $userId === $this->foreignUserId ? $this->foreignSchoolId : $this->schoolId])['id'];
    }

    private function schoolSession(?int $userId = null): void
    {
        $userId ??= $this->actorId;
        $_SESSION = ['user_id' => $userId, 'context_type' => 'SCHOOL', 'school_id' => $this->schoolId,
            'school_membership_id' => $this->membershipId($userId), 'display_name' => 'School Admin', 'last_activity' => time()];
        $this->token();
    }

    private function token(): string
    {
        return (new Csrf())->token(new Session());
    }

    private function path(string $path, ?int $userId = null): string
    {
        return str_replace('{id}', (string) ($userId ?? $this->targetId), $path);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['_token' => $this->token(), 'username' => 'http7-new', 'display_name' => 'New User',
            'email' => 'new@example.test', 'password' => self::PASSWORD, 'role_codes' => ['HOMEROOM_TEACHER'], 'status' => 'SUSPENDED'], $overrides);
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

    private function activeRoleCodes(): array
    {
        return array_column($this->rows("SELECT code FROM roles WHERE scope_type = 'SCHOOL' AND status = 'ACTIVE' AND code <> 'SYSTEM_ADMIN' ORDER BY code"), 'code');
    }

    private function offeredRoles(DOMXPath $xpath): array
    {
        $values = [];
        foreach ($xpath->query('//input[@name="role_codes[]"]/@value | //select[@name="role_codes[]"]/option/@value') as $attribute) {
            if ($attribute->value !== '') {
                $values[] = $attribute->value;
            }
        }
        sort($values, SORT_STRING);
        return $values;
    }

    private function assertSafeForm(DOMXPath $xpath, string $html): void
    {
        self::assertSame(1, $xpath->query('//input[@type="password" and @name="password"]')->length);
        self::assertSame(0, $xpath->query('//input[@type="password"]/@value')->length);
        self::assertSame(0, $xpath->query('//*[@name="school_id" or @name="context_type" or @name="actor_user_id"]')->length);
        self::assertSame(0, $xpath->query('//script')->length);
        self::assertStringNotContainsString('SYSTEM_ADMIN', $html);
        $this->assertSafe($html);
    }

    private function assertDenied(Response $response): void
    {
        self::assertSame(403, $response->status());
        self::assertStringContainsString('ไม่มีสิทธิ์เข้าใช้งาน', $response->body());
        $this->assertSafe($response->body());
    }

    private function assertErrorOnly(Response $response): void
    {
        self::assertNotSame('', $response->body());
        $this->assertSafe($response->body());
        foreach (['http7-target', 'http7-foreign', 'http7-admin', 'New User'] as $target) {
            self::assertStringNotContainsString($target, $response->body());
        }
        self::assertSame(0, $this->xpath($response->body())->query('//form')->length);
    }

    private function assertSafe(string $output, ?string $hash = null): void
    {
        foreach (['SQLSTATE', 'PDOException', 'Stack trace', 'SELECT ', 'INSERT INTO', 'UPDATE users', '/Applications/', '/private/',
            'private-db-details', 'password_hash', self::PASSWORD, self::OLD_PASSWORD, 'shortsecret', self::$fixtureHash, $hash] as $secret) {
            if ($secret !== null) {
                self::assertStringNotContainsString($secret, $output);
            }
        }
    }

    private function assertAudit(array $audit, int $entityId, string $entityType, ?string $ip): void
    {
        self::assertSame($this->schoolId, $audit['school_id']);
        self::assertSame($this->actorId, $audit['user_id']);
        self::assertSame($entityId, $audit['entity_id']);
        self::assertSame($entityType, $audit['entity_type']);
        self::assertSame($ip, $audit['ip_address']);
        $this->assertSafe(json_encode($audit, JSON_THROW_ON_ERROR));
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

/** Preserve the fixture transaction while HTTP service transactions use savepoints. */
final class SchoolAdminHttpTestPDO extends PDO
{
    public int $depth = 0;
    public ?string $failPrepare = null;
    public bool $failureTriggered = false;

    public function beginTransaction(): bool
    {
        $result = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT http_task7_' . $this->depth) !== false;
        ++$this->depth;
        return $result;
    }

    public function commit(): bool
    {
        $result = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT http_task7_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $result;
    }

    public function rollBack(): bool
    {
        if ($this->depth === 1) {
            $result = parent::rollBack();
        } else {
            $result = $this->exec('ROLLBACK TO SAVEPOINT http_task7_' . ($this->depth - 1)) !== false;
            $this->exec('RELEASE SAVEPOINT http_task7_' . ($this->depth - 1));
        }
        --$this->depth;
        return $result;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failPrepare !== null && str_contains($query, $this->failPrepare)) {
            $this->failPrepare = null;
            $this->failureTriggered = true;
            throw new PDOException('SQLSTATE private-db-details SELECT password_hash FROM users /Applications/private.php http-school-new-secret-password');
        }
        return parent::prepare($query, $options);
    }
}
