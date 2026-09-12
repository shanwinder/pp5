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

final class ClassroomHttpTest extends TestCase
{
    private ClassroomHttpPDO $pdo;
    private Application $app;
    private int $school;
    private int $foreignSchool;
    private int $year;
    private int $activeYear;
    private int $closedYear;
    private int $foreignYear;
    private int $room;
    private int $foreignRoom;
    private int $grade;
    private int $inactiveGrade;
    private array $users;
    private const HOSTILE = '<b>"XSS"</b>';

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new ClassroomHttpPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->school = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['room-http-a', 'School A']);
        $this->foreignSchool = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['room-http-b', 'Foreign School Secret']);
        $this->year = $this->fixtureYear($this->school, 2569, 'DRAFT');
        $this->activeYear = $this->fixtureYear($this->school, 2570, 'ACTIVE');
        $this->closedYear = $this->fixtureYear($this->school, 2568, 'CLOSED');
        $this->foreignYear = $this->fixtureYear($this->foreignSchool, 2699, 'DRAFT');
        $this->grade = $this->row("SELECT id FROM grade_levels WHERE code = 'P1'")['id'];
        $this->inactiveGrade = $this->row("SELECT id FROM grade_levels WHERE code = 'P6'")['id'];
        $this->pdo->prepare("UPDATE grade_levels SET status = 'INACTIVE' WHERE id = ?")->execute([$this->inactiveGrade]);
        $this->room = $this->fixtureRoom($this->school, $this->year, 'OWN');
        $this->foreignRoom = $this->fixtureRoom($this->foreignSchool, $this->foreignYear, 'FOREIGN_SECRET');
        foreach (['SCHOOL_ADMIN', 'ACADEMIC_ADMIN', 'VIEWER', 'SYSTEM_ADMIN'] as $role) {
            $id = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['room-http-' . $role, 'unused', $role]);
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
        return [['GET', '/academic/classrooms', 'ACADEMIC_SETUP_VIEW'], ['GET', '/academic/classrooms/create', 'CLASSROOM_MANAGE'],
            ['POST', '/academic/classrooms', 'CLASSROOM_MANAGE'], ['GET', '/academic/classrooms/{id}/edit', 'CLASSROOM_MANAGE'],
            ['POST', '/academic/classrooms/{id}', 'CLASSROOM_MANAGE'], ['POST', '/academic/classrooms/{id}/status', 'CLASSROOM_MANAGE']];
    }

    #[DataProvider('routes')]
    public function test_exact_route_metadata_and_numeric_ids(string $method, string $path, string $permission): void
    {
        $dispatcher = simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php');
        $route = $dispatcher->dispatch($method, $this->path($path));
        self::assertSame(Dispatcher::FOUND, $route[0], 'Task 4 classroom route missing');
        self::assertTrue($route[1]['protected']);
        self::assertSame('SCHOOL', $route[1]['context']);
        self::assertSame($permission, $route[1]['permission']);
        self::assertArrayNotHasKey('school_id', $route[2]);
        if (str_contains($path, '{id}')) {
            self::assertSame((string) $this->room, $route[2]['id']);
            self::assertSame(Dispatcher::NOT_FOUND, $dispatcher->dispatch($method, str_replace('{id}', 'abc', $path))[0]);
        }
    }

    #[DataProvider('routes')]
    public function test_guest_redirect_and_system_context_denial_precede_permission_and_csrf(string $method, string $path, string $permission): void
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
    public function test_school_and_academic_admin_access_depends_on_exact_mapping(string $method, string $path, string $permission): void
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
    public function test_viewer_denied_until_exact_permission_granted(string $method, string $path, string $permission): void
    {
        $this->login('VIEWER');
        $before = $this->snapshot();
        self::assertSame(403, $this->request($method, $this->path($path), $this->payload())->status());
        self::assertSame($before, $this->snapshot());
        $this->grant('VIEWER', $permission);
        self::assertSame($method === 'GET' ? 200 : 302, $this->request($method, $this->path($path), $this->payload())->status());
    }

    public function test_request_query_accessor_returns_raw_values_and_preserves_post_server_contracts(): void
    {
        self::assertTrue(method_exists(Request::class, 'query'), 'Request::query missing');
        foreach (['123', 123, ['123'], new stdClass(), false, '', '99999999999999999999999'] as $value) {
            $request = new Request('GET', '/', ['academic_year_id' => $value], ['academic_year_id' => 'post'], ['academic_year_id' => 'server']);
            self::assertSame($value, $request->query('academic_year_id'));
            self::assertSame('fallback', $request->query('missing', 'fallback'));
            self::assertSame('post', $request->post('academic_year_id'));
            self::assertSame('server', $request->server('academic_year_id'));
        }
        $request = new Request('GET', '/', ['x' => null], [], []);
        self::assertSame('fallback', $request->query('x', 'fallback'));
        self::assertNull($request->query('missing'));
    }

    public function test_list_is_school_scoped_ordered_and_supports_own_year_filter(): void
    {
        $this->login();
        $new = $this->fixtureRoom($this->school, $this->activeYear, 'NEWER');
        $all = $this->request('GET', '/academic/classrooms', [], ['school_id' => $this->foreignSchool]);
        self::assertSame(200, $all->status());
        $body = $all->body();
        self::assertLessThan(strpos($body, 'OWN'), strpos($body, 'NEWER'));
        foreach (['2569', '2570', 'ประถมศึกษาปีที่ 1', 'ชื่อห้อง', 'ACTIVE', '/academic/classrooms/' . $new . '/edit'] as $text) { self::assertStringContainsString($text, $body); }
        self::assertStringNotContainsString('FOREIGN_SECRET', $body);
        self::assertStringNotContainsString('2699', $body);
        $filtered = $this->request('GET', '/academic/classrooms', [], ['academic_year_id' => (string) $this->year, 'school_id' => $this->foreignSchool]);
        self::assertSame(200, $filtered->status());
        self::assertStringContainsString('OWN', $filtered->body());
        self::assertStringNotContainsString('NEWER', $filtered->body());
        $this->assertSafe($filtered);
    }

    public function test_create_options_are_own_open_years_active_grades_and_safe_preselection(): void
    {
        $this->login();
        foreach ([$this->year, $this->activeYear] as $year) {
            $response = $this->request('GET', '/academic/classrooms/create', [], ['academic_year_id' => (string) $year, 'school_id' => $this->foreignSchool]);
            self::assertSame(200, $response->status());
            $xpath = $this->xpath($response->body());
            $years = $xpath->query('//select[@name="academic_year_id"]/option[@value!=""]/@value');
            self::assertEqualsCanonicalizing([(string) $this->year, (string) $this->activeYear], $this->values($years));
            self::assertSame((string) $year, $xpath->evaluate('string(//select[@name="academic_year_id"]/option[@selected]/@value)'));
            self::assertSame(5, $xpath->query('//select[@name="grade_level_id"]/option[@value!=""]')->length);
            self::assertSame(0, $xpath->query('//select[@name="grade_level_id"]/option[@value="' . $this->inactiveGrade . '"]')->length);
            self::assertEqualsCanonicalizing(['academic_year_id', 'grade_level_id', 'code', 'name_th', '_token'], $this->values($xpath->query('//form//*[@name]/@name')));
            $this->assertForms($response);
        }
    }

    #[DataProvider('malformedIds')]
    public function test_malformed_year_query_is_safe_404_on_list_and_create(mixed $value): void
    {
        $this->login();
        foreach (['/academic/classrooms', '/academic/classrooms/create'] as $path) {
            $response = $this->request('GET', $path, [], ['academic_year_id' => $value]);
            self::assertSame(404, $response->status());
            $this->assertSafe($response);
            self::assertStringNotContainsString('OWN', $response->body());
        }
    }

    public static function malformedIds(): array
    {
        return [[['1']], [new stdClass()], [true], [false], ['123abc'], ['1.5'], [1.5], ['1e3'], [''], ['0'], ['-1'], ['999999999999999999999999']];
    }

    public function test_foreign_missing_year_filter_preselection_and_classroom_edit_are_indistinguishable(): void
    {
        $this->login();
        foreach (['/academic/classrooms', '/academic/classrooms/create'] as $path) {
            $foreign = $this->request('GET', $path, [], ['academic_year_id' => (string) $this->foreignYear, 'school_id' => $this->foreignSchool]);
            $missing = $this->request('GET', $path, [], ['academic_year_id' => '999999999']);
            self::assertSame(404, $foreign->status());
            self::assertEquals($missing, $foreign);
            $this->assertSafe($foreign);
        }
        $foreign = $this->request('GET', '/academic/classrooms/' . $this->foreignRoom . '/edit');
        self::assertSame(404, $foreign->status());
        self::assertEquals($this->request('GET', '/academic/classrooms/0/edit'), $foreign);
        self::assertStringNotContainsString('FOREIGN_SECRET', $foreign->body());
        self::assertSame(404, $this->request('GET', '/academic/classrooms/create', [], ['academic_year_id' => (string) $this->closedYear])->status());
        self::assertSame(200, $this->request('GET', '/academic/classrooms', [], ['academic_year_id' => (string) $this->closedYear])->status());
    }

    public static function mutations(): array { return [['/academic/classrooms'], ['/academic/classrooms/{id}'], ['/academic/classrooms/{id}/status']]; }

    #[DataProvider('mutations')]
    public function test_all_post_families_reject_csrf_before_malicious_input_without_writes(string $path): void
    {
        $this->login();
        $before = $this->snapshot();
        foreach ([null, 'bad', ['bad'], new stdClass()] as $token) {
            $this->pdo->writeAttempts = 0;
            $response = $this->request('POST', $this->path($path), $this->payload(['_token' => $token, 'grade_level_id' => ['bad'], 'code' => new stdClass(), 'status' => ['bad']]));
            self::assertSame(419, $response->status());
            self::assertSame('CSRF token mismatch', $response->body());
            self::assertSame(0, $this->pdo->writeAttempts);
            self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('mutations')]
    public function test_success_uses_session_actor_school_and_server_ip_ignoring_forgery(string $path): void
    {
        $this->login();
        $foreignBefore = $this->row('SELECT * FROM classrooms WHERE id = ?', [$this->foreignRoom]);
        $forged = ['school_id' => $this->foreignSchool, 'user_id' => $this->users['SYSTEM_ADMIN'], 'actor' => $this->users['SYSTEM_ADMIN'],
            'created_by' => $this->users['SYSTEM_ADMIN'], 'actor_user_id' => $this->users['SYSTEM_ADMIN'], 'ip_address' => '203.0.113.1'];
        $post = $this->payload($forged);
        if ($path !== '/academic/classrooms') { $post['academic_year_id'] = $this->foreignYear; }
        $response = $this->request('POST', $this->path($path), $post, $forged, ['REMOTE_ADDR' => '192.0.2.41', 'HTTP_X_FORWARDED_FOR' => '203.0.113.2']);
        self::assertEquals(Response::redirect($path === '/academic/classrooms' ? '/academic/classrooms' : $this->path('/academic/classrooms/{id}/edit')), $response);
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame($this->school, $audit['school_id']);
        self::assertSame($this->users['SCHOOL_ADMIN'], $audit['user_id']);
        self::assertSame('classrooms', $audit['entity_type']);
        self::assertSame('192.0.2.41', $audit['ip_address']);
        self::assertSame(match ($path) { '/academic/classrooms' => 'CLASSROOM_CREATED', '/academic/classrooms/{id}' => 'CLASSROOM_UPDATED', default => 'CLASSROOM_STATUS_CHANGED' }, $audit['action']);
        $row = $this->row('SELECT * FROM classrooms WHERE id = ?', [$audit['entity_id']]);
        self::assertSame($this->year, $row['academic_year_id']);
        self::assertSame($this->school, $row['school_id']);
        self::assertSame($path === '/academic/classrooms/{id}/status' ? 'INACTIVE' : 'ACTIVE', $row['status']);
        self::assertSame($foreignBefore, $this->row('SELECT * FROM classrooms WHERE id = ?', [$this->foreignRoom]));
    }

    #[DataProvider('invalidFormValues')]
    public function test_malformed_post_types_and_domain_values_are_safe_422(string $field, mixed $value): void
    {
        $this->login();
        $before = $this->snapshot();
        $paths = $field === 'academic_year_id' ? ['/academic/classrooms'] : ['/academic/classrooms', $this->path('/academic/classrooms/{id}')];
        foreach ($paths as $path) {
            $response = $this->request('POST', $path, $this->payload([$field => $value]));
            self::assertSame(422, $response->status());
            $this->assertSafe($response);
            self::assertSame($before, $this->snapshot());
        }
    }

    public static function invalidFormValues(): array
    {
        $cases = [];
        foreach (['academic_year_id', 'grade_level_id'] as $field) {
            foreach ([...array_column(self::malformedIds(), 0), null] as $i => $value) { $cases[$field . $i] = [$field, $value]; }
        }
        foreach (['code', 'name_th'] as $field) {
            foreach ([['bad'], new stdClass(), true, 1, null, '', '  ', "bad\0", "bad\n", str_repeat('ก', $field === 'code' ? 51 : 121)] as $i => $value) { $cases[$field . $i] = [$field, $value]; }
        }
        return $cases;
    }

    public function test_create_open_years_unicode_and_server_side_parent_checks(): void
    {
        $this->login();
        foreach ([$this->year, $this->activeYear] as $year) {
            self::assertEquals(Response::redirect('/academic/classrooms'), $this->request('POST', '/academic/classrooms', $this->payload(['academic_year_id' => (string) $year, 'code' => ' ป.4/1 '])));
            $row = $this->row('SELECT * FROM classrooms WHERE school_id = ? AND academic_year_id = ? AND code = ?', [$this->school, $year, 'ป.4/1']);
            self::assertSame('ACTIVE', $row['status']);
        }
        $before = $this->snapshot();
        foreach ([['academic_year_id' => (string) $this->closedYear], ['academic_year_id' => (string) $this->foreignYear],
            ['academic_year_id' => '999999999'], ['grade_level_id' => (string) $this->inactiveGrade], ['code' => 'OWN']] as $values) {
            $response = $this->request('POST', '/academic/classrooms', $this->payload($values));
            self::assertSame(422, $response->status());
            $this->assertSafe($response);
            self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('yearStates')]
    public function test_edit_details_and_status_controls_follow_owning_year(string $state): void
    {
        $this->login();
        $this->pdo->prepare('UPDATE academic_years SET status = ? WHERE id = ?')->execute([$state, $this->year]);
        foreach (['ACTIVE', 'INACTIVE'] as $roomStatus) {
            $this->pdo->prepare('UPDATE classrooms SET status = ? WHERE id = ?')->execute([$roomStatus, $this->room]);
            $response = $this->request('GET', $this->path('/academic/classrooms/{id}/edit'));
            self::assertSame(200, $response->status());
            $xpath = $this->xpath($response->body());
            self::assertSame(0, $xpath->query('//*[@name="academic_year_id"]')->length);
            foreach (['code', 'name_th', 'grade_level_id'] as $field) {
                self::assertSame($state === 'CLOSED' ? 0 : 1, $xpath->query('//*[@name="' . $field . '" and not(@readonly) and not(@disabled)]')->length);
            }
            if ($state === 'CLOSED') { self::assertSame(0, $xpath->query('//form')->length); }
            else { self::assertSame($roomStatus === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE', $xpath->evaluate('string(//*[@name="status"]/@value)')); }
            foreach (['2569', 'OWN', 'ชื่อห้อง', $roomStatus] as $value) { self::assertStringContainsString($value, $response->body()); }
            $this->assertForms($response);
        }
    }

    public static function yearStates(): array { return [['DRAFT'], ['ACTIVE'], ['CLOSED']]; }

    public function test_update_cannot_move_year_even_with_malicious_ignored_parent_input(): void
    {
        $this->login();
        foreach ([$this->foreignYear, $this->activeYear, ['bad'], new stdClass()] as $forgedYear) {
            self::assertSame(302, $this->request('POST', $this->path('/academic/classrooms/{id}'), $this->payload(['academic_year_id' => $forgedYear, 'school_id' => $this->foreignSchool]))->status());
            $row = $this->row('SELECT * FROM classrooms WHERE id = ?', [$this->room]);
            self::assertSame($this->year, $row['academic_year_id']);
            self::assertSame($this->school, $row['school_id']);
        }
    }

    public function test_foreign_missing_overflow_mutation_targets_are_indistinguishable_and_unchanged(): void
    {
        $this->login();
        $before = $this->snapshot();
        foreach (['/academic/classrooms/{id}', '/academic/classrooms/{id}/status'] as $path) {
            $responses = [];
            foreach ([(string) $this->foreignRoom, '0', '999999999999999999999999'] as $id) {
                $response = $this->request('POST', str_replace('{id}', $id, $path), $this->payload(['school_id' => $this->foreignSchool]));
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

    public function test_closed_year_denies_update_and_status_even_noops(): void
    {
        $this->login();
        $this->pdo->prepare("UPDATE academic_years SET status = 'CLOSED' WHERE id = ?")->execute([$this->year]);
        $before = $this->snapshot();
        foreach (['/academic/classrooms/{id}', '/academic/classrooms/{id}/status'] as $path) {
            foreach (['ACTIVE', 'INACTIVE'] as $status) {
                $response = $this->request('POST', $this->path($path), $this->payload(['status' => $status, 'code' => 'OWN', 'name_th' => 'ชื่อห้อง']));
                self::assertSame(422, $response->status());
                self::assertSame($before, $this->snapshot());
            }
        }
    }

    public function test_status_toggle_and_normalized_update_noops_have_no_extra_writes_or_audit(): void
    {
        $this->login();
        $path = $this->path('/academic/classrooms/{id}/status');
        foreach (['INACTIVE', 'ACTIVE'] as $status) {
            self::assertEquals(Response::redirect($this->path('/academic/classrooms/{id}/edit')), $this->request('POST', $path, $this->payload(['status' => $status])));
            self::assertSame($status, $this->row('SELECT status FROM classrooms WHERE id = ?', [$this->room])['status']);
            $before = $this->snapshot();
            $this->pdo->writeAttempts = 0;
            self::assertSame(302, $this->request('POST', $path, $this->payload(['status' => $status]))->status());
            self::assertSame(0, $this->pdo->writeAttempts);
            self::assertSame($before, $this->snapshot());
        }
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        self::assertSame(302, $this->request('POST', $this->path('/academic/classrooms/{id}'), $this->payload(['code' => ' OWN ', 'name_th' => ' ชื่อห้อง ']))->status());
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
        foreach (['UNKNOWN', 'CLOSED', '', ['ACTIVE'], new stdClass(), true, 1, null] as $status) {
            $response = $this->request('POST', $path, $this->payload(['status' => $status]));
            self::assertSame(422, $response->status());
            $this->assertSafe($response);
            self::assertSame($before, $this->snapshot());
        }
    }

    public function test_hostile_values_and_csrf_are_escaped_in_lists_edit_and_error_forms(): void
    {
        $this->login();
        $_SESSION['csrf_token'] = '" onfocus="alert(1)';
        $this->pdo->prepare('UPDATE classrooms SET code = ?, name_th = ? WHERE id = ?')->execute([self::HOSTILE, self::HOSTILE, $this->room]);
        foreach (['/academic/classrooms', $this->path('/academic/classrooms/{id}/edit')] as $path) {
            $response = $this->request('GET', $path);
            self::assertSame(200, $response->status());
            self::assertStringContainsString(htmlspecialchars(self::HOSTILE, ENT_QUOTES, 'UTF-8'), $response->body());
            self::assertStringNotContainsString(self::HOSTILE, $response->body());
            self::assertSame(0, $this->xpath($response->body())->query('//*[@onfocus]')->length);
        }
        $response = $this->request('POST', '/academic/classrooms', $this->payload(['code' => self::HOSTILE, 'name_th' => self::HOSTILE]));
        self::assertSame(422, $response->status());
        self::assertStringContainsString(htmlspecialchars(self::HOSTILE, ENT_QUOTES, 'UTF-8'), $response->body());
        self::assertStringNotContainsString(self::HOSTILE, $response->body());
        self::assertStringContainsString('&quot; onfocus=&quot;alert(1)', $response->body());
        self::assertSame(0, $this->xpath($response->body())->query('//*[@onfocus]')->length);
        $this->assertForms($response);
    }

    #[DataProvider('mutations')]
    public function test_domain_write_and_audit_failure_safe_422_and_atomic_rollback(string $path): void
    {
        $this->login();
        foreach (['INSERT INTO audit_logs', $path === '/academic/classrooms' ? 'INSERT INTO classrooms' : 'UPDATE classrooms'] as $failure) {
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
    public function test_only_valid_remote_addr_is_audited(mixed $value, ?string $expected): void
    {
        $this->login();
        self::assertSame(302, $this->request('POST', '/academic/classrooms', $this->payload(['REMOTE_ADDR' => '203.0.113.1']), ['REMOTE_ADDR' => '203.0.113.2'], ['REMOTE_ADDR' => $value, 'HTTP_X_FORWARDED_FOR' => '203.0.113.3'])->status());
        self::assertSame($expected, $this->row('SELECT ip_address FROM audit_logs')['ip_address']);
    }

    public static function ipAddresses(): array { return [['192.0.2.42', '192.0.2.42'], ['2001:db8::1', '2001:db8::1'], [null, null], ['invalid', null], [['invalid'], null]]; }

    public function test_no_hard_delete_or_system_classroom_routes(): void
    {
        $this->login();
        $before = $this->snapshot();
        self::assertSame(405, $this->request('DELETE', $this->path('/academic/classrooms/{id}'))->status());
        self::assertSame(404, $this->request('POST', $this->path('/academic/classrooms/{id}/delete'))->status());
        self::assertSame(404, $this->request('GET', '/system/classrooms')->status());
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
        return array_replace(['_token' => $this->token(), 'academic_year_id' => (string) $this->year,
            'grade_level_id' => (string) $this->grade, 'code' => 'NEW', 'name_th' => 'ห้องใหม่', 'status' => 'INACTIVE'], $overrides);
    }
    private function path(string $path): string { return str_replace('{id}', (string) $this->room, $path); }
    private function request(string $method, string $path, array $post = [], array $query = [], array $server = []): Response
    {
        return $this->app->handle(new Request($method, $path, $query, $post, $server));
    }
    private function fixtureYear(int $school, int $year, string $status): int
    {
        return $this->insert('INSERT INTO academic_years (school_id, year_be, status) VALUES (?, ?, ?)', [$school, $year, $status]);
    }
    private function fixtureRoom(int $school, int $year, string $code): int
    {
        return $this->insert('INSERT INTO classrooms (school_id, academic_year_id, grade_level_id, code, name_th) VALUES (?, ?, ?, ?, ?)', [$school, $year, $this->grade, $code, 'ชื่อห้อง']);
    }
    private function grant(string $role, string $permission): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = ? AND p.code = ?')->execute([$role, $permission]);
    }
    private function snapshot(): array { return [$this->rows('SELECT * FROM classrooms ORDER BY id'), $this->rows('SELECT * FROM audit_logs ORDER BY id')]; }
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
        self::assertSame(0, $xpath->query('//*[@name="school_id" or @name="user_id" or @name="actor" or @name="actor_user_id"]')->length);
        foreach ($xpath->query('//form[@method="post"]') as $form) { self::assertSame($this->token(), $xpath->evaluate('string(.//input[@name="_token"]/@value)', $form)); }
    }
    private function assertSafe(Response $response): void
    {
        foreach (['SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', '/Applications/MAMP/', 'htdocs/app/', 'uq_classroom', 'fk_classroom', 'private-db-details', 'FOREIGN_SECRET', 'Foreign School Secret'] as $secret) { self::assertStringNotContainsString($secret, $response->body()); }
    }
}

/** Service commits use savepoints inside each HTTP test's rollback fixture. */
final class ClassroomHttpPDO extends PDO
{
    public int $depth = 0;
    public int $writeAttempts = 0;
    public ?string $failPrepare = null;
    public bool $failureTriggered = false;
    public function beginTransaction(): bool
    {
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT classroom_http_' . $this->depth) !== false;
        ++$this->depth;
        return $ok;
    }
    public function commit(): bool
    {
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT classroom_http_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else { $ok = $this->exec('ROLLBACK TO SAVEPOINT classroom_http_' . ($this->depth - 1)) !== false; $this->exec('RELEASE SAVEPOINT classroom_http_' . ($this->depth - 1)); }
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
