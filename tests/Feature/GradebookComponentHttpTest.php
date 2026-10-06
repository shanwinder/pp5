<?php
declare(strict_types=1);

use FastRoute\Dispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/GradebookComponentFixtures.php';

final class GradebookComponentHttpTest extends TestCase
{
    use GradebookComponentFixtures;

    public static function routes(): iterable
    {
        yield ['GET', 'setup', 'setup']; yield ['POST', 'create', 'store'];
        yield ['POST', 'update', 'update']; yield ['POST', 'status', 'changeStatus'];
    }
    #[DataProvider('routes')]
    public function testRouteMetadataAndPermissionBoundary(string $method, string $action, string $handler): void
    {
        $route = FastRoute\simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php')->dispatch($method, $this->path($action));
        self::assertSame(Dispatcher::FOUND, $route[0]);
        self::assertSame(['action' => 'gradebook.components.' . $handler, 'protected' => true, 'context' => 'SCHOOL', 'permission' => 'GRADEBOOK_COMPONENT_MANAGE'], $route[1]);
        $before = $this->snapshot();
        self::assertSame(302, $this->request($method, $this->path($action))->status());
        foreach (['SYSTEM_ADMIN','SUBJECT_TEACHER','EXECUTIVE','HOMEROOM_TEACHER','VIEWER'] as $role) {
            $this->login($role); self::assertSame(403, $this->request($method, $this->path($action), $this->payload())->status(), $role);
        }
        foreach ([null, [], 'bad', $this->f['schoolB']] as $school) {
            $this->login(); $_SESSION['school_id'] = $school;
            self::assertContains($this->request($method, $this->path($action), $this->payload())->status(), [302,403]);
        }
        self::assertSame($before, $this->snapshot());
    }
    public function testAdministratorsSeeDetailsAllComponentsAndContextualNavigation(): void
    {
        foreach (['SCHOOL_ADMIN','ACADEMIC_ADMIN'] as $role) {
            $this->login($role); $r = $this->request('GET', $this->path()); self::assertSame(200, $r->status()); $this->assertSafe($r->body());
            foreach (['2569','ห้องทดสอบ','วิทยาศาสตร์','EXAM','OLD','20.00','การเก็บคะแนน','รายการคะแนน','หัวข้อคะแนน','เพิ่มช่องคะแนน','คะแนนเต็ม','ปิดใช้งาน / ประวัติ'] as $text) { self::assertStringContainsString($text, $r->body()); }
            self::assertSame(0, $this->xpath($r->body())->query('//main//input[@name="code" or @name="sort_order"]')->length);
            $x = $this->xpath($r->body()); self::assertSame(2, $x->query('//*[@data-component-id]')->length);
            self::assertSame(5, $x->query('//main//form[@method="post"]')->length);
            foreach ($x->query('//form[@method="post"]') as $form) { self::assertSame($this->token(), $x->evaluate('string(.//input[@name="_token"]/@value)', $form)); }
            foreach (['school_id','academic_year_id','actor_user_id','user_id','permission','role'] as $name) { self::assertSame(0, $x->query('//*[@name="' . $name . '"]')->length); }
            $list = $this->request('GET', '/academic/offerings'); self::assertSame(200, $list->status());
            self::assertStringContainsString('href="' . $this->path() . '"', $list->body());
            self::assertStringNotContainsString('/gradebook/' . $this->f['offeringB'] . '/setup', $list->body());
        }
    }
    public function testCreateUpdateAndStatusPreserveCanonicalValuesAuditAndNoOps(): void
    {
        $this->login(); $before = $this->snapshot();
        $r = $this->request('POST', $this->path('create'), $this->payload()); self::assertSame(302, $r->status());
        $after = $this->snapshot(); $component = end($after['gradebook_components']);
        self::assertSame('1.50', $component['max_score']); self::assertCount(count($before['audit_logs']) + 1, $after['audit_logs']);
        $this->f['componentA'] = $component['id'];
        self::assertSame(302, $this->request('POST', $this->path('update'), $this->payload())->status()); self::assertSame($after, $this->snapshot());
        self::assertSame(302, $this->request('POST', $this->path('update'), $this->payload(['name_th' => 'แก้ไข', 'max_score' => '25']))->status());
        self::assertSame('25.00', $this->row('gradebook_components', $component['id'])['max_score']);
        foreach (['ACTIVE','INACTIVE','INACTIVE','ACTIVE'] as $status) {
            $before = $this->snapshot(); $old = $this->row('gradebook_components', $component['id'])['status'];
            self::assertSame(302, $this->request('POST', $this->path('status'), $this->payload(['status' => $status]))->status());
            self::assertSame($status, $this->row('gradebook_components', $component['id'])['status']);
            self::assertCount(count($before['audit_logs']) + ($old === $status ? 0 : 1), $this->snapshot()['audit_logs']);
        }
    }
    public static function invalid(): iterable
    {
        foreach (['code','name_th','max_score','sort_order','status'] as $field) {
            foreach ([null, [], new stdClass(), true, 1.5, ''] as $value) { yield [$field, $value]; }
        }
        foreach (['1e2','1.234','100000','0','10,5'] as $value) { yield ['max_score', $value]; }
        yield ['status', 'DELETED']; yield ['code', "bad\ncode"]; yield ['sort_order', '65536'];
    }
    #[DataProvider('invalid')]
    public function testMalformedFieldsReturnSafe422WithoutWrites(string $field, mixed $value): void
    {
        $this->login(); $before = $this->snapshot();
        foreach ($field === 'status' ? ['status'] : ['create','update'] as $action) {
            $r = $this->request('POST', $this->path($action), $this->payload([$field => $value]));
            self::assertSame(422, $r->status()); $this->assertSafe($r->body());
        }
        self::assertSame($before, $this->snapshot());
    }
    public function testEveryPostChecksCsrfBeforeParsingOrWrites(): void
    {
        $this->login(); $before = $this->snapshot();
        foreach (['create','update','status'] as $action) {
            foreach ([null, [], '', 'wrong'] as $token) {
                $this->pdo->queries = [];
                self::assertSame(419, $this->request('POST', $this->path($action), ['_token' => $token, 'code' => [], 'max_score' => [], 'status' => []])->status());
                self::assertSame([], array_values(array_filter($this->pdo->queries, fn ($sql) => preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $sql))));
                self::assertSame($before, $this->snapshot());
            }
        }
    }
    public static function frozenParents(): iterable { yield ['Closed']; yield ['Inactive']; }
    #[DataProvider('frozenParents')]
    public function testHistoricalReadOnlyAndForgedPostDenied(string $key): void
    {
        $this->login(); $this->pdo->prepare("UPDATE gradebook_components SET status='INACTIVE' WHERE id=?")->execute([$this->f['component' . $key]]);
        $r = $this->request('GET', $this->path('setup', $key)); self::assertSame(200, $r->status());
        self::assertStringContainsString('EXAM', $r->body()); self::assertStringContainsString('ปิดใช้งาน / ประวัติ', $r->body());
        self::assertSame(0, $this->xpath($r->body())->query('//main//form[@method="post"]')->length);
        $before = $this->snapshot();
        foreach (['create','update','status'] as $action) {
            foreach (['ACTIVE','INACTIVE'] as $status) { self::assertSame(422, $this->request('POST', $this->path($action, $key), $this->payload(['status' => $status]))->status()); }
        }
        self::assertSame($before, $this->snapshot());
    }
    public function testHistoryProtectionAndDuplicateErrorsThroughHttp(): void
    {
        $this->login(); $this->score(null); $before = $this->snapshot();
        foreach ([['create', ['code' => 'exam']], ['update', ['code' => 'OLD']], ['update', ['max_score' => '25']]] as [$action, $fields]) {
            $r = $this->request('POST', $this->path($action), $this->payload($fields)); self::assertSame(422, $r->status()); $this->assertSafe($r->body());
        }
        self::assertSame($before, $this->snapshot());
        self::assertSame(302, $this->request('POST', $this->path('update'), $this->payload(['code' => 'LABEL', 'max_score' => '20']))->status());
        self::assertSame($before['gradebook_scores'], $this->snapshot()['gradebook_scores']);
    }
    public function testDynamicUnscopedGrantAndRevocationControlRouteAndNavigation(): void
    {
        $this->pdo->exec("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code IN ('GRADEBOOK_COMPONENT_MANAGE','ACADEMIC_SETUP_VIEW')");
        $this->login('VIEWER'); self::assertSame(200, $this->request('GET', $this->path())->status());
        self::assertStringContainsString('href="' . $this->path() . '"', $this->request('GET', '/academic/offerings')->body());
        foreach (['create','update','status'] as $action) { self::assertSame(302, $this->request('POST', $this->path($action), $this->payload(['code' => $action]))->status()); }
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='SCHOOL_ADMIN' AND p.code='GRADEBOOK_COMPONENT_MANAGE'");
        $this->login(); $before = $this->snapshot();
        self::assertStringNotContainsString('/gradebook/', $this->request('GET', '/academic/offerings')->body());
        foreach (self::routes() as [$method,$action]) { self::assertSame(403, $this->request($method, $this->path($action), $this->payload())->status()); }
        self::assertSame($before, $this->snapshot());
    }
    public function testAllTextIsEscapedInLabelsAndFormAttributes(): void
    {
        $this->login(); $hostile = '<script>alert("x")</script>';
        $this->pdo->prepare('UPDATE gradebook_components SET code=?,name_th=? WHERE id=?')->execute([$hostile,$hostile,$this->f['componentA']]);
        foreach (['subjects' => 'subjectA','classrooms' => 'roomA'] as $table => $key) {
            $this->pdo->prepare("UPDATE {$table} SET code=?,name_th=? WHERE id=?")->execute([$hostile,$hostile,$this->f[$key]]);
        }
        $r = $this->request('GET', $this->path()); self::assertSame(200, $r->status());
        self::assertStringNotContainsString($hostile, $r->body()); self::assertStringContainsString(htmlspecialchars($hostile, ENT_QUOTES, 'UTF-8'), $r->body());
        self::assertSame(0, $this->xpath($r->body())->query('//script[not(starts-with(@src,"/assets/app.js?v="))]')->length);
        self::assertStringContainsString('รหัสอ้างอิง: ' . htmlspecialchars($hostile, ENT_QUOTES, 'UTF-8'), $r->body());
        self::assertSame(0, $this->xpath($r->body())->query('//form[@action="' . $this->path('update') . '"]//input[@name="code"]')->length);
    }
    public function testUnexpectedReadIsGeneric500AndAuditFailureIsSafe422(): void
    {
        $this->login(); $before = $this->snapshot(); $this->pdo->failPrepare = 'FROM gradebook_components';
        $r = $this->request('GET', $this->path()); self::assertSame(500, $r->status()); self::assertSame('Internal Server Error', $r->body());
        self::assertTrue($this->pdo->failureTriggered); $this->pdo->failureTriggered = false; $this->pdo->failPrepare = 'INSERT INTO audit_logs';
        $r = $this->request('POST', $this->path('create'), $this->payload()); self::assertSame(422, $r->status()); $this->assertSafe($r->body());
        self::assertTrue($this->pdo->failureTriggered); self::assertSame($before, $this->snapshot());
    }
    public function testNoDeleteAndMalformedRouteIdsAreSafe(): void
    {
        $this->login(); $before = $this->snapshot();
        self::assertSame(405, $this->request('DELETE', $this->path('update'))->status());
        self::assertSame(404, $this->request('GET', '/gradebook/abc/setup')->status());
        self::assertSame(404, $this->request('GET', '/gradebook/99999999999999999999999/setup')->status());
        self::assertSame(422, $this->request('POST', '/gradebook/99999999999999999999999/components', $this->payload())->status());
        self::assertSame($before, $this->snapshot());
    }

    public function testTeacherFormAllocatesCodeAndAppendOrderUnderExistingAuditTransaction(): void
    {
        $this->login();
        $this->insert('gradebook_components', ['school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'],
            'subject_offering_id' => $this->f['offeringA'], 'code' => 'SCORE001', 'name_th' => 'เดิม', 'max_score' => '0.01', 'sort_order' => 65535]);
        $before = $this->snapshot();
        $response = $this->request('POST', $this->path('create'), ['_token' => $this->token(), 'name_th' => 'งานเก็บคะแนน', 'max_score' => '1.25']);
        self::assertSame(302, $response->status());
        $rows = $this->rows('SELECT * FROM gradebook_components WHERE subject_offering_id=? ORDER BY id DESC', [$this->f['offeringA']]);
        self::assertSame('SCORE002', $rows[0]['code']);
        self::assertSame(65535, (int) $rows[0]['sort_order']);
        self::assertSame('1.25', $rows[0]['max_score']);
        self::assertCount(count($before['audit_logs']) + 1, $this->snapshot()['audit_logs']);
        $page = $this->request('GET', $this->path());
        self::assertStringContainsString('คะแนนเต็มรวมที่ใช้งานอยู่: 21.26 คะแนน', $page->body());
        self::assertStringNotContainsString('ต้องรวม 100 คะแนน', $page->body());
        self::assertSame(422, $this->request('POST', $this->path('create'), ['_token' => $this->token(), 'name_th' => 'ผิด', 'max_score' => '0'])->status());
        self::assertCount(count($before['audit_logs']) + 1, $this->snapshot()['audit_logs']);
    }

    public function testTeacherEditPreservesCodeOrderAndHistoryLocksMaximum(): void
    {
        $this->login();
        $form = ['_token' => $this->token(), 'name_th' => 'สอบแก้ไข', 'max_score' => '21.50'];
        self::assertSame(302, $this->request('POST', $this->path('update'), $form)->status());
        $row = $this->row('gradebook_components', $this->f['componentA']);
        self::assertSame('EXAM', $row['code']); self::assertSame(10, (int) $row['sort_order']);
        self::assertSame('21.50', $row['max_score']);
        $this->score(null);
        $page = $this->request('GET', $this->path());
        $x = $this->xpath($page->body());
        self::assertSame(1, $x->query('//form[@action="' . $this->path('update') . '"]//input[@name="max_score" and @readonly]')->length);
        $before = $this->snapshot();
        self::assertSame(422, $this->request('POST', $this->path('update'), ['_token' => $this->token(), 'name_th' => 'ชื่อใหม่', 'max_score' => '22.00'])->status());
        self::assertSame($before, $this->snapshot());
        self::assertSame(302, $this->request('POST', $this->path('update'), ['_token' => $this->token(), 'name_th' => 'ชื่อใหม่', 'max_score' => '21.50'])->status());
        self::assertSame('ชื่อใหม่', $this->row('gradebook_components', $this->f['componentA'])['name_th']);
    }

    public function testSetupContextUsesOfferingInsteadOfForgedBrowserLocator(): void
    {
        $this->login();
        $response = $this->request('GET', $this->path(), [], ['workspace_classroom_id' => $this->f['roomB'], 'academic_year_id' => $this->f['yearB']]);
        self::assertSame(200, $response->status());
        self::assertStringContainsString('ปีการศึกษา 2569 · ภาคเรียนที่ 1', $response->body());
        self::assertStringContainsString('/workspaces/classrooms/' . $this->f['roomA'] . '/subjects', $response->body());
        self::assertStringNotContainsString('FOREIGN_SECRET', $response->body());
        self::assertSame(404, $this->request('GET', $this->path('setup', 'B'))->status());
    }

    public function testInactiveItemRemainsHistoricalAndDoesNotInflateActiveMaximum(): void
    {
        $this->login(); $this->score('5.00');
        self::assertSame(302, $this->request('POST', $this->path('status'), ['_token' => $this->token(), 'status' => 'INACTIVE'])->status());
        $page = $this->request('GET', $this->path());
        self::assertStringContainsString('คะแนนเต็มรวมที่ใช้งานอยู่: 0.00 คะแนน', $page->body());
        $x = $this->xpath($page->body());
        self::assertSame(1, $x->query('//section[@aria-label="รายการคะแนนที่ปิดใช้งาน / ประวัติ"]//*[@data-component-id="' . $this->f['componentA'] . '"]')->length);
        self::assertSame('5.00', $this->rows('SELECT score FROM gradebook_scores WHERE component_id=?', [$this->f['componentA']])[0]['score']);
        self::assertSame(302, $this->request('POST', $this->path('status'), ['_token' => $this->token(), 'status' => 'ACTIVE'])->status());
        self::assertStringContainsString('คะแนนเต็มรวมที่ใช้งานอยู่: 20.00 คะแนน', $this->request('GET', $this->path())->body());
    }
}
