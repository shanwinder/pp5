<?php
declare(strict_types=1);

use FastRoute\Dispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookReadFixtures.php';

final class GradebookReadHttpTest extends TestCase
{
    use GradebookReadFixtures;

    public function testRouteUsesSchoolContextWithoutGenericPermissionGate(): void
    {
        $route = FastRoute\simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php')->dispatch('GET',$this->readPath());
        self::assertSame(Dispatcher::FOUND,$route[0]);
        self::assertSame(['action'=>'gradebook.view','protected'=>true,'context'=>'SCHOOL'],$route[1]);
        self::assertSame(302,$this->request('GET',$this->readPath())->status());
        $this->login('SUBJECT_TEACHER'); unset($_SESSION['csrf_token']);
        self::assertSame(200,$this->request('GET',$this->readPath())->status());
        // Generic SCHOOL permission must remain false for the same valid scoped teacher.
        $auth = new App\Services\AuthorizationService(new App\Repositories\AuthorizationRepository($this->pdo));
        self::assertFalse($auth->hasPermission($this->users['SUBJECT_TEACHER']['user'],'SCHOOL',$this->f['schoolA'],'GRADEBOOK_VIEW'));
    }
    public static function readers(): iterable { foreach (['SCHOOL_ADMIN','ACADEMIC_ADMIN','EXECUTIVE','SUBJECT_TEACHER'] as $role) { yield [$role]; } }
    #[DataProvider('readers')]
    public function testPageRendersIdentityRosterCellsAndTotalsWithoutReadSideEffects(string $role): void
    {
        $this->login($role); $before=$this->readSnapshot(); $r=$this->request('GET',$this->readPath());
        self::assertSame(200,$r->status()); $this->assertReadSafe($r->body()); $x=$this->xpath($r->body());
        foreach (['2569','ROOM_A','SCI','วิทยาศาสตร์','DRAFT','SUBJECT_TEACHER','35.50'] as $label) { self::assertStringContainsString($label,$r->body()); }
        self::assertSame(array_map('strval',[$this->f['componentSecond'],$this->f['componentA']]),
            array_map(fn ($n)=>$n->nodeValue,iterator_to_array($x->query('//th[@data-component-id]/@data-component-id'))));
        self::assertSame(array_map(fn ($key)=>(string)$this->f['enrollment_'.$key],['current','zero','null','complete','moved','exited','nullHistory','oldHistory']),
            array_map(fn ($n)=>$n->nodeValue,iterator_to_array($x->query('//tbody/tr[@data-enrollment-id]/@data-enrollment-id'))));
        foreach (['current','null','nullHistory','oldHistory'] as $key) { self::assertSame('',$this->cell($x,$key,$this->f['componentA'])); }
        self::assertSame('0.00',$this->cell($x,'zero',$this->f['componentA']));
        self::assertSame('2.25',$this->cell($x,'moved',$this->f['componentA']));
        self::assertStringContainsString('ประวัติ',$this->rowText($x,'moved')); self::assertStringContainsString('อ่านอย่างเดียว',$this->rowText($x,'moved'));
        self::assertStringContainsString('ครบ',$this->rowText($x,'complete')); self::assertStringContainsString('ยังไม่ครบ',$this->rowText($x,'null'));
        self::assertStringContainsString('2 / 2',$this->rowText($x,'complete'));
        self::assertSame($role === 'EXECUTIVE' ? 0 : 8,$x->query('//input[@hx-post]')->length);
        self::assertSame(0,$x->query('//form|//textarea|//select|//script[not(@src)]|//*[@contenteditable]')->length);
        self::assertSame($before,$this->readSnapshot());
    }
    public static function revocations(): iterable { foreach (['scope','assignment','role','membership','permission','school'] as $kind) { yield [$kind]; } }
    #[DataProvider('revocations')]
    public function testDirectGetDenialAfterRevocationIsSameAsMissing(string $kind): void
    {
        $this->login('SUBJECT_TEACHER'); self::assertSame(200,$this->request('GET',$this->readPath())->status());
        $missing=$this->request('GET','/gradebook/'.PHP_INT_MAX);
        $this->revoke($kind); $r=$this->request('GET',$this->readPath());
        self::assertSame(404,$r->status()); self::assertSame($missing->body(),$r->body()); $this->assertReadSafe($r->body());
        $dashboard=$this->request('GET','/dashboard'); self::assertStringNotContainsString('href="'.$this->readPath().'"',$dashboard->body());
    }
    public function testDashboardLinksFollowScopeAndUnscopedPermissionsWithoutAcademicSetupAccess(): void
    {
        $this->login('SUBJECT_TEACHER'); $before=$this->readSnapshot();
        $r=$this->request('GET','/dashboard'); self::assertSame(200,$r->status());
        self::assertSame([$this->readPath()],$this->gradebookLinks($r->body()));
        self::assertStringNotContainsString('/academic/years',$r->body());
        self::assertSame(404,$this->request('GET',$this->readPath('Other'))->status());
        foreach (['SCHOOL_ADMIN','ACADEMIC_ADMIN','EXECUTIVE'] as $role) {
            $this->login($role); $r=$this->request('GET','/dashboard'); self::assertSame(200,$r->status());
            self::assertCount(5,$this->gradebookLinks($r->body())); self::assertNotContains($this->readPath('B'),$this->gradebookLinks($r->body()));
        }
        $this->login('VIEWER'); self::assertSame([],$this->gradebookLinks($this->request('GET','/dashboard')->body()));
        self::assertSame(404,$this->request('GET',$this->readPath())->status());
        self::assertSame($before,$this->readSnapshot());
    }
    public function testDynamicGrantAndRevokeApplyToDirectRouteAndDashboard(): void
    {
        $this->login('VIEWER'); self::assertSame(404,$this->request('GET',$this->readPath())->status());
        $this->pdo->exec("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code='GRADEBOOK_VIEW'");
        self::assertSame(200,$this->request('GET',$this->readPath())->status()); self::assertCount(5,$this->gradebookLinks($this->request('GET','/dashboard')->body()));
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='SCHOOL_ADMIN' AND p.code='GRADEBOOK_VIEW'");
        $this->login(); self::assertSame(404,$this->request('GET',$this->readPath())->status()); self::assertSame([],$this->gradebookLinks($this->request('GET','/dashboard')->body()));
    }
    public function testHistoricalParentsRemainReadableAndNoActiveComponentsAreNotComplete(): void
    {
        $this->login('SUBJECT_TEACHER');
        $this->pdo->prepare("UPDATE academic_years SET status='CLOSED' WHERE id=?")->execute([$this->f['yearA']]);
        $this->pdo->prepare("UPDATE subject_offerings SET status='INACTIVE' WHERE id=?")->execute([$this->f['offeringA']]);
        $r=$this->request('GET',$this->readPath()); self::assertSame(200,$r->status()); self::assertStringContainsString('CLOSED',$r->body()); self::assertStringContainsString('INACTIVE',$r->body());
        self::assertSame([$this->readPath()],$this->gradebookLinks($this->request('GET','/dashboard')->body()));
        $this->pdo->prepare("UPDATE gradebook_components SET status='INACTIVE' WHERE subject_offering_id=?")->execute([$this->f['offeringA']]);
        $r=$this->request('GET',$this->readPath()); self::assertSame(200,$r->status()); $x=$this->xpath($r->body());
        self::assertSame(0,$x->query('//th[@data-component-id]')->length); self::assertSame(8,$x->query('//tbody/tr[@data-enrollment-id]')->length);
        self::assertStringContainsString('0 / 0',$this->rowText($x,'zero')); self::assertStringContainsString('ยังไม่ครบ',$this->rowText($x,'zero'));
    }
    public function testAllDynamicLabelsAreEscapedIncludingDashboardOfferingLabels(): void
    {
        $this->login('SUBJECT_TEACHER'); $hostile='<script>alert("read-xss")</script>';
        foreach (['subjects'=>'subjectA','classrooms'=>'roomA','gradebook_components'=>'componentA'] as $table=>$key) {
            $this->pdo->prepare("UPDATE {$table} SET code=?,name_th=? WHERE id=?")->execute([$hostile,$hostile,$this->f[$key]]);
        }
        $e=$this->row('student_enrollments',$this->f['enrollment_current']);
        $this->pdo->prepare('UPDATE students SET student_code=?,prefix_th=?,first_name_th=?,last_name_th=? WHERE id=?')->execute([$hostile,$hostile,$hostile,$hostile,$e['student_id']]);
        $this->pdo->prepare('UPDATE users SET display_name=? WHERE id=?')->execute([$hostile,$this->users['SUBJECT_TEACHER']['user']]);
        foreach ([$this->readPath(),'/dashboard'] as $path) {
            $r=$this->request('GET',$path); self::assertSame(200,$r->status());
            self::assertStringContainsString(htmlspecialchars($hostile,ENT_QUOTES,'UTF-8'),$r->body()); self::assertStringNotContainsString($hostile,$r->body());
            self::assertSame(0,$this->xpath($r->body())->query('//script[not(@src)]')->length); $this->assertReadSafe($r->body());
        }
    }
    public function testOnlyDedicatedCsrfProtectedEndpointCanMutateAndReadsHaveNoSideEffects(): void
    {
        $this->login(); $before=$this->readSnapshot();
        foreach ([$this->readPath(),'/dashboard'] as $path) { self::assertSame(200,$this->request('GET',$path)->status()); }
        self::assertSame(405,$this->request('POST',$this->readPath(),['score'=>'1','_token'=>$this->token()])->status());
        self::assertSame(404,$this->request('POST',$this->readPath().'/score',['score'=>'1'])->status());
        self::assertSame(419,$this->request('POST','/hx'.$this->readPath().'/components/'.$this->f['componentA'].'/enrollments/'.$this->f['enrollment_current'].'/score',['score'=>'1'])->status());
        self::assertSame($before,$this->readSnapshot());
    }
    public function testUnexpectedReadFailureIsGenericAndAuthorizationStoreFailureDenies(): void
    {
        $this->login(); $this->pdo->failPrepare='FROM gradebook_scores gs';
        $r=$this->request('GET',$this->readPath()); self::assertSame(500,$r->status()); self::assertSame('Internal Server Error',$r->body()); self::assertTrue($this->pdo->failureTriggered);
        $this->pdo->failureTriggered=false; $this->pdo->failPrepare='FROM user_role_assignments ura';
        $r=$this->request('GET',$this->readPath()); self::assertSame(404,$r->status()); self::assertTrue($this->pdo->failureTriggered); $this->assertReadSafe($r->body());
    }
    private function cell(DOMXPath $x,string $key,int $cid): string
    {
        $path = '//tr[@data-enrollment-id="'.$this->f['enrollment_'.$key].'"]/td[@data-component-id="'.$cid.'"]';
        return $x->query($path.'//input')->length > 0 ? $x->evaluate('string('.$path.'//input/@value)') : trim($x->evaluate('string('.$path.')'));
    }
    private function rowText(DOMXPath $x,string $key): string { return $x->evaluate('string(//tr[@data-enrollment-id="'.$this->f['enrollment_'.$key].'"])'); }
    private function gradebookLinks(string $html): array
    {
        return array_map(fn ($n)=>$n->nodeValue,iterator_to_array($this->xpath($html)->query('//a[starts-with(@href,"/gradebook/")]/@href')));
    }
}
