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

final class AcademicYearHttpTest extends TestCase
{
    private AcademicYearHttpTestPDO $pdo;
    private Application $app;
    private int $schoolId;
    private int $foreignSchoolId;
    private int $actorId;
    private int $viewerId;
    private int $academicId;
    private int $systemId;
    private int $yearId;
    private int $foreignYearId;
    private const HOSTILE = '<b>"XSS"</b>';

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new AcademicYearHttpTestPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->schoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['year-http-a', 'School A']);
        $this->foreignSchoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['year-http-b', 'Foreign School Secret']);
        $this->actorId = $this->user('year-http-admin', $this->schoolId, 'SCHOOL_ADMIN');
        $this->viewerId = $this->user('year-http-viewer', $this->schoolId, 'VIEWER');
        $this->academicId = $this->user('year-http-academic', $this->schoolId, 'ACADEMIC_ADMIN');
        $this->systemId = $this->user('year-http-system', null, 'SYSTEM_ADMIN');
        $this->yearId = $this->year($this->schoolId, 2569);
        $this->foreignYearId = $this->year($this->foreignSchoolId, 2699);
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
            'index' => ['GET', '/academic/years', 'ACADEMIC_SETUP_VIEW'],
            'create form' => ['GET', '/academic/years/create', 'ACADEMIC_YEAR_MANAGE'],
            'store' => ['POST', '/academic/years', 'ACADEMIC_YEAR_MANAGE'],
            'edit' => ['GET', '/academic/years/{id}/edit', 'ACADEMIC_YEAR_MANAGE'],
            'update' => ['POST', '/academic/years/{id}', 'ACADEMIC_YEAR_MANAGE'],
            'status' => ['POST', '/academic/years/{id}/status', 'ACADEMIC_YEAR_MANAGE'],
        ];
    }

    #[DataProvider('routes')]
    public function test_route_metadata_and_numeric_target_contract(string $method, string $path, string $permission): void
    {
        $dispatcher = simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php');
        $route = $dispatcher->dispatch($method, $this->path($path));
        self::assertSame(Dispatcher::FOUND, $route[0], 'Task 3 academic route is missing.');
        self::assertTrue($route[1]['protected']);
        self::assertSame('SCHOOL', $route[1]['context']);
        self::assertSame($permission, $route[1]['permission']);
        self::assertArrayNotHasKey('school_id', $route[2]);
        if (str_contains($path, '{id}')) {
            self::assertSame((string) $this->yearId, $route[2]['id']);
            self::assertSame(Dispatcher::NOT_FOUND, $dispatcher->dispatch($method, str_replace('{id}', 'abc', $path))[0]);
        }
    }

    #[DataProvider('routes')]
    public function test_guest_redirects_before_permission_csrf_or_mutation(string $method, string $path, string $permission): void
    {
        $before = $this->snapshot();
        self::assertEquals(Response::redirect('/login'), $this->request($method, $this->path($path)));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('routes')]
    public function test_system_context_is_denied_before_permission_lookup_even_with_mapping(string $method, string $path, string $permission): void
    {
        $this->grant('SYSTEM_ADMIN', $permission);
        $_SESSION = ['user_id' => $this->systemId, 'context_type' => 'SYSTEM'];
        $before = $this->snapshot();
        $this->pdo->failPrepare = 'FROM user_role_assignments ura';
        self::assertSame(403, $this->request($method, $this->path($path), $this->payload())->status());
        self::assertFalse($this->pdo->failureTriggered, 'SchoolContext must reject before PermissionMiddleware queries assignments.');
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('routes')]
    public function test_school_admin_requires_exact_permission_on_each_route(string $method, string $path, string $permission): void
    {
        $this->schoolSession();
        $this->pdo->prepare("DELETE rp FROM role_permissions rp JOIN roles r ON r.id = rp.role_id
            JOIN permissions p ON p.id = rp.permission_id WHERE r.code = 'SCHOOL_ADMIN' AND p.code = ?")->execute([$permission]);
        $before = $this->snapshot();
        self::assertSame(403, $this->request($method, $this->path($path), $this->payload())->status());
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('routes')]
    public function test_role_without_permission_is_denied_but_exact_mapping_grants_access(string $method, string $path, string $permission): void
    {
        $this->schoolSession($this->viewerId);
        $before = $this->snapshot();
        self::assertSame(403, $this->request($method, $this->path($path), $this->payload())->status());
        self::assertSame($before, $this->snapshot());
        $this->grant('VIEWER', $permission);
        self::assertSame($method === 'GET' ? 200 : 302, $this->request($method, $this->path($path), $this->payload())->status());
    }

    #[DataProvider('routes')]
    public function test_academic_admin_can_access_all_six_routes(string $method, string $path, string $permission): void
    {
        $this->schoolSession($this->academicId);
        self::assertSame($method === 'GET' ? 200 : 302, $this->request($method, $this->path($path), $this->payload())->status());
    }

    #[DataProvider('routes')]
    public function test_tampered_session_school_is_denied_even_when_browser_restores_real_school(string $method, string $path, string $permission): void
    {
        $this->schoolSession();
        $_SESSION['school_id'] = $this->foreignSchoolId;
        $before = $this->snapshot();
        self::assertSame(403, $this->request($method, $this->path($path), $this->payload(['school_id' => $this->schoolId]), ['school_id' => $this->schoolId])->status());
        self::assertSame($before, $this->snapshot());
    }

    public function test_list_is_scoped_ordered_and_ignores_browser_school(): void
    {
        $this->schoolSession();
        $newer = $this->year($this->schoolId, 2570);
        $response = $this->request('GET', '/academic/years', ['school_id' => $this->foreignSchoolId], ['school_id' => $this->foreignSchoolId]);
        self::assertSame(200, $response->status());
        $xpath = $this->xpath($response->body());
        $cells = $xpath->query('//tbody/tr/td[1]');
        self::assertSame(['2570', '2569'], array_map(static fn (DOMNode $node): string => trim($node->textContent), iterator_to_array($cells)));
        foreach (['2569', '2026-05-16', '2027-03-31', 'DRAFT'] as $value) {
            self::assertStringContainsString($value, $response->body());
        }
        self::assertStringNotContainsString('2699', $response->body());
        self::assertStringNotContainsString('Foreign School Secret', $response->body());
        self::assertSame(1, $xpath->query('//a[@href="/academic/years/' . $newer . '/edit"]')->length);
        $this->assertSafe($response);
    }

    public function test_create_form_has_only_business_fields_and_current_csrf_token(): void
    {
        $this->schoolSession();
        $response = $this->request('GET', '/academic/years/create');
        self::assertSame(200, $response->status());
        $xpath = $this->xpath($response->body());
        $names = array_map(static fn (DOMNode $node): string => $node->nodeValue, iterator_to_array($xpath->query('//form//*[@name]/@name')));
        self::assertEqualsCanonicalizing(['year_be', 'start_date', 'end_date', '_token'], $names);
        self::assertSame(1, $xpath->query('//input[@name="start_date" and @type="date"]')->length);
        self::assertSame(1, $xpath->query('//input[@name="end_date" and @type="date"]')->length);
        $this->assertForms($response);
        self::assertStringContainsString('พ.ศ.', $response->body());
        self::assertStringContainsString('ค.ศ.', $response->body());
    }

    #[DataProvider('editStates')]
    public function test_edit_controls_follow_state_and_use_session_token(string $status, bool $editable, ?string $next): void
    {
        $this->schoolSession();
        $this->pdo->prepare('UPDATE academic_years SET status = ? WHERE id = ?')->execute([$status, $this->yearId]);
        $response = $this->request('GET', $this->path('/academic/years/{id}/edit'));
        self::assertSame(200, $response->status());
        $xpath = $this->xpath($response->body());
        foreach (['year_be', 'start_date', 'end_date'] as $field) {
            self::assertSame($editable ? 1 : 0, $xpath->query('//input[@name="' . $field . '" and not(@readonly) and not(@disabled)]')->length);
        }
        self::assertSame($editable ? 1 : 0, $xpath->query('//form[@action="' . $this->path('/academic/years/{id}') . '"]')->length);
        $statuses = $xpath->query('//*[@name="status"]/@value');
        self::assertSame($next === null ? [] : [$next], array_map(static fn (DOMNode $node): string => $node->nodeValue, iterator_to_array($statuses)));
        foreach (['2569', '2026-05-16', '2027-03-31', $status] as $value) {
            self::assertStringContainsString($value, $response->body());
        }
        if ($status === 'CLOSED') {
            self::assertSame(0, $xpath->query('//form')->length);
        }
        $this->assertForms($response);
    }

    public static function editStates(): array
    {
        return [['DRAFT', true, 'ACTIVE'], ['ACTIVE', false, 'CLOSED'], ['CLOSED', false, null]];
    }

    public function test_foreign_and_missing_edit_are_identical_404_even_with_forged_school(): void
    {
        $this->schoolSession();
        $foreign = $this->request('GET', $this->path('/academic/years/{id}/edit', $this->foreignYearId), [], ['school_id' => $this->foreignSchoolId]);
        $missing = $this->request('GET', '/academic/years/0/edit');
        self::assertSame(404, $foreign->status());
        self::assertEquals($missing, $foreign);
        $this->assertSafe($foreign);
        self::assertStringNotContainsString('2699', $foreign->body());
    }

    public static function mutations(): array
    {
        return [['/academic/years'], ['/academic/years/{id}'], ['/academic/years/{id}/status']];
    }

    #[DataProvider('mutations')]
    public function test_missing_bad_and_array_csrf_prevent_all_writes_before_input_validation(string $path): void
    {
        $this->schoolSession();
        $before = $this->snapshot();
        foreach ([null, 'bad-token', ['bad'], new stdClass()] as $token) {
            $this->pdo->writeAttempts = 0;
            $response = $this->request('POST', $this->path($path), $this->payload(['_token' => $token, 'year_be' => ['bad'], 'status' => ['bad']]));
            self::assertSame(419, $response->status());
            self::assertSame('CSRF token mismatch', $response->body());
            self::assertSame(0, $this->pdo->writeAttempts);
            self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('mutations')]
    public function test_successful_mutations_use_only_session_actor_school_and_server_ip(string $path): void
    {
        $this->schoolSession();
        $foreignBefore = $this->row('SELECT * FROM academic_years WHERE id = ?', [$this->foreignYearId]);
        $forged = ['school_id' => $this->foreignSchoolId, 'user_id' => $this->systemId, 'actor_user_id' => $this->systemId,
            'created_by' => $this->systemId, 'actor' => $this->systemId, 'ip_address' => '203.0.113.8', 'REMOTE_ADDR' => '203.0.113.9'];
        $payload = $this->payload($forged);
        if ($path === '/academic/years') {
            $payload['status'] = 'ACTIVE';
        }
        $response = $this->request('POST', $this->path($path), $payload, $forged, ['REMOTE_ADDR' => '192.0.2.30', 'HTTP_X_FORWARDED_FOR' => '203.0.113.10']);
        self::assertEquals(Response::redirect($path === '/academic/years' ? '/academic/years' : $this->path('/academic/years/{id}/edit')), $response);
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame($this->schoolId, $audit['school_id']);
        self::assertSame($this->actorId, $audit['user_id']);
        self::assertSame('academic_years', $audit['entity_type']);
        self::assertSame('192.0.2.30', $audit['ip_address']);
        self::assertSame(match ($path) {
            '/academic/years' => 'ACADEMIC_YEAR_CREATED', '/academic/years/{id}' => 'ACADEMIC_YEAR_UPDATED', default => 'ACADEMIC_YEAR_STATUS_CHANGED',
        }, $audit['action']);
        $year = $this->row('SELECT * FROM academic_years WHERE id = ?', [$audit['entity_id']]);
        self::assertSame($this->schoolId, $year['school_id']);
        self::assertSame($path === '/academic/years/{id}/status' ? 'ACTIVE' : 'DRAFT', $year['status']);
        self::assertSame($path === '/academic/years/{id}/status' ? 2569 : 2570, $year['year_be']);
        self::assertSame($foreignBefore, $this->row('SELECT * FROM academic_years WHERE id = ?', [$this->foreignYearId]));
    }

    #[DataProvider('invalidDetails')]
    public function test_invalid_form_representation_and_domain_values_return_safe_422(string $field, mixed $value): void
    {
        $this->schoolSession();
        $before = $this->snapshot();
        foreach (['/academic/years', $this->path('/academic/years/{id}')] as $path) {
            $response = $this->request('POST', $path, $this->payload([$field => $value]));
            self::assertSame(422, $response->status());
            $this->assertSafe($response);
            self::assertSame($before, $this->snapshot());
        }
    }

    public static function invalidDetails(): array
    {
        return [
            'year suffix' => ['year_be', '2570abc'], 'year decimal' => ['year_be', '2570.5'],
            'year scientific' => ['year_be', '2.570e3'], 'year array' => ['year_be', ['2570']],
            'year object' => ['year_be', new stdClass()], 'empty year' => ['year_be', ''],
            'missing year' => ['year_be', null], 'boolean year' => ['year_be', true],
            'overflow year' => ['year_be', '99999999999999999999999999'],
            'below range' => ['year_be', '2399'], 'above range' => ['year_be', '2701'],
            'start array' => ['start_date', ['2027-05-16']], 'end array' => ['end_date', ['2028-03-31']],
            'start object' => ['start_date', new stdClass()], 'end object' => ['end_date', new stdClass()],
            'numeric date' => ['start_date', 20270516], 'boolean date' => ['end_date', false],
            'local date' => ['start_date', '16/05/2027'], 'impossible date' => ['start_date', '2027-02-30'],
            'Buddhist date' => ['start_date', '2570-05-16'], 'wrong start year' => ['start_date', '2026-05-16'],
            'wrong end year' => ['end_date', '2029-03-31'], 'wrong order' => ['end_date', '2027-01-01'],
            'hostile year' => ['year_be', self::HOSTILE], 'hostile date' => ['start_date', self::HOSTILE],
        ];
    }

    #[DataProvider('blankDates')]
    public function test_draft_blank_and_missing_dates_remain_nullable(?string $start, ?string $end): void
    {
        $this->schoolSession();
        $response = $this->request('POST', '/academic/years', $this->payload(['start_date' => $start, 'end_date' => $end]));
        self::assertEquals(Response::redirect('/academic/years'), $response);
        $row = $this->row('SELECT * FROM academic_years WHERE school_id = ? AND year_be = 2570', [$this->schoolId]);
        self::assertNull($row['start_date']);
        self::assertNull($row['end_date']);
        self::assertSame('DRAFT', $row['status']);
    }

    public static function blankDates(): array
    {
        return [[null, null], ['', " \t\n "]];
    }

    public function test_duplicate_create_and_update_are_safe_and_atomic(): void
    {
        $this->schoolSession();
        $this->year($this->schoolId, 2570);
        $before = $this->snapshot();
        foreach (['/academic/years', $this->path('/academic/years/{id}')] as $path) {
            $response = $this->request('POST', $path, $this->payload());
            self::assertSame(422, $response->status());
            self::assertStringContainsString('ปีการศึกษานี้มีอยู่แล้ว', $response->body());
            $this->assertSafe($response);
            self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('targetMutations')]
    public function test_foreign_missing_and_overflow_targets_are_indistinguishable_and_unchanged(string $path): void
    {
        $this->schoolSession();
        $before = $this->snapshot();
        $responses = [];
        foreach ([(string) $this->foreignYearId, '0', '999999999999999999999999'] as $target) {
            $response = $this->request('POST', str_replace('{id}', $target, $path), $this->payload(['school_id' => $this->foreignSchoolId]), ['school_id' => $this->foreignSchoolId]);
            self::assertSame(422, $response->status());
            $this->assertSafe($response);
            self::assertStringNotContainsString('2699', $response->body());
            self::assertSame(0, $this->xpath($response->body())->query('//form')->length);
            self::assertSame($before, $this->snapshot());
            $responses[] = $response;
        }
        self::assertEquals($responses[0], $responses[1]);
        self::assertEquals($responses[0], $responses[2]);
    }

    public static function targetMutations(): array
    {
        return [['/academic/years/{id}'], ['/academic/years/{id}/status']];
    }

    #[DataProvider('immutableStates')]
    public function test_active_closed_detail_updates_return_422_and_no_audit(string $status): void
    {
        $this->schoolSession();
        $this->pdo->prepare('UPDATE academic_years SET status = ? WHERE id = ?')->execute([$status, $this->yearId]);
        $before = $this->snapshot();
        $response = $this->request('POST', $this->path('/academic/years/{id}'), $this->payload());
        self::assertSame(422, $response->status());
        $this->assertSafe($response);
        self::assertSame($before, $this->snapshot());
    }

    public static function immutableStates(): array
    {
        return [['ACTIVE'], ['CLOSED']];
    }

    public function test_draft_active_closed_flow_and_same_status_no_op(): void
    {
        $this->schoolSession();
        foreach (['DRAFT', 'ACTIVE', 'CLOSED'] as $status) {
            $path = $this->path('/academic/years/{id}/status');
            self::assertEquals(Response::redirect($this->path('/academic/years/{id}/edit')), $this->request('POST', $path, $this->payload(['status' => $status])));
            self::assertSame($status, $this->row('SELECT status FROM academic_years WHERE id = ?', [$this->yearId])['status']);
            $before = $this->snapshot();
            $this->pdo->writeAttempts = 0;
            self::assertSame(302, $this->request('POST', $path, $this->payload(['status' => $status]))->status());
            self::assertSame(0, $this->pdo->writeAttempts);
            self::assertSame($before, $this->snapshot());
        }
        self::assertCount(2, $this->rows('SELECT * FROM audit_logs'));
    }

    #[DataProvider('invalidStates')]
    public function test_invalid_transitions_and_status_types_return_422(string $old, mixed $new): void
    {
        $this->schoolSession();
        $this->pdo->prepare('UPDATE academic_years SET status = ? WHERE id = ?')->execute([$old, $this->yearId]);
        $before = $this->snapshot();
        $response = $this->request('POST', $this->path('/academic/years/{id}/status'), $this->payload(['status' => $new]));
        self::assertSame(422, $response->status());
        $this->assertSafe($response);
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidStates(): array
    {
        return [['DRAFT', 'CLOSED'], ['ACTIVE', 'DRAFT'], ['CLOSED', 'ACTIVE'], ['CLOSED', 'DRAFT'],
            ['DRAFT', 'UNKNOWN'], ['DRAFT', ['ACTIVE']], ['DRAFT', new stdClass()], ['DRAFT', null], ['DRAFT', 1]];
    }

    #[DataProvider('missingActivationDates')]
    public function test_activation_requires_complete_dates_over_http(?string $start, ?string $end): void
    {
        $this->schoolSession();
        $this->pdo->prepare('UPDATE academic_years SET start_date = ?, end_date = ? WHERE id = ?')->execute([$start, $end, $this->yearId]);
        $before = $this->snapshot();
        $response = $this->request('POST', $this->path('/academic/years/{id}/status'), $this->payload());
        self::assertSame(422, $response->status());
        self::assertSame($before, $this->snapshot());
    }

    public static function missingActivationDates(): array
    {
        return [[null, null], ['2026-05-16', null], [null, '2027-03-31']];
    }

    public function test_exact_draft_update_is_no_op(): void
    {
        $this->schoolSession();
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $response = $this->request('POST', $this->path('/academic/years/{id}'), $this->payload(['year_be' => '2569', 'start_date' => '2026-05-16', 'end_date' => '2027-03-31']));
        self::assertEquals(Response::redirect($this->path('/academic/years/{id}/edit')), $response);
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }

    public function test_hostile_database_and_redisplayed_form_values_are_escaped(): void
    {
        $this->schoolSession();
        $_SESSION['csrf_token'] = '" onfocus="alert(1)';
        $this->pdo->prepare('UPDATE academic_years SET status = ? WHERE id = ?')->execute([self::HOSTILE, $this->yearId]);
        foreach (['/academic/years', $this->path('/academic/years/{id}/edit')] as $path) {
            $response = $this->request('GET', $path);
            self::assertSame(200, $response->status());
            self::assertStringContainsString(htmlspecialchars(self::HOSTILE, ENT_QUOTES, 'UTF-8'), $response->body());
            self::assertStringNotContainsString(self::HOSTILE, $response->body());
        }
        $response = $this->request('POST', '/academic/years', $this->payload(['year_be' => self::HOSTILE, 'start_date' => self::HOSTILE]));
        self::assertSame(422, $response->status());
        self::assertStringContainsString(htmlspecialchars(self::HOSTILE, ENT_QUOTES, 'UTF-8'), $response->body());
        self::assertStringNotContainsString(self::HOSTILE, $response->body());
        self::assertStringContainsString('&quot; onfocus=&quot;alert(1)', $response->body());
        self::assertSame(0, $this->xpath($response->body())->query('//*[@onfocus]')->length);
    }

    #[DataProvider('mutations')]
    public function test_domain_write_and_audit_failures_return_sanitized_422_and_rollback(string $path): void
    {
        $this->schoolSession();
        foreach (['INSERT INTO audit_logs', $path === '/academic/years' ? 'INSERT INTO academic_years' : 'UPDATE academic_years'] as $query) {
            $before = $this->snapshot();
            $this->pdo->failPrepare = $query;
            $this->pdo->failureTriggered = false;
            $response = $this->request('POST', $this->path($path), $this->payload());
            self::assertSame(422, $response->status());
            self::assertTrue($this->pdo->failureTriggered);
            $this->assertSafe($response);
            self::assertSame($before, $this->snapshot());
            self::assertSame(1, $this->pdo->depth);
        }
    }

    #[DataProvider('ipAddresses')]
    public function test_audit_uses_only_valid_remote_addr(mixed $address, ?string $expected): void
    {
        $this->schoolSession();
        $response = $this->request('POST', '/academic/years', $this->payload(['REMOTE_ADDR' => '203.0.113.1']), ['REMOTE_ADDR' => '203.0.113.2'], ['REMOTE_ADDR' => $address, 'HTTP_X_FORWARDED_FOR' => '203.0.113.3']);
        self::assertSame(302, $response->status());
        self::assertSame($expected, $this->row('SELECT ip_address FROM audit_logs')['ip_address']);
    }

    public static function ipAddresses(): array
    {
        return [['192.0.2.1', '192.0.2.1'], ['2001:db8::1', '2001:db8::1'], [null, null], ['invalid', null], [['bad'], null]];
    }

    private function schoolSession(?int $userId = null): void
    {
        $userId ??= $this->actorId;
        $membership = $this->row('SELECT id FROM school_memberships WHERE user_id = ? AND school_id = ?', [$userId, $this->schoolId]);
        $_SESSION = ['user_id' => $userId, 'context_type' => 'SCHOOL', 'school_id' => $this->schoolId,
            'school_membership_id' => $membership['id'], 'display_name' => 'Academic Admin', 'last_activity' => time()];
        $this->token();
    }

    private function user(string $username, ?int $schoolId, string $role): int
    {
        $id = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', [$username, 'unused-fixture-hash', $username]);
        if ($schoolId !== null) {
            $this->insert('INSERT INTO school_memberships (user_id, school_id) VALUES (?, ?)', [$id, $schoolId]);
        }
        $this->insert('INSERT INTO user_role_assignments (user_id, school_id, role_id) SELECT ?, ?, id FROM roles WHERE code = ?', [$id, $schoolId, $role]);
        return $id;
    }

    private function year(int $schoolId, int $year): int
    {
        return $this->insert('INSERT INTO academic_years (school_id, year_be, start_date, end_date) VALUES (?, ?, ?, ?)',
            [$schoolId, $year, ($year - 543) . '-05-16', ($year - 542) . '-03-31']);
    }

    private function grant(string $role, string $permission): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = ? AND p.code = ?')->execute([$role, $permission]);
    }

    private function token(): string
    {
        return (new Csrf())->token(new Session());
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['_token' => $this->token(), 'year_be' => '2570', 'start_date' => '2027-05-16',
            'end_date' => '2028-03-31', 'status' => 'ACTIVE'], $overrides);
    }

    private function path(string $path, ?int $id = null): string
    {
        return str_replace('{id}', (string) ($id ?? $this->yearId), $path);
    }

    private function request(string $method, string $path, array $post = [], array $query = [], array $server = []): Response
    {
        return $this->app->handle(new Request($method, $path, $query, $post, $server));
    }

    private function assertSafe(Response $response): void
    {
        foreach (['SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', '/Applications/MAMP/', 'htdocs/app/', 'private-db-details'] as $unsafe) {
            self::assertStringNotContainsString($unsafe, $response->body());
        }
    }

    private function assertForms(Response $response): void
    {
        $xpath = $this->xpath($response->body());
        self::assertSame(0, $xpath->query('//*[@name="school_id" or @name="actor_user_id" or @name="user_id"]')->length);
        foreach ($xpath->query('//form[@method="post"]') as $form) {
            self::assertSame($this->token(), $xpath->evaluate('string(.//input[@name="_token"]/@value)', $form));
        }
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

    private function snapshot(): array
    {
        return ['years' => $this->rows('SELECT * FROM academic_years ORDER BY id'), 'audit' => $this->rows('SELECT * FROM audit_logs ORDER BY id')];
    }

    private function rows(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    private function row(string $sql, array $parameters = []): array
    {
        $rows = $this->rows($sql, $parameters);
        self::assertCount(1, $rows);
        return $rows[0];
    }

    private function insert(string $sql, array $parameters): int
    {
        $this->pdo->prepare($sql)->execute($parameters);
        return (int) $this->pdo->lastInsertId();
    }
}

/** Use real savepoints so HTTP service commits remain inside fixture rollback. */
final class AcademicYearHttpTestPDO extends PDO
{
    public int $depth = 0;
    public int $writeAttempts = 0;
    public ?string $failPrepare = null;
    public bool $failureTriggered = false;

    public function beginTransaction(): bool
    {
        $result = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT year_http_' . $this->depth) !== false;
        ++$this->depth;
        return $result;
    }

    public function commit(): bool
    {
        $result = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT year_http_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $result;
    }

    public function rollBack(): bool
    {
        if ($this->depth === 1) {
            $result = parent::rollBack();
        } else {
            $result = $this->exec('ROLLBACK TO SAVEPOINT year_http_' . ($this->depth - 1)) !== false;
            $this->exec('RELEASE SAVEPOINT year_http_' . ($this->depth - 1));
        }
        --$this->depth;
        return $result;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $query)) {
            ++$this->writeAttempts;
        }
        if ($this->failPrepare !== null && str_contains($query, $this->failPrepare)) {
            $this->failPrepare = null;
            $this->failureTriggered = true;
            throw new PDOException('SQLSTATE private-db-details SELECT /Applications/MAMP/htdocs/app/secret.php');
        }
        return parent::prepare($query, $options);
    }
}
