<?php
declare(strict_types=1);

use App\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookScoreFixtures.php';

final class GradebookBatchScoreHttpTest extends TestCase
{
    use GradebookScoreFixtures;

    private function batchPath(): string { return '/hx/gradebook/'.$this->f['offeringA'].'/scores/batch'; }
    private function matrix(): array
    {
        return ['enrollment_ids'=>[$this->f['enrollment_current'],$this->f['enrollment_zero']],
            'component_ids'=>[$this->f['componentA'],$this->f['componentSecond']], 'values'=>[['','0'],['12.5','15.50']]];
    }
    private function postBatch(?array $matrix = null, array $extra = []): Response
    {
        return $this->request('POST',$this->batchPath(),array_replace(['_token'=>$this->token(),'batch'=>json_encode($matrix ?? $this->matrix(),JSON_THROW_ON_ERROR)],$extra));
    }
    private function headers(Response $r): array { return (new ReflectionProperty($r,'headers'))->getValue($r); }
    private function assertBatchFailed(Response $r,array $before,int $status = 422): array
    {
        self::assertSame($status,$r->status()); self::assertArrayNotHasKey('X-Gradebook-Batch-Saved',$this->headers($r));
        self::assertSame('application/json; charset=UTF-8',$this->headers($r)['Content-Type']); self::assertSame('no-store',$this->headers($r)['Cache-Control']);
        $body=json_decode($r->body(),true,512,JSON_THROW_ON_ERROR); self::assertFalse($body['committed']);
        self::assertArrayNotHasKey('cells',$body); self::assertSame($before,$this->readSnapshot()); $this->assertReadSafe($r->body());
        return $body;
    }
    public function testDedicatedPostRouteAndSuccessWithAuthoritativeAffectedRowsOnly(): void
    {
        $route=FastRoute\simpleDispatcher(require dirname(__DIR__,2).'/htdocs/routes/web.php')->dispatch('POST',$this->batchPath());
        self::assertSame(['action'=>'gradebook.scores.batch','protected'=>true,'context'=>'SCHOOL'],$route[1]);
        self::assertSame(302,$this->postBatch()->status()); self::assertSame(405,$this->request('GET',$this->batchPath())->status());
        $this->login('SUBJECT_TEACHER'); $r=$this->postBatch(); self::assertSame(200,$r->status());
        self::assertSame(['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store','X-Gradebook-Batch-Saved'=>'1'],$this->headers($r));
        $body=json_decode($r->body(),true,512,JSON_THROW_ON_ERROR); self::assertTrue($body['committed']);
        self::assertSame(4,$body['targeted_count']); self::assertSame(3,$body['changed_count']);
        self::assertSame([null,'0.00','12.50','15.50'],array_column($body['cells'],'score'));
        self::assertCount(2,$body['rows']); self::assertSame(['0.00','28.00'],array_column($body['rows'],'entered_score_total'));
        self::assertSame(['35.50','35.50'],array_column($body['rows'],'configured_max_total'));
        self::assertSame([1,2],array_column($body['rows'],'entered_component_count'));
        self::assertSame([2,2],array_column($body['rows'],'active_component_count')); self::assertSame([false,true],array_column($body['rows'],'complete'));
        self::assertCount(3,$this->scoreAudits()); $this->assertReadSafe($r->body());
        self::assertStringNotContainsString('display_name',$r->body()); self::assertStringNotContainsString('teachers',$r->body());
        $before=$this->readSnapshot(); self::assertSame(0,json_decode($this->postBatch()->body(),true)['changed_count']); self::assertSame($before,$this->readSnapshot());
    }
    public static function csrf(): array { return [[null],['bad'],[['bad']],['expired']]; }
    #[DataProvider('csrf')]
    public function testCsrfFailureBeforeLocks(mixed $token): void
    {
        $this->login(); if ($token==='expired') { $token=$this->token(); unset($_SESSION['csrf_token']); $this->token(); }
        $before=$this->readSnapshot(); $this->pdo->queries=[]; $this->assertBatchFailed($this->postBatch(extra:['_token'=>$token]),$before,419);
        self::assertSame([],array_values(array_filter($this->pdo->queries,fn ($q)=>str_contains($q,'FOR UPDATE'))));
    }
    public static function malformed(): array { return [[null],[[]],['{'],['null'],['false'],['{}'],['[]'],['{"enrollment_ids":{"0":1},"component_ids":[1],"values":[["1"]]}'],['{"enrollment_ids":[]}'],[str_repeat('x',262145)]]; }
    #[DataProvider('malformed')]
    public function testMalformedBodyIsSafe(mixed $body): void
    {
        $this->login(); $before=$this->readSnapshot(); $this->assertBatchFailed($this->postBatch(extra:['batch'=>$body]),$before);
    }
    public static function targetCases(): array { return [['foreign','componentA'],['current','componentB'],['current','componentOther'],['current','inactiveComponent'],['wrongRoom','componentA'],['wrongYear','componentA'],['moved','componentA']]; }
    #[DataProvider('targetCases')]
    public function testTargetForgeryFailsWithoutNames(string $student,string $component): void
    {
        $this->login(); $m=['enrollment_ids'=>[$this->f['enrollment_'.$student]], 'component_ids'=>[$this->f[$component]], 'values'=>[['5']]];
        $before=$this->readSnapshot(); $body=$this->assertBatchFailed($this->postBatch($m),$before);
        self::assertArrayNotHasKey('location',$body);
    }
    public function testDuplicateAndMissingTargetFieldsAreNotClear(): void
    {
        $this->login(); $before=$this->readSnapshot(); $m=$this->matrix();
        $m['enrollment_ids'][1]=$m['enrollment_ids'][0]; $this->assertBatchFailed($this->postBatch($m),$before);
        unset($m['component_ids']); $this->assertBatchFailed($this->postBatch($m),$before);
    }
    public static function stale(): array { return array_map(fn ($x)=>[$x],['scope','permission','assignment','role','component','year','offering','placement','enrollment']); }
    #[DataProvider('stale')]
    public function testStalePageDoesNotGrantWrite(string $state): void
    {
        $this->login('SUBJECT_TEACHER'); self::assertSame(200,$this->request('GET',$this->readPath())->status());
        $this->changePrerequisite($state); $before=$this->readSnapshot(); $this->assertBatchFailed($this->postBatch(),$before);
    }
    public function testSessionLossAndReadOnlyUsersCannotWrite(): void
    {
        $this->login(); $before=$this->readSnapshot(); $_SESSION=[];
        self::assertSame(302,$this->postBatch()->status()); self::assertSame($before,$this->readSnapshot());
        $this->login('EXECUTIVE'); $this->assertBatchFailed($this->postBatch(),$before);
        foreach (['SYSTEM_ADMIN','FOREIGN'] as $role) { $this->login($role); self::assertSame(403,$this->postBatch()->status()); self::assertSame($before,$this->readSnapshot()); }
    }
    public function testInvalidMiddleCellAndHostileValueHaveNoPartialSuccess(): void
    {
        $this->login(); $before=$this->readSnapshot();
        foreach (['20.01','<img src=x onerror=alert(1)>'] as $invalid) {
            $m=$this->matrix(); $m['values'][1][0]=$invalid; $body=$this->assertBatchFailed($this->postBatch($m),$before);
            self::assertSame(2,$body['location']['row']); self::assertSame(1,$body['location']['column']);
            self::assertStringContainsString('02-ZERO',$body['message']); self::assertStringNotContainsString('<img',$body['message']);
        }
    }
    public static function failures(): array { return [['INSERT INTO gradebook_scores',2],['INSERT INTO audit_logs',3]]; }
    #[DataProvider('failures')]
    public function testWriteAndAuditFailureRollbackWholeHttpCommand(string $query,int $nth): void
    {
        $this->login(); $before=$this->readSnapshot(); $this->pdo->failPrepare=$query; $this->pdo->failPrepareOccurrence=$nth;
        $this->assertBatchFailed($this->postBatch(),$before); self::assertTrue($this->pdo->failureTriggered);
    }
    public function testPostCommitRefreshFailureHonestlyReportsCommitted(): void
    {
        $this->login(); $this->pdo->failPrepare='FROM gradebook_scores gs'; $r=$this->postBatch();
        self::assertSame(409,$r->status()); self::assertTrue(json_decode($r->body(),true)['committed']);
        self::assertArrayNotHasKey('X-Gradebook-Batch-Saved',$this->headers($r)); self::assertCount(3,$this->scoreAudits());
        self::assertSame('12.50',$this->cellRows('zero')[0]['score']); $this->assertReadSafe($r->body());
        self::assertSame(200,$this->postBatch()->status()); self::assertCount(3,$this->scoreAudits());
    }
    public function testTargetedReadReusesTotalsAndDoesNotFetchUnrelatedRowsOrTeachers(): void
    {
        $this->login(); $all=$this->gradebook(); $this->pdo->queries=[];
        $rows=$this->readService()->getAffectedRows($this->users['SCHOOL_ADMIN']['user'],'SCHOOL',$this->f['schoolA'],$this->f['offeringA'],[$this->f['enrollment_complete']]);
        self::assertSame([$this->modelRow($all,'complete')],$rows);
        $queries=$this->pdo->queries;
        self::assertCount(1,array_filter($queries,fn ($q)=>str_contains($q,'AND e.id IN (?)')));
        self::assertCount(1,array_filter($queries,fn ($q)=>str_contains($q,'AND gs.enrollment_id IN (?)')));
        self::assertCount(0,array_filter($queries,fn ($q)=>str_contains($q,'AS teaching_assignment_id')));
        $this->revoke('scope'); self::assertNull($this->readService()->getAffectedRows($this->users['SUBJECT_TEACHER']['user'],'SCHOOL',$this->f['schoolA'],$this->f['offeringA'],[$this->f['enrollment_complete']]));
    }
    public function testForeignOfferingAndForgedAuthorityCannotRedirectMutation(): void
    {
        $this->login('SUBJECT_TEACHER'); $before=$this->readSnapshot();
        $r=$this->request('POST','/hx/gradebook/'.$this->f['offeringB'].'/scores/batch',
            ['_token'=>$this->token(),'batch'=>json_encode($this->matrix())]);
        $this->assertBatchFailed($r,$before);
        $r=$this->postBatch(extra:['school_id'=>$this->f['schoolB'],'academic_year_id'=>$this->f['yearB'],
            'user_id'=>$this->users['FOREIGN']['user'],'offering_id'=>$this->f['offeringB']]);
        self::assertSame(200,$r->status());
        foreach ($this->scoreAudits() as $audit) {
            self::assertSame($this->f['schoolA'],$audit['school_id']); self::assertSame($this->users['SUBJECT_TEACHER']['user'],$audit['user_id']);
        }
    }

