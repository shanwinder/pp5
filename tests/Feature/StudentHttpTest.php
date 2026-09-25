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

final class StudentHttpTest extends TestCase
{
    private StudentHttpPDO $pdo;
    private Application $app;
    private int $school;
    private int $foreignSchool;
    private int $student;
    private int $foreignStudent;
    private array $users;
    private const HOSTILE = '<script>"XSS"</script>';
    private const NATIONAL = '1234567890123';

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new StudentHttpPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->school = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['student-http-a', 'School A']);
        $this->foreignSchool = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['student-http-b', 'Foreign School Secret']);
        $this->student = $this->fixture($this->school, 'OWN');
        $this->foreignStudent = $this->fixture($this->foreignSchool, 'FOREIGN_SECRET');
        foreach (['SCHOOL_ADMIN', 'ACADEMIC_ADMIN', 'VIEWER', 'SYSTEM_ADMIN'] as $role) {
            $id = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['student-http-' . $role, 'unused', $role]);
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
        return [
            ['GET', '/students', 'STUDENT_VIEW'],
            ['GET', '/students/create', 'STUDENT_MANAGE'],
            ['POST', '/students', 'STUDENT_MANAGE'],
            ['GET', '/students/{id}', 'STUDENT_VIEW'],
            ['GET', '/students/{id}/edit', 'STUDENT_MANAGE'],
            ['POST', '/students/{id}', 'STUDENT_MANAGE'],
            ['POST', '/students/{id}/status', 'STUDENT_MANAGE'],
        ];
    }

    #[DataProvider('routes')]
    public function test_routes_require_exact_school_permission_and_numeric_target(string $method, string $path, string $permission): void
    {
        $dispatcher = simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php');
        $route = $dispatcher->dispatch($method, $this->path($path));
        self::assertSame(Dispatcher::FOUND, $route[0], 'Task 3 student route missing');
        self::assertTrue($route[1]['protected']);
        self::assertSame('SCHOOL', $route[1]['context']);
        self::assertSame($permission, $route[1]['permission']);
        if (str_contains($path, '{id}')) {
            self::assertSame(Dispatcher::NOT_FOUND, $dispatcher->dispatch($method, str_replace('{id}', 'abc', $path))[0]);
        }
    }

    #[DataProvider('routes')]
    public function test_guest_system_and_unprivileged_school_requests_cannot_reach_handlers(string $method, string $path, string $permission): void
    {
        $before = $this->snapshot();
        self::assertEquals(Response::redirect('/login'), $this->request($method, $this->path($path)));
        foreach (['SYSTEM_ADMIN', 'VIEWER'] as $role) {
            $this->login($role);
            if ($role === 'SYSTEM_ADMIN') { $this->grant($role, $permission); }
            $this->pdo->writeAttempts = 0;
            $this->pdo->beginAttempts = 0;
            $response = $this->request($method, $this->path($path), $this->payload());
            self::assertSame(403, $response->status());
            $this->assertSafe($response);
            self::assertSame(0, $this->pdo->writeAttempts);
            self::assertSame(0, $this->pdo->beginAttempts);
            self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('routes')]
    public function test_exact_permission_enforces_access_independently_of_role_code(string $method, string $path, string $permission): void
    {
        foreach (['SCHOOL_ADMIN', 'ACADEMIC_ADMIN'] as $role) {
            $this->login($role);
            $this->pdo->beginTransaction();
            self::assertSame($method === 'GET' ? 200 : 302, $this->request($method, $this->path($path), $this->payload())->status());
            $this->pdo->rollBack();
            $this->pdo->prepare('DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code=? AND p.code=?')->execute([$role, $permission]);
            $before = $this->snapshot();
            self::assertSame(403, $this->request($method, $this->path($path), $this->payload())->status());
            self::assertSame($before, $this->snapshot());
        }
        $this->login('VIEWER');
        $this->grant('VIEWER', $permission);
        self::assertSame($method === 'GET' ? 200 : 302, $this->request($method, $this->path($path), $this->payload())->status());
    }

    #[DataProvider('routes')]
    public function test_view_permission_alone_never_allows_management(string $method, string $path, string $permission): void
    {
        $this->login('VIEWER'); $this->grant('VIEWER', 'STUDENT_VIEW');
        $before = $this->snapshot();
        $response = $this->request($method, $this->path($path), $this->payload());
        self::assertSame($permission === 'STUDENT_VIEW' ? 200 : 403, $response->status());
        $this->assertSafe($response);
        self::assertSame($before, $this->snapshot());
        self::assertStringNotContainsString('/students/create', $response->body());
        self::assertStringNotContainsString('/edit', $response->body());
        self::assertSame(0, $this->xpath($response->body())->query('//form[@method="post" and not(@action="/logout")]')->length);
    }

    public function test_list_is_tenant_scoped_ordered_and_has_only_public_columns(): void
    {
        $this->login(); $this->fixture($this->school, 'AAA', 'INACTIVE');
        $response = $this->request('GET', '/students', [], ['school_id' => $this->foreignSchool, 'national_id' => self::NATIONAL]);
        self::assertSame(200, $response->status());
        self::assertSame(['AAA', 'OWN'], array_map(static fn (DOMNode $n): string => trim($n->textContent), iterator_to_array($this->xpath($response->body())->query('//tbody/tr/th[@scope="row"]'))));
        foreach (['ชื่อทดสอบ', 'นามสกุล', 'INACTIVE', '/students/create', '/students/' . $this->student . '/edit'] as $value) { self::assertStringContainsString($value, $response->body()); }
        self::assertSame(4, $this->xpath($response->body())->query('//thead/tr/th')->length);
        self::assertStringNotContainsString('2014-03-04', $response->body());
        self::assertSame(['q'], $this->values($this->xpath($response->body())->query('//form[@method="get"]//*[@name]/@name')));
        $this->assertSafe($response);
    }

    public function test_show_masks_national_id_and_represents_null_without_invented_digits(): void
    {
        $this->login();
        $response = $this->request('GET', $this->path('/students/{id}'), [], ['school_id' => $this->foreignSchool]);
        self::assertSame(200, $response->status());
        foreach (['*********0123', '2014-03-04', 'MALE', 'OWN', 'ชื่อทดสอบ', 'นามสกุล'] as $text) { self::assertStringContainsString($text, $response->body()); }
        $this->assertSafe($response);
        $this->pdo->prepare('UPDATE students SET national_id=NULL, gender_code=NULL, birth_date=NULL WHERE id=?')->execute([$this->student]);
        $response = $this->request('GET', $this->path('/students/{id}'));
        self::assertSame(200, $response->status());
        self::assertStringContainsString('ไม่ระบุ', $response->body());
        self::assertStringNotContainsString('*********', $response->body());
        $this->assertSafe($response);
    }

    public function test_manage_only_forms_expose_full_national_id_in_edit_and_use_current_csrf(): void
    {
        $this->login('VIEWER'); $this->grant('VIEWER', 'STUDENT_MANAGE');
        foreach (['/students/create', $this->path('/students/{id}/edit')] as $path) {
            $response = $this->request('GET', $path);
            self::assertSame(200, $response->status());
            $xpath = $this->xpath($response->body());
            $names = $this->values($xpath->query('(//main//form)[1]//*[@name]/@name'));
            self::assertEqualsCanonicalizing(['_token', 'student_code', 'national_id', 'prefix_th', 'first_name_th', 'last_name_th', 'gender_code', 'birth_date'], $names);
            self::assertSame($path === '/students/create' ? '' : self::NATIONAL, $xpath->evaluate('string(//input[@name="national_id"]/@value)'));
            $this->assertForms($response);
        }
        $response = $this->request('GET', $this->path('/students/{id}/edit'));
        self::assertSame('INACTIVE', $this->xpath($response->body())->evaluate('string(//input[@name="status"]/@value)'));
        self::assertSame(403, $this->request('GET', $this->path('/students/{id}'))->status());
    }

    public function test_foreign_missing_and_overflow_targets_are_indistinguishable(): void
    {
        $this->login(); $before = $this->snapshot();
        foreach ([['GET', '/students/{id}', 404], ['GET', '/students/{id}/edit', 404], ['POST', '/students/{id}', 422], ['POST', '/students/{id}/status', 422]] as [$method, $path, $status]) {
            $responses = [];
            foreach ([(string) $this->foreignStudent, '0', '999999999999999999999999'] as $id) {
                $response = $this->request($method, str_replace('{id}', $id, $path), $this->payload(['school_id' => $this->foreignSchool]), ['school_id' => $this->foreignSchool]);
                self::assertSame($status, $response->status()); $this->assertSafe($response);
                self::assertSame(0, $this->xpath($response->body())->query('//form[not(@action="/logout")]')->length);
                self::assertSame($before, $this->snapshot()); $responses[] = $response;
            }
            self::assertEquals($responses[0], $responses[1]); self::assertEquals($responses[0], $responses[2]);
        }
    }

    public static function mutations(): array { return [['/students'], ['/students/{id}'], ['/students/{id}/status']]; }

    #[DataProvider('mutations')]
    public function test_csrf_rejection_precedes_shape_validation_service_transaction_and_writes(string $path): void
    {
        $this->login(); $before = $this->snapshot();
        foreach ([null, 'bad', ['bad'], new stdClass()] as $token) {
            $this->pdo->writeAttempts = 0; $this->pdo->beginAttempts = 0;
            $response = $this->request('POST', $this->path($path), $this->payload(['_token' => $token, 'student_code' => new stdClass(), 'national_id' => ['bad'], 'status' => ['bad']]));
            self::assertSame(419, $response->status());
            self::assertSame('CSRF token mismatch', $response->body());
            self::assertSame(0, $this->pdo->writeAttempts); self::assertSame(0, $this->pdo->beginAttempts);
            self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('mutations')]
    public function test_post_uses_only_session_school_actor_and_server_ip(string $path): void
    {
        $this->login();
        $foreign = $this->row('SELECT * FROM students WHERE id=?', [$this->foreignStudent]);
        $forged = ['school_id' => $this->foreignSchool, 'user_id' => $this->users['SYSTEM_ADMIN'], 'actor_user_id' => $this->users['SYSTEM_ADMIN'], 'ip_address' => '203.0.113.1'];
        $response = $this->request('POST', $this->path($path), $this->payload($forged), $forged, ['REMOTE_ADDR' => '192.0.2.51', 'HTTP_X_FORWARDED_FOR' => '203.0.113.1']);
        self::assertSame(302, $response->status());
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame([$this->school, $this->users['SCHOOL_ADMIN'], 'students', '192.0.2.51'], [$audit['school_id'], $audit['user_id'], $audit['entity_type'], $audit['ip_address']]);
        self::assertSame(match ($path) { '/students' => 'STUDENT_CREATED', '/students/{id}' => 'STUDENT_UPDATED', default => 'STUDENT_STATUS_CHANGED' }, $audit['action']);
        $row = $this->row('SELECT * FROM students WHERE id=?', [$audit['entity_id']]);
        self::assertSame($this->school, $row['school_id']);
        self::assertSame($path === '/students/{id}/status' ? 'INACTIVE' : 'ACTIVE', $row['status']);
        self::assertSame($foreign, $this->row('SELECT * FROM students WHERE id=?', [$this->foreignStudent]));
        $this->assertSafe(new Response(json_encode($audit, JSON_UNESCAPED_UNICODE)));
        self::assertStringNotContainsString('ชื่อทดสอบ', $audit['new_value']);
        self::assertStringNotContainsString('ใหม่', $audit['new_value']);
    }

    #[DataProvider('malformedFields')]
    public function test_malformed_profile_shape_is_safe_422_before_service(string $field, mixed $value): void
    {
        $this->login(); $before = $this->snapshot();
        foreach (['/students', $this->path('/students/{id}')] as $path) {
            $this->pdo->beginAttempts = 0; $this->pdo->writeAttempts = 0;
            $response = $this->request('POST', $path, $this->payload([$field => $value]));
            self::assertSame(422, $response->status()); $this->assertSafe($response);
            self::assertSame(0, $this->pdo->beginAttempts); self::assertSame(0, $this->pdo->writeAttempts);
            self::assertSame($before, $this->snapshot());
        }
    }
    public static function malformedFields(): array
    {
        $cases = [];
        foreach (['student_code', 'national_id', 'prefix_th', 'first_name_th', 'last_name_th', 'gender_code', 'birth_date'] as $field) {
            foreach ([['bad'], new stdClass(), true, 1, 1.5] as $value) { $cases[] = [$field, $value]; }
        }
        foreach (['student_code', 'prefix_th', 'first_name_th', 'last_name_th'] as $field) { $cases[] = [$field, null]; }
        return $cases;
    }

    #[DataProvider('badDomainFields')]
    public function test_domain_rejections_are_safe_422_and_do_not_reflect_national_id(string $field, mixed $value): void
    {
        $this->login(); $before = $this->snapshot();
        foreach (['/students', $this->path('/students/{id}')] as $path) {
            $response = $this->request('POST', $path, $this->payload([$field => $value]));
            self::assertSame(422, $response->status()); $this->assertSafe($response);
            self::assertSame($before, $this->snapshot());
        }
    }
    public static function badDomainFields(): array
    {
        return [['student_code', ''], ['first_name_th', "\xFF"], ['last_name_th', "a\nb"], ['prefix_th', str_repeat('ก', 51)],
            ['national_id', '123'], ['gender_code', 'male'], ['birth_date', '2023-02-29'], ['birth_date', '2999-01-01']];
    }

    #[DataProvider('badStatuses')]
    public function test_status_shape_or_value_rejection_preserves_entities_and_audit(mixed $status): void
    {
        $this->login(); $before = $this->snapshot();
        $response = $this->request('POST', $this->path('/students/{id}/status'), $this->payload(['status' => $status]));
        self::assertSame(422, $response->status()); $this->assertSafe($response);
        self::assertSame($before, $this->snapshot());
    }
    public static function badStatuses(): array { return [[null], [['ACTIVE']], [new stdClass()], [true], [1], [1.5], [''], ['active'], [self::NATIONAL]]; }

    #[DataProvider('badQueries')]
    public function test_malformed_search_is_friendly_422_without_disclosure(mixed $query): void
    {
        $this->login(); $before = $this->snapshot();
        $response = $this->request('GET', '/students', [], ['q' => $query]);
        self::assertSame(422, $response->status()); $this->assertSafe($response);
        self::assertSame($before, $this->snapshot());
    }
    public static function badQueries(): array { return [[['bad']], [new stdClass()], [true], [1], [1.5], [str_repeat('ก', 101)], ["\xFF"], ["a\nb"]]; }

    public function test_search_trims_unicode_matches_only_code_names_and_never_reflects_national_id(): void
    {
        $this->login();
        foreach ([[], ['q' => ''], ['q' => "\u{3000}OWN\u{00A0}"], ['q' => 'ด.ช. ชื่อทดสอบ นามสกุล']] as $query) {
            $response = $this->request('GET', '/students', [], $query);
            self::assertSame(200, $response->status());
            self::assertSame(1, $this->xpath($response->body())->query('//tbody/tr')->length);
            $this->assertSafe($response);
        }
        foreach ([self::NATIONAL, '%', '_', 'FOREIGN_SECRET', str_repeat('ก', 100)] as $query) {
            $response = $this->request('GET', '/students', [], ['q' => $query]);
            self::assertSame(200, $response->status());
            self::assertSame(0, $this->xpath($response->body())->query('//tbody/tr')->length);
            // Safe q is now reflected in its input. Remove only that known request value
            // from the leak scan; foreign data elsewhere must still never appear.
            $body = $response->body();
            if ($query === 'FOREIGN_SECRET') {
                self::assertSame($query, $this->xpath($body)->evaluate('string(//input[@name="q"]/@value)'));
                $body = str_replace('value="FOREIGN_SECRET"', 'value=""', $body);
            }
            $this->assertSafe(new Response($body, $response->status()));
        }
    }

    public function test_html_names_and_csrf_values_are_escaped_in_all_views(): void
    {
        $this->login(); $_SESSION['csrf_token'] = '" onfocus="alert(1)';
        $this->pdo->prepare('UPDATE students SET student_code=?, prefix_th=?, first_name_th=?, last_name_th=? WHERE id=?')->execute([self::HOSTILE, self::HOSTILE, self::HOSTILE, self::HOSTILE, $this->student]);
        foreach (['/students', $this->path('/students/{id}'), $this->path('/students/{id}/edit')] as $path) {
            $response = $this->request('GET', $path);
            self::assertSame(200, $response->status());
            self::assertStringContainsString(htmlspecialchars(self::HOSTILE, ENT_QUOTES, 'UTF-8'), $response->body());
            self::assertStringNotContainsString(self::HOSTILE, $response->body());
            self::assertSame(0, $this->xpath($response->body())->query('//script[not(@src="/assets/app.js")] | //*[@onfocus]')->length);
        }
        $response = $this->request('GET', '/students/create');
        self::assertSame(200, $response->status());
        self::assertStringContainsString('&quot; onfocus=&quot;alert(1)', $response->body());
        $this->assertForms($response);
        $response = $this->request('POST', '/students', $this->payload(['student_code' => self::HOSTILE, 'first_name_th' => self::HOSTILE]));
        self::assertSame(422, $response->status()); $this->assertSafe($response);
        self::assertSame(0, $this->xpath($response->body())->query('//script[not(@src="/assets/app.js")] | //*[@onfocus]')->length);
    }

    #[DataProvider('mutations')]
    public function test_repository_and_audit_failures_are_safe_422_with_real_rollback(string $path): void
    {
        $this->login();
        foreach (['INSERT INTO audit_logs', $path === '/students' ? 'INSERT INTO students' : 'UPDATE students'] as $failure) {
            $before = $this->snapshot(); $this->pdo->failPrepare = $failure; $this->pdo->failureTriggered = false;
            $response = $this->request('POST', $this->path($path), $this->payload());
            self::assertSame(422, $response->status()); self::assertTrue($this->pdo->failureTriggered);
            $this->assertSafe($response); self::assertSame(1, $this->pdo->depth);
            self::assertSame($before, $this->snapshot());
        }
    }

    public function test_get_repository_failure_has_no_raw_exception_details(): void
    {
        $this->login(); $this->pdo->failPrepare = 'FROM students';
        $response = $this->request('GET', '/students');
        self::assertSame(500, $response->status()); self::assertTrue($this->pdo->failureTriggered);
        $this->assertSafe($response);
    }

    public function test_status_round_trip_and_profile_noop_use_domain_rules(): void
    {
        $this->login();
        foreach (['INACTIVE', 'ACTIVE'] as $status) {
            self::assertSame(302, $this->request('POST', $this->path('/students/{id}/status'), $this->payload(['status' => $status]))->status());
            self::assertSame($status, $this->row('SELECT status FROM students WHERE id=?', [$this->student])['status']);
            $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
            self::assertSame(302, $this->request('POST', $this->path('/students/{id}/status'), $this->payload(['status' => $status]))->status());
            self::assertSame(0, $this->pdo->writeAttempts); self::assertSame($before, $this->snapshot());
        }
        $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        $response = $this->request('POST', $this->path('/students/{id}'), $this->payload(['student_code' => ' OWN ', 'national_id' => self::NATIONAL, 'first_name_th' => 'ชื่อทดสอบ', 'last_name_th' => 'นามสกุล', 'birth_date' => '2014-03-04']));
        self::assertSame(302, $response->status()); self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }

    public function test_tampered_session_cannot_use_browser_school_to_restore_access(): void
    {
        $this->login(); $_SESSION['school_id'] = $this->foreignSchool;
        $before = $this->snapshot();
        foreach (self::routes() as [$method, $path]) {
            self::assertSame(403, $this->request($method, $this->path($path), $this->payload(['school_id' => $this->school]), ['school_id' => $this->school])->status());
            self::assertSame($before, $this->snapshot());
        }
    }

    public function test_student_history_is_newest_first_tenant_scoped_masked_and_keeps_ended_placements(): void
    {
        $this->login('VIEWER'); $this->grant('VIEWER','STUDENT_VIEW');
        $grade=(int)$this->pdo->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        foreach ([[$this->school,$this->student,2568,'CLOSED','WITHDRAWN','Old class'],[$this->school,$this->student,2569,'ACTIVE','ACTIVE','Current class'],[$this->foreignSchool,$this->foreignStudent,2579,'CLOSED','WITHDRAWN','FOREIGN_SECRET']] as [$school,$student,$yearBe,$yearStatus,$status,$roomName]) {
            $year=$this->insert('INSERT INTO academic_years (school_id,year_be,status) VALUES (?,?,?)',[$school,$yearBe,$yearStatus]);
            $enrollment=$this->insert('INSERT INTO student_enrollments (school_id,academic_year_id,student_id,grade_level_id,entry_date,exit_date,status) VALUES (?,?,?,?,?,?,?)',[$school,$year,$student,$grade,'2025-05-01',$status==='ACTIVE' ? null : '2026-03-01',$status]);
            $room=$this->insert('INSERT INTO classrooms (school_id,academic_year_id,grade_level_id,code,name_th) VALUES (?,?,?,?,?)',[$school,$year,$grade,'R',$roomName]);
            $this->insert('INSERT INTO student_classroom_placements (school_id,academic_year_id,grade_level_id,enrollment_id,classroom_id,status,started_at,ended_at) VALUES (?,?,?,?,?,?,?,?)',[$school,$year,$grade,$enrollment,$room,$status==='ACTIVE' ? 'ACTIVE' : 'ENDED','2025-05-01 08:00:00',$status==='ACTIVE' ? null : '2026-03-01 16:00:00']);
        }
        $before=[$this->rows('SELECT * FROM student_enrollments ORDER BY id'),$this->rows('SELECT * FROM student_classroom_placements ORDER BY id'),$this->snapshot()];
        $response=$this->request('GET',$this->path('/students/{id}'),[],['school_id'=>$this->foreignSchool]);
        self::assertSame(200,$response->status()); $this->assertSafe($response);
        $xpath=$this->xpath($response->body());
        self::assertSame(['ปีการศึกษา 2569','ปีการศึกษา 2568'],$this->values($xpath->query('//section[contains(@class,"enrollment-history")]/h3')));
        foreach (['*********0123','Current class','Old class','WITHDRAWN','ENDED','2025-05-01','2026-03-01','ห้องล่าสุด','ห้องปัจจุบัน'] as $value) { self::assertStringContainsString($value,$response->body()); }
        self::assertStringNotContainsString('/academic/enrollments/',$response->body());
        self::assertSame($before,[$this->rows('SELECT * FROM student_enrollments ORDER BY id'),$this->rows('SELECT * FROM student_classroom_placements ORDER BY id'),$this->snapshot()]);
        $this->pdo->prepare('UPDATE classrooms SET name_th=? WHERE school_id=?')->execute([self::HOSTILE,$this->school]);
        $response=$this->request('GET',$this->path('/students/{id}'));
        self::assertStringContainsString(htmlspecialchars(self::HOSTILE,ENT_QUOTES,'UTF-8'),$response->body());
        self::assertSame(0,$this->xpath($response->body())->query('//script[not(@src="/assets/app.js")]')->length); $this->assertSafe($response);
    }

    public function test_student_without_enrollments_shows_empty_history(): void
    {
        $this->login(); $response=$this->request('GET',$this->path('/students/{id}'));
        self::assertSame(200,$response->status()); self::assertStringContainsString('ยังไม่มีประวัติการลงทะเบียน',$response->body()); $this->assertSafe($response);
    }

    public function test_student_history_repository_failure_is_safe(): void
    {
        $this->login(); $this->pdo->failPrepare='FROM student_enrollments';
        $response=$this->request('GET',$this->path('/students/{id}'));
        self::assertSame(500,$response->status()); self::assertTrue($this->pdo->failureTriggered); $this->assertSafe($response);
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
        return array_replace(['_token' => $this->token(), 'student_code' => 'NEW', 'national_id' => '0000000000001',
            'prefix_th' => 'ด.ช.', 'first_name_th' => 'ใหม่', 'last_name_th' => 'ทดสอบ',
            'gender_code' => 'MALE', 'birth_date' => '2015-01-02', 'status' => 'INACTIVE'], $overrides);
    }
    private function path(string $path): string { return str_replace('{id}', (string) $this->student, $path); }
    private function request(string $method, string $path, array $post = [], array $query = [], array $server = []): Response
    {
        return $this->app->handle(new Request($method, $path, $query, $post, $server));
    }
    private function fixture(int $school, string $code, string $status = 'ACTIVE'): int
    {
        return $this->insert('INSERT INTO students (school_id, student_code, national_id, prefix_th, first_name_th, last_name_th, gender_code, birth_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$school, $code, $code === 'OWN' ? self::NATIONAL : null, 'ด.ช.', 'ชื่อทดสอบ', 'นามสกุล', 'MALE', '2014-03-04', $status]);
    }
    private function grant(string $role, string $permission): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = ? AND p.code = ?')->execute([$role, $permission]);
    }
    private function snapshot(): array { return [$this->rows('SELECT * FROM students ORDER BY id'), $this->rows('SELECT * FROM audit_logs ORDER BY id')]; }
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
        foreach (['SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', '/Applications/MAMP/', 'htdocs/app/', 'uq_student', 'fk_student', 'private-db-details', 'FOREIGN_SECRET', 'Foreign School Secret', self::NATIONAL, '0000000000001'] as $secret) { self::assertStringNotContainsString($secret, $response->body()); }
    }
}

/** Service commits stay inside the HTTP fixture rollback. */
final class StudentHttpPDO extends PDO
{
    public int $depth = 0;
    public int $writeAttempts = 0;
    public int $beginAttempts = 0;
    public ?string $failPrepare = null;
    public bool $failureTriggered = false;
    public function beginTransaction(): bool
    {
        ++$this->beginAttempts;
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT student_http_' . $this->depth) !== false;
        ++$this->depth;
        return $ok;
    }
    public function commit(): bool
    {
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT student_http_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else { $ok = $this->exec('ROLLBACK TO SAVEPOINT student_http_' . ($this->depth - 1)) !== false; $this->exec('RELEASE SAVEPOINT student_http_' . ($this->depth - 1)); }
        --$this->depth;
        return $ok;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $query)) { ++$this->writeAttempts; }
        if ($this->failPrepare !== null && str_contains($query, $this->failPrepare)) {
            $this->failPrepare = null; $this->failureTriggered = true;
            throw new PDOException('SQLSTATE private-db-details 1234567890123 0000000000001 SELECT /Applications/MAMP/htdocs/app/secret.php');
        }
        return parent::prepare($query, $options);
    }
}
