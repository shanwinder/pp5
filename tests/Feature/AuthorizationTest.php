<?php
declare(strict_types=1);

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Middleware\PermissionMiddleware;
use App\Repositories\AuthorizationRepository;
use App\Services\AuthorizationService;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthorizationTest extends TestCase
{
    private PDO $pdo;
    private int $userId;
    private int $otherUserId;
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
        $this->userId = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)',
            ['authorization-test-user', 'unused-fixture-hash', 'Authorization User']);
        $this->otherUserId = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)',
            ['authorization-test-other', 'unused-fixture-hash', 'Other User']);
        $this->schoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['authorization-test-a', 'School A']);
        $this->otherSchoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['authorization-test-b', 'School B']);
        $this->membershipId = $this->insert('INSERT INTO school_memberships (user_id, school_id) VALUES (?, ?)', [$this->userId, $this->schoolId]);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $_SESSION = [];
    }

    #[DataProvider('systemCases')]
    public function test_system_authorization_checks_assignment_scope_and_permission(array $options, bool $expectedRole, bool $expectedPermission): void
    {
        $roleId = $this->roleId('SYSTEM_ADMIN');
        $this->assign($roleId, ($options['school'] ?? false) ? $this->schoolId : null, $options['assignment'] ?? 'ACTIVE', $options['year'] ?? null);
        $this->pdo->prepare('UPDATE roles SET status = ?, scope_type = ? WHERE id = ?')
            ->execute([$options['role_status'] ?? 'ACTIVE', $options['scope'] ?? 'SYSTEM', $roleId]);
        $repository = new AuthorizationRepository($this->pdo);
        $userId = ($options['other_user'] ?? false) ? $this->otherUserId : $this->userId;

        self::assertSame($expectedRole, $repository->hasActiveSystemAdmin($userId));
        self::assertSame($expectedPermission, $repository->hasSystemPermission($userId, $options['permission'] ?? 'SYSTEM_SCHOOL_VIEW'));
    }

    public static function systemCases(): array
    {
        return [
            'active global system administrator' => [[], true, true],
            'inactive assignment' => [['assignment' => 'INACTIVE'], false, false],
            'inactive role' => [['role_status' => 'INACTIVE'], false, false],
            'school-bound assignment' => [['school' => true], false, false],
            'year-bound assignment' => [['year' => 1], false, false],
            'other user' => [['other_user' => true], false, false],
            'school scope role' => [['scope' => 'SCHOOL'], false, false],
            'permission not granted' => [['permission' => 'SCHOOL_USER_VIEW'], true, false],
            'unknown permission' => [['permission' => 'UNKNOWN_PERMISSION'], true, false],
        ];
    }

    #[DataProvider('schoolCases')]
    public function test_school_authorization_checks_tenant_status_and_permission(array $options, bool $expectedRole, bool $expectedPermission): void
    {
        $roleId = $this->roleId($options['role'] ?? 'SCHOOL_ADMIN');
        $this->assign($roleId, ($options['global'] ?? false) ? null : $this->schoolId, $options['assignment'] ?? 'ACTIVE', $options['year'] ?? null);
        $this->pdo->prepare('UPDATE roles SET status = ?, scope_type = ? WHERE id = ?')
            ->execute([$options['role_status'] ?? 'ACTIVE', $options['scope'] ?? 'SCHOOL', $roleId]);
        $this->pdo->prepare('UPDATE school_memberships SET status = ? WHERE id = ?')
            ->execute([$options['membership'] ?? 'ACTIVE', $this->membershipId]);
        $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?')
            ->execute([$options['school_status'] ?? 'ACTIVE', $this->schoolId]);
        $repository = new AuthorizationRepository($this->pdo);
        $userId = ($options['other_user'] ?? false) ? $this->otherUserId : $this->userId;
        $schoolId = ($options['other_school'] ?? false) ? $this->otherSchoolId : $this->schoolId;

        self::assertSame($expectedRole, $repository->hasActiveSchoolRole($userId, $schoolId));
        self::assertSame($expectedPermission, $repository->hasSchoolPermission($userId, $schoolId, $options['permission'] ?? 'SCHOOL_USER_VIEW'));
    }

    public static function schoolCases(): array
    {
        return [
            'active school administrator' => [[], true, true],
            'same permission in other school' => [['other_school' => true], false, false],
            'other user' => [['other_user' => true], false, false],
            'role without permission' => [['role' => 'VIEWER'], true, false],
            'inactive assignment' => [['assignment' => 'INACTIVE'], false, false],
            'inactive role' => [['role_status' => 'INACTIVE'], false, false],
            'system scope role' => [['scope' => 'SYSTEM'], false, false],
            'global assignment' => [['global' => true], false, false],
            'year-bound assignment' => [['year' => 1], false, false],
            'suspended membership' => [['membership' => 'SUSPENDED'], false, false],
            'inactive membership' => [['membership' => 'INACTIVE'], false, false],
            'suspended school' => [['school_status' => 'SUSPENDED'], false, false],
            'inactive school' => [['school_status' => 'INACTIVE'], false, false],
            'system permission not granted' => [['permission' => 'SYSTEM_SCHOOL_VIEW'], true, false],
            'unknown permission' => [['permission' => 'UNKNOWN_PERMISSION'], true, false],
        ];
    }

    public function test_system_role_cannot_grant_school_permission_even_with_mapping(): void
    {
        $roleId = $this->roleId('SYSTEM_ADMIN');
        $this->grant($roleId, 'SCHOOL_USER_VIEW');
        $this->assign($roleId, $this->schoolId);
        $repository = new AuthorizationRepository($this->pdo);

        self::assertFalse($repository->hasSchoolPermission($this->userId, $this->schoolId, 'SCHOOL_USER_VIEW'));
        self::assertFalse($repository->hasActiveSchoolRole($this->userId, $this->schoolId));
    }

    public function test_school_role_cannot_grant_system_permission_even_with_mapping(): void
    {
        $roleId = $this->roleId('SCHOOL_ADMIN');
        $this->grant($roleId, 'SYSTEM_SCHOOL_VIEW');
        $this->assign($roleId, null);
        $repository = new AuthorizationRepository($this->pdo);

        self::assertFalse($repository->hasSystemPermission($this->userId, 'SYSTEM_SCHOOL_VIEW'));
        self::assertFalse($repository->hasActiveSystemAdmin($this->userId));
    }

    public function test_system_admin_detection_requires_the_system_admin_code(): void
    {
        $roleId = $this->insert('INSERT INTO roles (code, name_th, scope_type) VALUES (?, ?, ?)', ['AUTH_TEST_SYSTEM', 'Test System Role', 'SYSTEM']);
        $this->grant($roleId, 'SYSTEM_SCHOOL_VIEW');
        $this->assign($roleId, null);
        $repository = new AuthorizationRepository($this->pdo);

        self::assertFalse($repository->hasActiveSystemAdmin($this->userId));
        self::assertTrue($repository->hasSystemPermission($this->userId, 'SYSTEM_SCHOOL_VIEW'));
    }

    public function test_user_without_assignments_has_no_authorization(): void
    {
        $repository = new AuthorizationRepository($this->pdo);

        self::assertFalse($repository->hasActiveSystemAdmin($this->userId));
        self::assertFalse($repository->hasActiveSchoolRole($this->userId, $this->schoolId));
        self::assertFalse($repository->hasSystemPermission($this->userId, 'SYSTEM_SCHOOL_VIEW'));
        self::assertFalse($repository->hasSchoolPermission($this->userId, $this->schoolId, 'SCHOOL_USER_VIEW'));
    }

    public function test_service_enforces_context_even_when_both_assignments_exist(): void
    {
        $this->assign($this->roleId('SYSTEM_ADMIN'), null);
        $this->assign($this->roleId('SCHOOL_ADMIN'), $this->schoolId);
        $service = new AuthorizationService(new AuthorizationRepository($this->pdo));

        self::assertTrue($service->hasPermission($this->userId, 'SYSTEM', null, 'SYSTEM_SCHOOL_VIEW'));
        self::assertTrue($service->hasPermission($this->userId, 'SCHOOL', $this->schoolId, 'SCHOOL_USER_VIEW'));
        self::assertFalse($service->hasPermission($this->userId, 'SYSTEM', $this->schoolId, 'SYSTEM_SCHOOL_VIEW'));
        self::assertFalse($service->hasPermission($this->userId, 'SCHOOL', null, 'SCHOOL_USER_VIEW'));
        self::assertFalse($service->hasPermission($this->userId, 'UNKNOWN', null, 'SYSTEM_SCHOOL_VIEW'));
        self::assertFalse($service->hasPermission($this->userId, 'SYSTEM', null, 'SCHOOL_USER_VIEW'));
        self::assertFalse($service->hasPermission($this->userId, 'SCHOOL', $this->schoolId, 'SYSTEM_SCHOOL_VIEW'));
    }

    #[DataProvider('incompleteIdentities')]
    public function test_incomplete_middleware_identity_redirects_without_calling_next(array $identity): void
    {
        $_SESSION = $identity;

        $response = $this->middleware('SCHOOL_USER_VIEW')->handle($this->request(), static function (): Response {
            self::fail('Incomplete identity must not reach the handler.');
        });

        self::assertEquals(Response::redirect('/login'), $response);
    }

    public static function incompleteIdentities(): array
    {
        return [
            'guest' => [[]],
            'missing user' => [['context_type' => 'SYSTEM']],
            'missing context' => [['user_id' => 1]],
            'malformed user' => [['user_id' => '1', 'context_type' => 'SYSTEM']],
            'malformed context' => [['user_id' => 1, 'context_type' => []]],
            'school context missing school' => [['user_id' => 1, 'context_type' => 'SCHOOL']],
            'school context malformed school' => [['user_id' => 1, 'context_type' => 'SCHOOL', 'school_id' => []]],
        ];
    }

    public function test_denied_permission_renders_friendly_403_without_calling_next(): void
    {
        $_SESSION = ['user_id' => $this->userId, 'context_type' => 'SCHOOL', 'school_id' => $this->schoolId];
        $this->assign($this->roleId('VIEWER'), $this->schoolId);

        $response = $this->middleware('SCHOOL_USER_VIEW')->handle($this->request(), static function (): Response {
            self::fail('Denied permission must not reach the handler.');
        });

        self::assertSame(403, $response->status());
        self::assertStringContainsString('ไม่มีสิทธิ์เข้าใช้งาน', $response->body());
    }

    public function test_allowed_school_permission_calls_next_using_session_not_browser_school(): void
    {
        $_SESSION = ['user_id' => $this->userId, 'context_type' => 'SCHOOL', 'school_id' => $this->schoolId];
        $this->assign($this->roleId('SCHOOL_ADMIN'), $this->schoolId);
        $request = new Request('GET', '/admin/users', ['school_id' => $this->otherSchoolId], ['school_id' => $this->otherSchoolId], []);
        $expected = new Response('Protected handler', 202);

        $response = $this->middleware('SCHOOL_USER_VIEW')->handle($request, static function (Request $actual) use ($request, $expected): Response {
            self::assertSame($request, $actual);
            return $expected;
        });

        self::assertSame($expected, $response);
    }

    public function test_browser_school_cannot_rescue_unauthorized_session_school(): void
    {
        $_SESSION = ['user_id' => $this->userId, 'context_type' => 'SCHOOL', 'school_id' => $this->otherSchoolId];
        $this->assign($this->roleId('SCHOOL_ADMIN'), $this->schoolId);
        $request = new Request('GET', '/admin/users', ['school_id' => $this->schoolId], ['school_id' => $this->schoolId], []);

        $response = $this->middleware('SCHOOL_USER_VIEW')->handle($request, static function (): Response {
            self::fail('Browser input cannot authorize a different session school.');
        });

        self::assertSame(403, $response->status());
    }

    public function test_system_permission_needs_no_school_key(): void
    {
        $_SESSION = ['user_id' => $this->userId, 'context_type' => 'SYSTEM'];
        $this->assign($this->roleId('SYSTEM_ADMIN'), null);
        $expected = new Response('System handler');

        self::assertSame($expected, $this->middleware('SYSTEM_SCHOOL_VIEW')->handle($this->request(), static fn (): Response => $expected));
    }

    public function test_system_context_with_injected_school_is_forbidden(): void
    {
        $_SESSION = ['user_id' => $this->userId, 'context_type' => 'SYSTEM', 'school_id' => $this->schoolId];
        $this->assign($this->roleId('SYSTEM_ADMIN'), null);

        $response = $this->middleware('SYSTEM_SCHOOL_VIEW')->handle($this->request(), static function (): Response {
            self::fail('SYSTEM context must not accept an injected school.');
        });

        self::assertSame(403, $response->status());
    }

    private function middleware(string $permission): PermissionMiddleware
    {
        return new PermissionMiddleware(new Session(), new AuthorizationService(new AuthorizationRepository($this->pdo)), $permission);
    }

    private function request(): Request
    {
        return new Request('GET', '/protected', [], [], []);
    }

    private function roleId(string $code): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM roles WHERE code = ?');
        $statement->execute([$code]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Run the role/permission seed on pp5_test before testing.');
        }
        return (int) $id;
    }

    private function assign(int $roleId, ?int $schoolId, string $status = 'ACTIVE', ?int $yearId = null): int
    {
        return $this->insert('INSERT INTO user_role_assignments (user_id, school_id, role_id, status, academic_year_id) VALUES (?, ?, ?, ?, ?)',
            [$this->userId, $schoolId, $roleId, $status, $yearId]);
    }

    private function grant(int $roleId, string $permission): void
    {
        $this->pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE code = ?')
            ->execute([$roleId, $permission]);
    }

    private function insert(string $sql, array $parameters): int
    {
        $this->pdo->prepare($sql)->execute($parameters);
        return (int) $this->pdo->lastInsertId();
    }
}