    public function testBatchIpUsesValidatedRemoteAddressAndIgnoresForwardedHeaders(): void
    {
        $this->login();
        $r=(new App\Application($this->pdo))->handle(new App\Http\Request('POST',$this->batchPath(),[],
            ['_token'=>$this->token(),'batch'=>json_encode($this->matrix())],['REMOTE_ADDR'=>'bad-ip','HTTP_X_FORWARDED_FOR'=>'8.8.8.8']));
        self::assertSame(200,$r->status()); foreach ($this->scoreAudits() as $audit) { self::assertNull($audit['ip_address']); }
    }

    public function testBatchControlsOnlyRenderedForWritableGradebook(): void
    {
        foreach (['SUBJECT_TEACHER'=>true,'EXECUTIVE'=>false] as $role=>$writable) {
            $this->login($role); $page=$this->request('GET',$this->readPath()); $x=$this->xpath($page->body());
            self::assertSame($writable ? 1 : 0,$x->query('//*[@data-batch-url]')->length);
            self::assertSame($writable ? 1 : 0,$x->query('//*[@id="gradebook-batch-status" and @role="status" and @aria-live="polite"]')->length);
            if ($writable) {
                self::assertSame($this->batchPath(),$x->evaluate('string(//*[@data-batch-url]/@data-batch-url)'));
                self::assertSame('2000',$x->evaluate('string(//*[@data-batch-limit]/@data-batch-limit)'));
            }
        }
    }
}
