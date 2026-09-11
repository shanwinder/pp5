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

final class SubjectOfferingHttpTest extends TestCase
{
    private OfferingHttpPDO $pdo;
    private Application $app;
    private int $school;
    private int $foreignSchool;
    private int $year;
    private int $activeYear;
    private int $closedYear;
    private int $foreignYear;
    private int $room;
    private int $otherRoom;
    private int $wrongYearRoom;
    private int $closedRoom;
    private int $inactiveRoom;
    private int $foreignRoom;
    private int $subject;
    private int $otherSubject;
    private int $inactiveSubject;
    private int $foreignSubject;
    private int $offering;
    private int $foreignOffering;
    private array $users;
    private const HOSTILE = '<b>"XSS"</b>';

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php'; $config['database'] = 'pp5_test';
        $this->pdo = new OfferingHttpPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->school = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['offering-http-a', 'School A']);
        $this->foreignSchool = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['offering-http-b', 'Foreign School Secret']);
        $this->year = $this->fixtureYear($this->school, 2569, 'DRAFT');
        $this->activeYear = $this->fixtureYear($this->school, 2570, 'ACTIVE');
        $this->closedYear = $this->fixtureYear($this->school, 2568, 'CLOSED');
        $this->foreignYear = $this->fixtureYear($this->foreignSchool, 2699, 'DRAFT');
        $this->room = $this->fixtureRoom($this->school, $this->year, 'OWN');
        $this->otherRoom = $this->fixtureRoom($this->school, $this->year, 'OTHER');
        $this->wrongYearRoom = $this->fixtureRoom($this->school, $this->activeYear, 'NEXT_YEAR');
        $this->closedRoom = $this->fixtureRoom($this->school, $this->closedYear, 'HISTORY');
        $this->inactiveRoom = $this->fixtureRoom($this->school, $this->year, 'INACTIVE_ROOM', 'INACTIVE');
        $this->foreignRoom = $this->fixtureRoom($this->foreignSchool, $this->foreignYear, 'FOREIGN_SECRET_ROOM');
        $this->subject = $this->fixtureSubject($this->school, 'SCI');
        $this->otherSubject = $this->fixtureSubject($this->school, 'MATH');
        $this->inactiveSubject = $this->fixtureSubject($this->school, 'INACTIVE_SUBJECT', 'INACTIVE');
        $this->foreignSubject = $this->fixtureSubject($this->foreignSchool, 'FOREIGN_SECRET_SUBJECT');
        $this->offering = $this->fixture();
        $this->foreignOffering = $this->fixture(['school_id' => $this->foreignSchool, 'academic_year_id' => $this->foreignYear, 'classroom_id' => $this->foreignRoom, 'subject_id' => $this->foreignSubject]);
        foreach (['SCHOOL_ADMIN', 'ACADEMIC_ADMIN', 'VIEWER', 'SYSTEM_ADMIN'] as $role) {
            $id = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['offering-http-' . $role, 'unused', $role]);
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
        return [['GET', '/academic/offerings', 'ACADEMIC_SETUP_VIEW'], ['GET', '/academic/offerings/create', 'SUBJECT_OFFERING_MANAGE'],
            ['POST', '/academic/offerings', 'SUBJECT_OFFERING_MANAGE'], ['GET', '/academic/offerings/{id}/edit', 'SUBJECT_OFFERING_MANAGE'],
            ['POST', '/academic/offerings/{id}', 'SUBJECT_OFFERING_MANAGE'], ['POST', '/academic/offerings/{id}/status', 'SUBJECT_OFFERING_MANAGE']];
    }

    #[DataProvider('routes')]
    public function test_exact_routes_permissions_context_and_numeric_ids(string $method, string $path, string $permission): void
    {
        $dispatcher = simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php');
        $route = $dispatcher->dispatch($method, $this->path($path));
        self::assertSame(Dispatcher::FOUND, $route[0], 'Task 6 offering route missing');
        self::assertTrue($route[1]['protected']);
        self::assertSame('SCHOOL', $route[1]['context']);
        self::assertSame($permission, $route[1]['permission']);
        self::assertArrayNotHasKey('school_id', $route[2]);
        if (str_contains($path, '{id}')) {
            self::assertSame((string) $this->offering, $route[2]['id']);
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

    public function test_list_scopes_and_filters_open_closed_history_in_deterministic_order(): void
    {
        $this->login();
        $this->fixture(['academic_year_id' => $this->activeYear, 'classroom_id' => $this->wrongYearRoom]);
        $this->fixture(['academic_year_id' => $this->closedYear, 'classroom_id' => $this->closedRoom, 'status' => 'INACTIVE']);
        $response = $this->request('GET', '/academic/offerings', [], ['school_id' => $this->foreignSchool]);
        self::assertSame(200, $response->status());
        self::assertSame(['2570', '2569', '2568'], array_map(static fn (DOMNode $n): string => trim($n->textContent), iterator_to_array($this->xpath($response->body())->query('//tbody/tr/td[1]'))));
        foreach (['OWN', 'SCI', 'HISTORY', 'INACTIVE'] as $text) { self::assertStringContainsString($text, $response->body()); }
        $this->assertSafe($response);
        foreach ([$this->year => 'OWN', $this->closedYear => 'HISTORY'] as $year => $code) {
            $filtered = $this->request('GET', '/academic/offerings', [], ['academic_year_id' => (string) $year, 'school_id' => $this->foreignSchool]);
            self::assertSame(200, $filtered->status());
            self::assertSame(1, $this->xpath($filtered->body())->query('//tbody/tr')->length);
            self::assertStringContainsString($code, $filtered->body());
            self::assertStringNotContainsString('NEXT_YEAR', $filtered->body());
        }
    }

    #[DataProvider('invalidIds')]
    public function test_malformed_year_query_is_safe_404(mixed $value): void
    {
        $this->login();
        foreach (['/academic/offerings', '/academic/offerings/create'] as $path) {
            $response = $this->request('GET', $path, [], ['academic_year_id' => $value]);
            self::assertSame(404, $response->status()); $this->assertSafe($response);
        }
    }
    public static function invalidIds(): array { return [[['1']], [new stdClass()], [true], [false], [1.5], ['1.5'], ['1abc'], ['1e3'], [''], ['0'], ['-1'], ['999999999999999999999999']]; }

    public function test_foreign_missing_year_filters_and_preselection_fail_identically(): void
    {
        $this->login();
        foreach (['/academic/offerings', '/academic/offerings/create'] as $path) {
            $foreign = $this->request('GET', $path, [], ['academic_year_id' => $this->foreignYear, 'school_id' => $this->foreignSchool]);
            $missing = $this->request('GET', $path, [], ['academic_year_id' => '999999999']);
            self::assertSame(404, $foreign->status()); self::assertEquals($missing, $foreign); $this->assertSafe($foreign);
        }
        self::assertSame(404, $this->request('GET', '/academic/offerings/create', [], ['academic_year_id' => $this->closedYear])->status());
    }

    public function test_create_selection_flow_only_offers_valid_own_parents_and_terms(): void
    {
        $this->login();
        $initial = $this->request('GET', '/academic/offerings/create'); self::assertSame(200, $initial->status());
        self::assertSame(0, $this->xpath($initial->body())->query('//form[@method="post"]')->length);
        foreach ([$this->year => [$this->room, $this->otherRoom], $this->activeYear => [$this->wrongYearRoom]] as $year => $rooms) {
            $response = $this->request('GET', '/academic/offerings/create', [], ['academic_year_id' => $year, 'school_id' => $this->foreignSchool]);
            self::assertSame(200, $response->status()); $xpath = $this->xpath($response->body());
            self::assertEqualsCanonicalizing([(string) $this->year, (string) $this->activeYear], $this->values($xpath->query('//form[@method="get"]//select[@name="academic_year_id"]/option[@value!=""]/@value')));
            self::assertSame((string) $year, $xpath->evaluate('string(//form[@method="post"]//input[@name="academic_year_id"]/@value)'));
            self::assertEqualsCanonicalizing(array_map('strval', $rooms), $this->optionIds($xpath, 'classroom_id'));
            self::assertEqualsCanonicalizing([(string) $this->subject, (string) $this->otherSubject], $this->optionIds($xpath, 'subject_id'));
            self::assertSame(['1', '2'], $this->optionIds($xpath, 'term_no'));
            self::assertEqualsCanonicalizing(['academic_year_id', 'classroom_id', 'subject_id', 'term_no', '_token'], $this->values($xpath->query('//form[@method="post"]//*[@name]/@name')));
            $this->assertForms($response); $this->assertSafe($response);
        }
    }

    public static function mutations(): array { return [['/academic/offerings'], ['/academic/offerings/{id}'], ['/academic/offerings/{id}/status']]; }
    #[DataProvider('mutations')]
    public function test_csrf_first_all_post_families_have_zero_write_and_audit(string $path): void
    {
        $this->login(); $before = $this->snapshot();
        foreach ([null, 'bad', ['bad'], new stdClass()] as $token) {
            $this->pdo->writeAttempts = 0;
            $response = $this->request('POST', $this->path($path), $this->payload(['_token' => $token, 'classroom_id' => ['bad'], 'term_no' => new stdClass(), 'status' => ['bad']]));
            self::assertSame(419, $response->status()); self::assertSame('CSRF token mismatch', $response->body());
            self::assertSame(0, $this->pdo->writeAttempts); self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('badInputs')]
    public function test_malicious_id_term_types_and_values_are_safe_422(string $field, mixed $value): void
    {
        $this->login(); $before = $this->snapshot();
        $paths = $field === 'academic_year_id' ? ['/academic/offerings'] : ['/academic/offerings', $this->path('/academic/offerings/{id}')];
        foreach ($paths as $path) {
            $response = $this->request('POST', $path, $this->payload([$field => $value]));
            self::assertSame(422, $response->status()); $this->assertSafe($response); self::assertSame($before, $this->snapshot());
        }
    }
    public static function badInputs(): array
    {
        $cases = [];
        foreach (['academic_year_id', 'classroom_id', 'subject_id', 'term_no'] as $field) {
            foreach ([...array_column(self::invalidIds(), 0), null] as $i => $value) { $cases[$field . $i] = [$field, $value]; }
        }
        $cases['term 3'] = ['term_no', '3'];
        return $cases;
    }

    #[DataProvider('openTerms')]
    public function test_create_open_years_terms_and_active_default(string $yearField, string $roomField, int $term): void
    {
        $this->login();
        self::assertEquals(Response::redirect('/academic/offerings'), $this->request('POST', '/academic/offerings', $this->payload([
            'academic_year_id' => $this->$yearField, 'classroom_id' => $this->$roomField, 'term_no' => $term, 'status' => 'INACTIVE',
        ])));
        $audit = $this->row('SELECT * FROM audit_logs');
        $row = $this->row('SELECT * FROM subject_offerings WHERE id = ?', [$audit['entity_id']]);
        self::assertSame([$this->school, $this->$yearField, $this->$roomField, $this->subject, $term, 'ACTIVE'],
            array_map(static fn (string $key): mixed => $row[$key], ['school_id', 'academic_year_id', 'classroom_id', 'subject_id', 'term_no', 'status']));
        self::assertSame('SUBJECT_OFFERING_CREATED', $audit['action']);
    }
    public static function openTerms(): array { return [['year', 'otherRoom', 1], ['year', 'otherRoom', 2], ['activeYear', 'wrongYearRoom', 1], ['activeYear', 'wrongYearRoom', 2]]; }

    #[DataProvider('invalidParents')]
    public function test_invalid_parents_are_rejected_without_mutation(string $field, string $property): void
    {
        $this->login(); $before = $this->snapshot();
        $value = $property === 'missing' ? 999999999 : $this->$property;
        $paths = $field === 'academic_year_id' ? ['/academic/offerings'] : ['/academic/offerings', $this->path('/academic/offerings/{id}')];
        foreach ($paths as $path) {
            $response = $this->request('POST', $path, $this->payload([$field => $value]));
            self::assertSame(422, $response->status()); $this->assertSafe($response); self::assertSame($before, $this->snapshot());
        }
    }
    public static function invalidParents(): array
    {
        return [['academic_year_id', 'foreignYear'], ['academic_year_id', 'closedYear'], ['academic_year_id', 'missing'],
            ['classroom_id', 'foreignRoom'], ['classroom_id', 'wrongYearRoom'], ['classroom_id', 'inactiveRoom'], ['classroom_id', 'missing'],
            ['subject_id', 'foreignSubject'], ['subject_id', 'inactiveSubject'], ['subject_id', 'missing']];
    }

    public function test_duplicate_create_including_inactive_and_duplicate_update_are_safe(): void
    {
        $this->login(); $second = $this->fixture(['term_no' => 2]);
        foreach (['ACTIVE', 'INACTIVE'] as $state) {
            $this->pdo->prepare('UPDATE subject_offerings SET status = ? WHERE id = ?')->execute([$state, $this->offering]);
            $before = $this->snapshot();
            foreach (['/academic/offerings', '/academic/offerings/' . $second] as $path) {
                $response = $this->request('POST', $path, $this->payload(['term_no' => 1]));
                self::assertSame(422, $response->status()); $this->assertSafe($response);
                self::assertStringContainsString('มีการเปิดรายวิชานี้สำหรับห้องเรียนและภาคเรียนนี้แล้ว', $response->body());
                self::assertSame($before, $this->snapshot());
            }
        }
    }

    #[DataProvider('mutations')]
    public function test_session_is_only_tenant_actor_authority_and_redirects_are_exact(string $path): void
    {
        $this->login(); $foreign = $this->row('SELECT * FROM subject_offerings WHERE id = ?', [$this->foreignOffering]);
        $forged = ['school_id' => $this->foreignSchool, 'user_id' => $this->users['SYSTEM_ADMIN'], 'actor' => $this->users['SYSTEM_ADMIN'],
            'actor_user_id' => $this->users['SYSTEM_ADMIN'], 'created_by' => $this->users['SYSTEM_ADMIN'], 'teacher_id' => 1,
            'ip_address' => '203.0.113.1', 'REMOTE_ADDR' => '203.0.113.2'];
        if ($path !== '/academic/offerings') { $forged['academic_year_id'] = $this->foreignYear; }
        $response = $this->request('POST', $this->path($path), $this->payload($forged), $forged, ['REMOTE_ADDR' => '192.0.2.51', 'HTTP_X_FORWARDED_FOR' => '203.0.113.3']);
        self::assertEquals(Response::redirect($path === '/academic/offerings' ? '/academic/offerings' : $this->path('/academic/offerings/{id}/edit')), $response);
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame([$this->school, $this->users['SCHOOL_ADMIN'], 'subject_offerings', '192.0.2.51', null],
            array_map(static fn (string $k): mixed => $audit[$k], ['school_id', 'user_id', 'entity_type', 'ip_address', 'reason']));
        self::assertSame(match ($path) { '/academic/offerings' => 'SUBJECT_OFFERING_CREATED', '/academic/offerings/{id}' => 'SUBJECT_OFFERING_UPDATED', default => 'SUBJECT_OFFERING_STATUS_CHANGED' }, $audit['action']);
        $row = $this->row('SELECT * FROM subject_offerings WHERE id = ?', [$audit['entity_id']]);
        self::assertSame($this->school, $row['school_id']); self::assertSame($this->year, $row['academic_year_id']);
        self::assertSame($path === '/academic/offerings/{id}/status' ? 'INACTIVE' : 'ACTIVE', $row['status']);
        if ($path !== '/academic/offerings') { self::assertSame($this->offering, $row['id']); }
        self::assertSame($foreign, $this->row('SELECT * FROM subject_offerings WHERE id = ?', [$this->foreignOffering]));
    }

    #[DataProvider('yearStatuses')]
    public function test_edit_open_controls_and_closed_history_are_correct(string $state): void
    {
        $this->login(); $this->pdo->prepare('UPDATE academic_years SET status = ? WHERE id = ?')->execute([$state, $this->year]);
        $response = $this->request('GET', $this->path('/academic/offerings/{id}/edit'));
        self::assertSame(200, $response->status()); $xpath = $this->xpath($response->body());
        foreach (['2569', 'OWN', 'SCI', 'ACTIVE'] as $value) { self::assertStringContainsString($value, $response->body()); }
        self::assertSame(0, $xpath->query('//*[@name="academic_year_id"]')->length);
        self::assertSame($state === 'CLOSED' ? 0 : 2, $xpath->query('//form[@method="post"]')->length);
        if ($state !== 'CLOSED') {
            self::assertEqualsCanonicalizing([(string) $this->room, (string) $this->otherRoom], $this->optionIds($xpath, 'classroom_id'));
            self::assertEqualsCanonicalizing([(string) $this->subject, (string) $this->otherSubject], $this->optionIds($xpath, 'subject_id'));
            self::assertSame(['1', '2'], $this->optionIds($xpath, 'term_no'));
            self::assertSame('INACTIVE', $xpath->evaluate('string(//input[@name="status"]/@value)'));
        }
        $this->assertForms($response); $this->assertSafe($response);
    }
    public static function yearStatuses(): array { return [['DRAFT'], ['ACTIVE'], ['CLOSED']]; }

    public function test_inactive_historical_parents_display_but_cannot_be_replacement_options(): void
    {
        $this->login();
        $this->pdo->prepare("UPDATE classrooms SET status = 'INACTIVE' WHERE id = ?")->execute([$this->room]);
        $this->pdo->prepare("UPDATE subjects SET status = 'INACTIVE' WHERE id = ?")->execute([$this->subject]);
        foreach (['DRAFT', 'CLOSED'] as $state) {
            $this->pdo->prepare('UPDATE academic_years SET status = ? WHERE id = ?')->execute([$state, $this->year]);
            $response = $this->request('GET', $this->path('/academic/offerings/{id}/edit'));
            self::assertSame(200, $response->status()); $xpath = $this->xpath($response->body());
            foreach (['OWN', 'SCI', 'INACTIVE'] as $value) { self::assertStringContainsString($value, $response->body()); }
            self::assertNotContains((string) $this->room, $this->optionIds($xpath, 'classroom_id'));
            self::assertNotContains((string) $this->subject, $this->optionIds($xpath, 'subject_id'));
        }
    }

    public function test_update_changes_only_allowed_fields_and_ignores_any_forged_year(): void
    {
        $this->login();
        foreach ([$this->foreignYear, $this->activeYear, ['bad'], new stdClass()] as $forgedYear) {
            $this->pdo->beginTransaction();
            $response = $this->request('POST', $this->path('/academic/offerings/{id}'), $this->payload([
                'academic_year_id' => $forgedYear, 'classroom_id' => $this->otherRoom, 'subject_id' => $this->otherSubject,
            ]));
            self::assertEquals(Response::redirect($this->path('/academic/offerings/{id}/edit')), $response);
            $row = $this->row('SELECT * FROM subject_offerings WHERE id = ?', [$this->offering]);
            self::assertSame([$this->school, $this->year, $this->otherRoom, $this->otherSubject, 2, 'ACTIVE'],
                array_map(static fn (string $key): mixed => $row[$key], ['school_id', 'academic_year_id', 'classroom_id', 'subject_id', 'term_no', 'status']));
            $this->pdo->rollBack();
        }
    }

    public function test_foreign_missing_and_overflow_targets_are_indistinguishable(): void
    {
        $this->login(); $before = $this->snapshot();
        foreach ([['GET', '/edit', 404], ['POST', '', 422], ['POST', '/status', 422]] as [$method, $suffix, $status]) {
            $responses = [];
            foreach ([(string) $this->foreignOffering, '0', '999999999999999999999999'] as $id) {
                $response = $this->request($method, '/academic/offerings/' . $id . $suffix, $this->payload(['school_id' => $this->foreignSchool]), ['school_id' => $this->foreignSchool]);
                self::assertSame($status, $response->status()); $this->assertSafe($response);
                self::assertSame(0, $this->xpath($response->body())->query('//form')->length);
                self::assertSame($before, $this->snapshot()); $responses[] = $response;
            }
            self::assertEquals($responses[0], $responses[1]); self::assertEquals($responses[0], $responses[2]);
        }
    }

    public function test_status_round_trip_retains_id_and_noops_never_write(): void
    {
        $this->login(); $path = $this->path('/academic/offerings/{id}/status');
        foreach (['INACTIVE', 'ACTIVE'] as $status) {
            self::assertEquals(Response::redirect($this->path('/academic/offerings/{id}/edit')), $this->request('POST', $path, $this->payload(['status' => $status])));
            self::assertSame($status, $this->row('SELECT status FROM subject_offerings WHERE id = ?', [$this->offering])['status']);
            $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
            self::assertSame(302, $this->request('POST', $path, $this->payload(['status' => $status]))->status());
            self::assertSame(0, $this->pdo->writeAttempts); self::assertSame($before, $this->snapshot());
        }
        $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        self::assertSame(302, $this->request('POST', $this->path('/academic/offerings/{id}'), $this->payload(['term_no' => '1']))->status());
        self::assertSame(0, $this->pdo->writeAttempts); self::assertSame($before, $this->snapshot());
        $audits = $this->rows('SELECT action, entity_id FROM audit_logs ORDER BY id');
        self::assertSame([['action' => 'SUBJECT_OFFERING_STATUS_CHANGED', 'entity_id' => $this->offering], ['action' => 'SUBJECT_OFFERING_STATUS_CHANGED', 'entity_id' => $this->offering]], $audits);
    }

    public function test_inactive_parents_allow_inactivation_but_block_reactivation(): void
    {
        $this->login();
        foreach (['classrooms' => $this->room, 'subjects' => $this->subject] as $table => $id) {
            $this->pdo->beginTransaction();
            $this->pdo->prepare("UPDATE $table SET status = 'INACTIVE' WHERE id = ?")->execute([$id]);
            self::assertSame(302, $this->request('POST', $this->path('/academic/offerings/{id}/status'), $this->payload())->status());
            $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
            self::assertSame(302, $this->request('POST', $this->path('/academic/offerings/{id}/status'), $this->payload())->status());
            self::assertSame(0, $this->pdo->writeAttempts);
            $response = $this->request('POST', $this->path('/academic/offerings/{id}/status'), $this->payload(['status' => 'ACTIVE']));
            self::assertSame(422, $response->status()); $this->assertSafe($response); self::assertSame($before, $this->snapshot());
            $this->pdo->rollBack();
        }
    }

    public function test_closed_year_freezes_all_mutations_including_same_status(): void
    {
        $this->login(); $this->pdo->prepare("UPDATE academic_years SET status = 'CLOSED' WHERE id = ?")->execute([$this->year]);
        foreach (['ACTIVE', 'INACTIVE'] as $from) {
            $this->pdo->prepare('UPDATE subject_offerings SET status = ? WHERE id = ?')->execute([$from, $this->offering]);
            $before = $this->snapshot();
            foreach (self::mutations() as [$path]) {
                foreach (['ACTIVE', 'INACTIVE'] as $to) {
                    $this->pdo->writeAttempts = 0;
                    $response = $this->request('POST', $this->path($path), $this->payload(['status' => $to]));
                    self::assertSame(422, $response->status()); $this->assertSafe($response);
                    self::assertSame(0, $this->pdo->writeAttempts); self::assertSame($before, $this->snapshot());
                }
            }
        }
    }

    public function test_hostile_joined_values_error_redisplay_and_token_are_escaped(): void
    {
        $this->login(); $_SESSION['csrf_token'] = '" onfocus="alert(1)';
        foreach (['classrooms' => $this->room, 'subjects' => $this->subject] as $table => $id) {
            $this->pdo->prepare("UPDATE $table SET code = ?, name_th = ? WHERE id = ?")->execute([self::HOSTILE, self::HOSTILE, $id]);
        }
        $responses = [$this->request('GET', '/academic/offerings'), $this->request('GET', $this->path('/academic/offerings/{id}/edit')),
            $this->request('GET', '/academic/offerings/create', [], ['academic_year_id' => $this->year]),
            $this->request('POST', '/academic/offerings', $this->payload(['term_no' => 1]))];
        foreach ($responses as $i => $response) {
            self::assertSame($i === 3 ? 422 : 200, $response->status());
            self::assertStringContainsString(htmlspecialchars(self::HOSTILE, ENT_QUOTES, 'UTF-8'), $response->body());
            self::assertStringNotContainsString(self::HOSTILE, $response->body());
            self::assertSame(0, $this->xpath($response->body())->query('//*[@onfocus]')->length);
            $this->assertForms($response); $this->assertSafe($response);
        }
    }

    #[DataProvider('invalidStatuses')]
    public function test_invalid_status_type_or_value_is_safe_422(mixed $status): void
    {
        $this->login();
        $before = $this->snapshot();
        $response = $this->request('POST', $this->path('/academic/offerings/{id}/status'), $this->payload(['status' => $status]));
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


    #[DataProvider('mutations')]
    public function test_write_audit_failure_is_safe_422_with_atomic_rollback(string $path): void
    {
        $this->login();
        foreach (['INSERT INTO audit_logs', $path === '/academic/offerings' ? 'INSERT INTO subject_offerings' : 'UPDATE subject_offerings'] as $failure) {
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
        self::assertSame(302, $this->request('POST', '/academic/offerings', $this->payload(['ip_address' => '203.0.113.1']), ['ip_address' => '203.0.113.2'], ['REMOTE_ADDR' => $address, 'HTTP_X_FORWARDED_FOR' => '203.0.113.3'])->status());
        self::assertSame($expected, $this->row('SELECT ip_address FROM audit_logs')['ip_address']);
    }
    public static function ipAddresses(): array { return [['192.0.2.52', '192.0.2.52'], ['2001:db8::1', '2001:db8::1'], [null, null], ['invalid', null], [['bad'], null], [new stdClass(), null]]; }

    public function test_no_system_or_hard_delete_routes_exist(): void
    {
        $this->login();
        $before = $this->snapshot();
        self::assertSame(405, $this->request('DELETE', $this->path('/academic/offerings/{id}'))->status());
        self::assertSame(404, $this->request('POST', $this->path('/academic/offerings/{id}/delete'))->status());
        self::assertSame(404, $this->request('GET', '/system/offerings')->status());
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
        return array_replace(['_token' => $this->token(), 'academic_year_id' => (string) $this->year, 'classroom_id' => (string) $this->room, 'subject_id' => (string) $this->subject, 'term_no' => '2', 'status' => 'INACTIVE'], $overrides);
    }
    private function path(string $path): string { return str_replace('{id}', (string) $this->offering, $path); }
    private function request(string $method, string $path, array $post = [], array $query = [], array $server = []): Response
    {
        return $this->app->handle(new Request($method, $path, $query, $post, $server));
    }
    private function fixture(array $overrides = []): int
    {
        $values = array_replace(['school_id' => $this->school, 'academic_year_id' => $this->year, 'classroom_id' => $this->room, 'subject_id' => $this->subject, 'term_no' => 1, 'status' => 'ACTIVE'], $overrides);
        return $this->insert('INSERT INTO subject_offerings (school_id, academic_year_id, classroom_id, subject_id, term_no, status) VALUES (?, ?, ?, ?, ?, ?)', array_values($values));
    }
    private function fixtureYear(int $school, int $year, string $status): int
    {
        return $this->insert('INSERT INTO academic_years (school_id, year_be, status) VALUES (?, ?, ?)', [$school, $year, $status]);
    }
    private function fixtureRoom(int $school, int $year, string $code, string $status = 'ACTIVE'): int
    {
        $grade = $this->row("SELECT id FROM grade_levels WHERE code = 'P1'")['id'];
        return $this->insert('INSERT INTO classrooms (school_id, academic_year_id, grade_level_id, code, name_th, status) VALUES (?, ?, ?, ?, ?, ?)', [$school, $year, $grade, $code, 'ชื่อห้องเรียน', $status]);
    }
    private function fixtureSubject(int $school, string $code, string $status = 'ACTIVE'): int
    {
        return $this->insert('INSERT INTO subjects (school_id, code, name_th, status) VALUES (?, ?, ?, ?)', [$school, $code, 'ชื่อรายวิชา', $status]);
    }
    private function optionIds(DOMXPath $xpath, string $field): array
    {
        return $this->values($xpath->query('//select[@name="' . $field . '"]/option[@value!=""]/@value'));
    }
    private function grant(string $role, string $permission): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = ? AND p.code = ?')->execute([$role, $permission]);
    }
    private function snapshot(): array { return [$this->rows('SELECT * FROM subject_offerings ORDER BY id'), $this->rows('SELECT * FROM audit_logs ORDER BY id')]; }
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
        foreach (['school_id', 'teacher_id', 'user_id', 'actor', 'actor_user_id'] as $field) { self::assertSame(0, $xpath->query('//*[@name="' . $field . '"]')->length); }
        self::assertSame(0, $xpath->query('//form[contains(@action,"delete")]')->length);
        foreach ($xpath->query('//form[@method="post"]') as $form) { self::assertSame($this->token(), $xpath->evaluate('string(.//input[@name="_token"]/@value)', $form)); }
    }
    private function assertSafe(Response $response): void
    {
        foreach (['SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', '/Applications/MAMP/', 'htdocs/app/', 'uq_offering', 'fk_offering', 'private-db-details', 'FOREIGN_SECRET', 'Foreign School Secret'] as $secret) { self::assertStringNotContainsString($secret, $response->body()); }
    }
}

/** Service commits stay inside the HTTP fixture rollback. */
final class OfferingHttpPDO extends PDO
{
    public int $depth = 0;
    public int $writeAttempts = 0;
    public ?string $failPrepare = null;
    public bool $failureTriggered = false;
    public function beginTransaction(): bool
    {
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT offering_http_' . $this->depth) !== false;
        ++$this->depth;
        return $ok;
    }
    public function commit(): bool
    {
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT offering_http_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else { $ok = $this->exec('ROLLBACK TO SAVEPOINT offering_http_' . ($this->depth - 1)) !== false; $this->exec('RELEASE SAVEPOINT offering_http_' . ($this->depth - 1)); }
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
