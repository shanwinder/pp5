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

final class EnrollmentHttpTest extends TestCase
{
    private EnrollmentHttpPDO $pdo;
    private Application $app;
    private int $school;
    private int $foreignSchool;
    private int $student;
    private int $foreignStudent;
    private array $users;
    private int $year;
    private int $closedYear;
    private int $foreignYear;
    private int $grade;
    private int $otherGrade;
    private int $candidate;
    private int $enrollment;
    private int $foreignEnrollment;
    private array $rooms;
    private const HOSTILE = '<script>"XSS"</script>';
    private const NATIONAL = '1234567890123';

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new EnrollmentHttpPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->school = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['enrollment-http-a', 'School A']);
        $this->foreignSchool = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['enrollment-http-b', 'Foreign School Secret']);
        $this->student = $this->fixture($this->school, 'OWN');
        $this->foreignStudent = $this->fixture($this->foreignSchool, 'FOREIGN_SECRET');
        foreach (['SCHOOL_ADMIN', 'ACADEMIC_ADMIN', 'VIEWER', 'SYSTEM_ADMIN'] as $role) {
            $id = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['enrollment-http-' . $role, 'unused', $role]);
            $school = $role === 'SYSTEM_ADMIN' ? null : $this->school;
            if ($school !== null) { $this->insert('INSERT INTO school_memberships (school_id, user_id) VALUES (?, ?)', [$school, $id]); }
            $this->insert('INSERT INTO user_role_assignments (school_id, user_id, role_id) SELECT ?, ?, id FROM roles WHERE code = ?', [$school, $id, $role]);
            $this->users[$role] = $id;
        }
        $this->year = $this->insert('INSERT INTO academic_years (school_id,year_be,status,start_date,end_date) VALUES (?,?,?,?,?)',[$this->school,2569,'ACTIVE','2026-05-01','2027-03-31']);
        $this->closedYear = $this->insert('INSERT INTO academic_years (school_id,year_be,status) VALUES (?,?,?)',[$this->school,2568,'CLOSED']);
        $this->foreignYear = $this->insert('INSERT INTO academic_years (school_id,year_be) VALUES (?,?)',[$this->foreignSchool,2579]);
        $this->grade = (int)$this->pdo->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        $this->otherGrade = (int)$this->pdo->query("SELECT id FROM grade_levels WHERE code='P2'")->fetchColumn();
        $this->candidate = $this->fixture($this->school,'CANDIDATE');
        $this->rooms = [];
        foreach ([['a',$this->school,$this->year,$this->grade],['b',$this->school,$this->year,$this->grade],['grade',$this->school,$this->year,$this->otherGrade],['closed',$this->school,$this->closedYear,$this->grade],['foreign',$this->foreignSchool,$this->foreignYear,$this->grade]] as [$key,$school,$year,$grade]) {
            $this->rooms[$key]=$this->insert('INSERT INTO classrooms (school_id,academic_year_id,grade_level_id,code,name_th) VALUES (?,?,?,?,?)',[$school,$year,$grade,$key,$key==='foreign' ? 'FOREIGN_SECRET' : 'Class '.$key]);
        }
        $this->enrollment=$this->insert('INSERT INTO student_enrollments (school_id,academic_year_id,student_id,grade_level_id,entry_date) VALUES (?,?,?,?,?)',[$this->school,$this->year,$this->student,$this->grade,'2026-05-01']);
        $this->foreignEnrollment=$this->insert('INSERT INTO student_enrollments (school_id,academic_year_id,student_id,grade_level_id) VALUES (?,?,?,?)',[$this->foreignSchool,$this->foreignYear,$this->foreignStudent,$this->grade]);
        $this->insert('INSERT INTO student_classroom_placements (school_id,academic_year_id,grade_level_id,enrollment_id,classroom_id) VALUES (?,?,?,?,?)',[$this->school,$this->year,$this->grade,$this->enrollment,$this->rooms['a']]);
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
        return [['GET','/academic/enrollments','STUDENT_VIEW'],['GET','/academic/enrollments/create','ENROLLMENT_MANAGE'],
            ['POST','/academic/enrollments','ENROLLMENT_MANAGE'],['GET','/academic/enrollments/{id}/edit','ENROLLMENT_MANAGE'],
            ['POST','/academic/enrollments/{id}/placement','ENROLLMENT_MANAGE'],['POST','/academic/enrollments/{id}/status','ENROLLMENT_MANAGE']];
    }
    #[DataProvider('routes')]
    public function test_routes_require_exact_school_permission_and_numeric_target(string $method, string $path, string $permission): void
    {
        $dispatcher = simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php');
        $route = $dispatcher->dispatch($method, $this->path($path));
        self::assertSame(Dispatcher::FOUND, $route[0], 'Task 5 enrollment route missing');
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
        self::assertStringNotContainsString('/academic/enrollments/create', $response->body());
        self::assertStringNotContainsString('/edit', $response->body());
        self::assertSame(0, $this->xpath($response->body())->query('//form[@method="post" and not(@action="/logout")]')->length);
    }


    public function test_list_filters_use_session_tenant_and_do_not_search_or_expose_national_id(): void
    {
        $this->login();
        foreach ([[],['academic_year_id'=>(string)$this->year],['grade_level_id'=>(string)$this->grade,'classroom_id'=>(string)$this->rooms['a'],'status'=>'ACTIVE','q'=>"\u{3000}OWN\u{00A0}"]] as $filters) {
            $response=$this->request('GET','/academic/enrollments',[],['school_id'=>$this->foreignSchool,...$filters]);
            self::assertSame(200,$response->status()); $this->assertSafe($response);
            self::assertSame(1,$this->xpath($response->body())->query('//tbody/tr')->length);
            foreach (['OWN','2569','Class a','ACTIVE'] as $value) { self::assertStringContainsString($value,$response->body()); }
        }
        foreach ([['q'=>self::NATIONAL],['q'=>'%'],['q'=>'_'],['q'=>str_repeat('ก',100)],['status'=>'WITHDRAWN'],['grade_level_id'=>(string)$this->otherGrade]] as $filters) {
            $response=$this->request('GET','/academic/enrollments',[],$filters); self::assertSame(200,$response->status());
            self::assertSame(0,$this->xpath($response->body())->query('//tbody/tr')->length); $this->assertSafe($response);
        }
    }

    public function test_foreign_missing_and_wrong_year_filters_are_non_enumerating(): void
    {
        $this->login();
        foreach (['/academic/enrollments','/academic/enrollments/create'] as $path) {
            $foreign=$this->request('GET',$path,[],['academic_year_id'=>(string)$this->foreignYear]);
            self::assertSame(404,$foreign->status());
            self::assertEquals($foreign,$this->request('GET',$path,[],['academic_year_id'=>'999999999'])); $this->assertSafe($foreign);
        }
        foreach ([$this->rooms['foreign'],$this->rooms['closed'],999999999] as $room) {
            $response=$this->request('GET','/academic/enrollments',[],['academic_year_id'=>(string)$this->year,'classroom_id'=>(string)$room]);
            self::assertSame(404,$response->status()); $this->assertSafe($response);
        }
    }

    #[DataProvider('badFilters')]
    public function test_malformed_filters_never_reach_strict_signatures(string $field,mixed $value,int $status): void
    {
        $this->login(); $before=$this->snapshot();
        $response=$this->request('GET','/academic/enrollments',[],[$field=>$value]);
        self::assertSame($status,$response->status()); $this->assertSafe($response); self::assertSame($before,$this->snapshot());
        if (in_array($field,['academic_year_id','grade_level_id'],true)) {
            self::assertSame(404,$this->request('GET','/academic/enrollments/create',[],[$field=>$value])->status());
        }
    }
    public static function badFilters(): array
    {
        $cases=[];
        foreach (['academic_year_id','grade_level_id','classroom_id'] as $field) { foreach ([['x'],new stdClass(),true,1.5,'0','-1','abc','9999999999999999999999'] as $value) { $cases[]=[$field,$value,404]; } }
        foreach (['status','q'] as $field) { foreach ([['x'],new stdClass(),true,1,1.5] as $value) { $cases[]=[$field,$value,422]; } }
        return [...$cases,['status','INACTIVE',422],['q',"\xFF",422],['q',"a\nb",422],['q',str_repeat('ก',101),422]];
    }

    public function test_create_options_are_open_year_active_student_grade_and_matching_classrooms(): void
    {
        $this->login(); $inactive=$this->fixture($this->school,'INACTIVE_SECRET','INACTIVE');
        $this->pdo->prepare("UPDATE classrooms SET status='INACTIVE' WHERE id=?")->execute([$this->rooms['b']]);
        $response=$this->request('GET','/academic/enrollments/create',[],['academic_year_id'=>(string)$this->year,'grade_level_id'=>(string)$this->grade,'school_id'=>$this->foreignSchool]);
        self::assertSame(200,$response->status()); $this->assertSafe($response); $this->assertForms($response);
        $xpath=$this->xpath($response->body());
        self::assertSame([(string)$this->year],array_values(array_filter($this->values($xpath->query('//select[@name="academic_year_id"]/option/@value')),static fn($v)=>$v!=='')));
        self::assertEqualsCanonicalizing([(string)$this->student,(string)$this->candidate],array_values(array_filter($this->values($xpath->query('//select[@name="student_id"]/option/@value')),static fn($v)=>$v!=='')));
        self::assertSame(['',(string)$this->rooms['a']],$this->values($xpath->query('//select[@name="classroom_id"]/option/@value')));
        self::assertStringNotContainsString('INACTIVE_SECRET',$response->body());
        self::assertEqualsCanonicalizing(['_token','academic_year_id','student_id','grade_level_id','classroom_id','entry_date'],$this->values($xpath->query('//form[@method="post" and not(@action="/logout")]//*[@name]/@name')));
        self::assertSame(404,$this->request('GET','/academic/enrollments/create',[],['academic_year_id'=>(string)$this->closedYear])->status());
    }

    public function test_foreign_and_missing_enrollment_targets_have_identical_get_and_post_responses(): void
    {
        $this->login(); $before=$this->snapshot();
        foreach ([['GET','/academic/enrollments/{id}/edit',404],['POST','/academic/enrollments/{id}/placement',422],['POST','/academic/enrollments/{id}/status',422]] as [$method,$path,$status]) {
            $responses=[];
            foreach ([(string)$this->foreignEnrollment,'0','99999999999999999999999'] as $id) {
                $response=$this->request($method,str_replace('{id}',$id,$path),$this->payload(['school_id'=>$this->foreignSchool]),['school_id'=>$this->foreignSchool]);
                self::assertSame($status,$response->status()); $this->assertSafe($response); self::assertSame($before,$this->snapshot()); $responses[]=$response;
            }
            self::assertEquals($responses[0],$responses[1]); self::assertEquals($responses[0],$responses[2]);
        }
    }

    public static function mutations(): array { return [['/academic/enrollments'],['/academic/enrollments/{id}/placement'],['/academic/enrollments/{id}/status']]; }
    #[DataProvider('mutations')]
    public function test_csrf_precedes_representation_service_transaction_and_writes(string $path): void
    {
        $this->login(); $before=$this->snapshot();
        foreach ([null,'bad',['bad'],new stdClass()] as $token) {
            $this->pdo->beginAttempts=0; $this->pdo->writeAttempts=0;
            $response=$this->request('POST',$this->path($path),$this->payload(['_token'=>$token,'academic_year_id'=>new stdClass(),'classroom_id'=>['x'],'status'=>['x'],'exit_date'=>new stdClass()]));
            self::assertSame(419,$response->status()); self::assertSame(0,$this->pdo->beginAttempts); self::assertSame(0,$this->pdo->writeAttempts); self::assertSame($before,$this->snapshot());
        }
    }

    #[DataProvider('badFields')]
    public function test_malformed_post_shapes_are_safe_422_before_service(string $field,mixed $value): void
    {
        $this->login(); $before=$this->snapshot();
        $paths=in_array($field,['status','exit_date'],true) ? ['/academic/enrollments/{id}/status'] : ($field==='classroom_id' ? ['/academic/enrollments','/academic/enrollments/{id}/placement'] : ['/academic/enrollments']);
        foreach ($paths as $path) {
            $this->pdo->beginAttempts=0; $this->pdo->writeAttempts=0;
            $response=$this->request('POST',$this->path($path),$this->payload([$field=>$value]));
            self::assertSame(422,$response->status()); $this->assertSafe($response); self::assertSame(0,$this->pdo->beginAttempts); self::assertSame(0,$this->pdo->writeAttempts); self::assertSame($before,$this->snapshot());
        }
    }
    public static function badFields(): array
    {
        $cases=[];
        foreach (['academic_year_id','student_id','grade_level_id','classroom_id','entry_date','status','exit_date'] as $field) {
            foreach ([['x'],new stdClass(),true,1.5] as $value) { $cases[]=[$field,$value]; }
        }
        foreach (['academic_year_id','student_id','grade_level_id'] as $field) { foreach ([null,'','0','-1','abc','99999999999999999999'] as $value) { $cases[]=[$field,$value]; } }
        return $cases;
    }

    public function test_foreign_parents_wrong_classrooms_and_invalid_dates_are_rejected_atomically(): void
    {
        $this->login(); $before=$this->snapshot();
        foreach ([['student_id'=>(string)$this->foreignStudent],['student_id'=>'999999999'],['academic_year_id'=>(string)$this->foreignYear],['academic_year_id'=>'999999999'],['classroom_id'=>(string)$this->rooms['foreign']],['classroom_id'=>(string)$this->rooms['closed']],['classroom_id'=>(string)$this->rooms['grade']],['entry_date'=>'2026-02-30'],['entry_date'=>'2026-04-30']] as $changes) {
            $response=$this->request('POST','/academic/enrollments',$this->payload($changes)); self::assertSame(422,$response->status()); $this->assertSafe($response); self::assertSame($before,$this->snapshot());
        }
        foreach ([$this->rooms['foreign'],$this->rooms['closed'],$this->rooms['grade'],999999999] as $room) {
            $response=$this->request('POST',$this->path('/academic/enrollments/{id}/placement'),$this->payload(['classroom_id'=>(string)$room])); self::assertSame(422,$response->status()); self::assertSame($before,$this->snapshot());
        }
    }

    #[DataProvider('mutations')]
    public function test_mutations_use_session_authority_and_deterministic_edit_redirect(string $path): void
    {
        $this->login(); $foreign=$this->row('SELECT * FROM student_enrollments WHERE id=?',[$this->foreignEnrollment]);
        $response=$this->request('POST',$this->path($path),$this->payload(['school_id'=>$this->foreignSchool,'actor_user_id'=>$this->users['SYSTEM_ADMIN'],'user_id'=>$this->users['SYSTEM_ADMIN']]),['school_id'=>$this->foreignSchool],['REMOTE_ADDR'=>'192.0.2.42']);
        $id=$path==='/academic/enrollments' ? $this->row('SELECT id FROM student_enrollments WHERE student_id=?',[$this->candidate])['id'] : $this->enrollment;
        self::assertEquals(Response::redirect('/academic/enrollments/'.$id.'/edit'),$response);
        foreach ($this->rows('SELECT * FROM audit_logs') as $audit) {
            self::assertSame([$this->school,$this->users['SCHOOL_ADMIN'],'192.0.2.42'],[$audit['school_id'],$audit['user_id'],$audit['ip_address']]);
            $this->assertSafe(new Response(json_encode($audit,JSON_UNESCAPED_UNICODE)));
        }
        self::assertSame($foreign,$this->row('SELECT * FROM student_enrollments WHERE id=?',[$this->foreignEnrollment]));
    }

    public function test_move_unassign_and_terminal_transitions_keep_history_and_hide_invalid_controls(): void
    {
        $this->login();
        $response=$this->request('GET',$this->path('/academic/enrollments/{id}/edit')); self::assertSame(200,$response->status());
        self::assertSame(2,$this->xpath($response->body())->query('//form[@method="post" and not(@action="/logout")]')->length); $this->assertForms($response);
        foreach ([(string)$this->rooms['b'],''] as $room) { self::assertSame(302,$this->request('POST',$this->path('/academic/enrollments/{id}/placement'),$this->payload(['classroom_id'=>$room]))->status()); }
        self::assertSame(['ENDED','ENDED'],array_column($this->rows('SELECT * FROM student_classroom_placements WHERE enrollment_id=? ORDER BY id',[$this->enrollment]),'status'));
        self::assertSame(302,$this->request('POST',$this->path('/academic/enrollments/{id}/status'),$this->payload(['status'=>'TRANSFERRED_OUT']))->status());
        self::assertSame('TRANSFERRED_OUT',$this->row('SELECT status FROM student_enrollments WHERE id=?',[$this->enrollment])['status']);
        $response=$this->request('GET',$this->path('/academic/enrollments/{id}/edit')); self::assertSame(200,$response->status());
        self::assertSame(0,$this->xpath($response->body())->query('//form[@method="post" and not(@action="/logout")]')->length); self::assertStringContainsString('2026-06-01',$response->body());
    }

    public function test_closed_year_edit_is_historical_read_only_and_posts_still_reject(): void
    {
        $this->login(); $this->pdo->prepare("UPDATE academic_years SET status='CLOSED' WHERE id=?")->execute([$this->year]); $before=$this->snapshot();
        $response=$this->request('GET',$this->path('/academic/enrollments/{id}/edit')); self::assertSame(200,$response->status());
        self::assertSame(0,$this->xpath($response->body())->query('//form[@method="post" and not(@action="/logout")]')->length);
        foreach (['OWN','2569','Class a','2026-05-01'] as $text) { self::assertStringContainsString($text,$response->body()); }
        foreach (['/academic/enrollments/{id}/placement','/academic/enrollments/{id}/status'] as $path) {
            self::assertSame(422,$this->request('POST',$this->path($path),$this->payload(['classroom_id'=>(string)$this->rooms['a'],'status'=>'ACTIVE']))->status()); self::assertSame($before,$this->snapshot());
        }
    }

    public function test_hostile_student_classroom_and_grade_names_are_escaped(): void
    {
        $this->login();
        $this->pdo->prepare('UPDATE students SET first_name_th=? WHERE id=?')->execute([self::HOSTILE,$this->student]);
        $this->pdo->prepare('UPDATE classrooms SET name_th=? WHERE id=?')->execute([self::HOSTILE,$this->rooms['a']]);
        $this->pdo->prepare('UPDATE grade_levels SET name_th=? WHERE id=?')->execute([self::HOSTILE,$this->grade]);
        foreach (['/academic/enrollments','/academic/enrollments/create',$this->path('/academic/enrollments/{id}/edit')] as $path) {
            $response=$this->request('GET',$path,[],['academic_year_id'=>(string)$this->year,'grade_level_id'=>(string)$this->grade]);
            self::assertSame(200,$response->status()); self::assertStringContainsString(htmlspecialchars(self::HOSTILE,ENT_QUOTES,'UTF-8'),$response->body());
            self::assertStringNotContainsString(self::HOSTILE,$response->body()); self::assertSame(0,$this->xpath($response->body())->query('//script[not(@src="/assets/app.js")]')->length); $this->assertSafe($response);
        }
    }

    #[DataProvider('mutations')]
    public function test_real_audit_failure_rolls_back_all_http_mutations_safely(string $path): void
    {
        $this->login(); $before=$this->snapshot(); $this->pdo->failPrepare='INSERT INTO audit_logs';
        $response=$this->request('POST',$this->path($path),$this->payload());
        self::assertSame(422,$response->status()); self::assertTrue($this->pdo->failureTriggered); $this->assertSafe($response); self::assertSame($before,$this->snapshot());
    }

    public function test_unexpected_read_failure_uses_application_safe_error(): void
    {
        $this->login(); $this->pdo->failPrepare='FROM student_enrollments';
        $response=$this->request('GET','/academic/enrollments'); self::assertSame(500,$response->status()); self::assertTrue($this->pdo->failureTriggered); $this->assertSafe($response);
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
        return array_replace(['_token'=>$this->token(),'academic_year_id'=>(string)$this->year,'student_id'=>(string)$this->candidate,
            'grade_level_id'=>(string)$this->grade,'classroom_id'=>(string)$this->rooms['b'],'entry_date'=>'2026-05-01','status'=>'WITHDRAWN','exit_date'=>'2026-06-01'],$overrides);
    }
    private function path(string $path): string { return str_replace('{id}', (string) $this->enrollment, $path); }
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
    private function snapshot(): array { return [$this->rows('SELECT * FROM students ORDER BY id'), $this->rows('SELECT * FROM student_enrollments ORDER BY id'), $this->rows('SELECT * FROM student_classroom_placements ORDER BY id'), $this->rows('SELECT * FROM audit_logs ORDER BY id')]; }
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
        foreach (['school_id', 'user_id', 'actor', 'actor_user_id'] as $field) { self::assertSame(0, $xpath->query('//*[@name="' . $field . '"]')->length); }
        self::assertSame(0, $xpath->query('//form[contains(@action,"delete")]')->length);
        foreach ($xpath->query('//form[@method="post"]') as $form) { self::assertSame($this->token(), $xpath->evaluate('string(.//input[@name="_token"]/@value)', $form)); }
    }
    private function assertSafe(Response $response): void
    {
        foreach (['SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', '/Applications/MAMP/', 'htdocs/app/', 'uq_student', 'fk_student', 'private-db-details', 'FOREIGN_SECRET', 'Foreign School Secret', self::NATIONAL, '0000000000001'] as $secret) { self::assertStringNotContainsString($secret, $response->body()); }
    }
}

/** Service commits stay inside the HTTP fixture rollback. */
final class EnrollmentHttpPDO extends PDO
{
    public int $depth = 0;
    public int $writeAttempts = 0;
    public int $beginAttempts = 0;
    public ?string $failPrepare = null;
    public bool $failureTriggered = false;
    public function beginTransaction(): bool
    {
        ++$this->beginAttempts;
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT enrollment_http_' . $this->depth) !== false;
        ++$this->depth;
        return $ok;
    }
    public function commit(): bool
    {
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT enrollment_http_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else { $ok = $this->exec('ROLLBACK TO SAVEPOINT enrollment_http_' . ($this->depth - 1)) !== false; $this->exec('RELEASE SAVEPOINT enrollment_http_' . ($this->depth - 1)); }
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
