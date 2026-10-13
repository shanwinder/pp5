<?php
declare(strict_types=1);

use App\Services\GradebookAnnualCourseFoundationService;
use App\Repositories\GradebookAnnualCourseRepository;
use App\Repositories\GradebookGovernanceRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/Support/GradebookFoundationDatabase.php';

final class GradebookAnnualCourseFoundationTest extends TestCase
{
    private PDO $p; private array $f; private array $args;
    protected function setUp(): void
    {
        if(!getenv('PP5_B1_TEST_OWNER'))$this->markTestSkipped('B1 requires explicit disposable ownership configuration');
        $this->p=GradebookFoundationDatabase::connect(); $this->f=GradebookFoundationDatabase::fixture($this->p);
        $this->args=[$this->f['school'],$this->f['actor'],$this->f['year'],$this->f['room'],GradebookFoundationDatabase::uuid(),'Annual synthetic',array_column($this->f['offerings'],'id'),'Explicit synthetic academic confirmation'];
    }
    private function create(?array $args=null): int { return (new GradebookAnnualCourseFoundationService($this->p))->create(...($args??$this->args)); }
    private function snapshot(): array
    {
        $s=[];foreach(['gradebook_annual_courses','gradebook_annual_course_offerings','gradebook_offering_governance','gradebook_scores','gradebook_components','audit_logs'] as $t){$q=$this->p->prepare('SELECT * FROM '.$t.' WHERE school_id=?');$q->execute([$this->f['school']]);$s[$t]=$q->fetchAll();}return $s;
    }
    private function rejects(callable $f): void
    {
        $before=$this->snapshot();try{$f();}catch(DomainException $e){self::assertSame($before,$this->snapshot());return;}self::fail('Unexpected successful mutation');
    }
    public function testAtomicCreationAndLostResponseRetry(): void
    {
        $before=$this->snapshot();$id=$this->create();$after=$this->snapshot();
        self::assertSame($id,$this->create());self::assertSame($after,$this->snapshot());
        self::assertCount(1,$after['gradebook_annual_courses']);self::assertCount(2,$after['gradebook_annual_course_offerings']);self::assertCount(1,$after['audit_logs']);
        self::assertNull($after['gradebook_annual_courses'][0]['coursework_percent']);
        self::assertSame($before['gradebook_scores'],$after['gradebook_scores']);self::assertSame($before['gradebook_components'],$after['gradebook_components']);self::assertSame($before['gradebook_offering_governance'],$after['gradebook_offering_governance']);
        $foreign=new GradebookAnnualCourseRepository($this->p);self::assertNull($foreign->find($this->f['school']+999999,$this->f['year'],$this->f['room'],$this->args[4]));
    }
    public function testSingleExplicitLinkDoesNotAutopairOtherTerm(): void
    {
        $this->args[6]=[$this->args[6][0]];$this->create();self::assertCount(1,$this->snapshot()['gradebook_annual_course_offerings']);
    }
    public function testMissingConfirmationAndDifferentRequestKeysDoNotCreateOrphans(): void
    {
        $a=$this->args;$a[6]=[];$this->rejects(fn()=>$this->create($a));
        $this->create();$a=$this->args;$a[4]=GradebookFoundationDatabase::uuid();$this->rejects(fn()=>$this->create($a));
        $a=$this->args;$a[5]='Changed request';$this->rejects(fn()=>$this->create($a));
        $a=$this->args;$a[7]='Changed confirmation';$this->rejects(fn()=>$this->create($a));
    }
    public static function denied(): iterable
    {
        foreach(['CLOSED','school','offering','subject','room','actor','membership','role','permission','scoped_only','missing_governance','missing_cutover','ARMED','wrong_year','wrong_room','foreign_actor','foreign_offering','duplicate_term'] as $v)yield [$v];
    }
    #[DataProvider('denied')]
    public function testLifecycleScopeAndLivePermissionDenyWithoutMutation(string $v): void
    {
        $f=$this->f;$o=$f['offerings'][0];
        switch($v){
            case 'CLOSED':$this->p->exec("UPDATE academic_years SET status='CLOSED' WHERE id=".$f['year']);break;
            case 'school':$this->p->exec("UPDATE schools SET status='SUSPENDED' WHERE id=".$f['school']);break;
            case 'offering':$this->p->exec("UPDATE subject_offerings SET status='INACTIVE' WHERE id=".$o['id']);break;
            case 'subject':$this->p->exec("UPDATE subjects SET status='INACTIVE' WHERE id=".$o['subject_id']);break;
            case 'room':$this->p->exec("UPDATE classrooms SET status='INACTIVE' WHERE id=".$f['room']);break;
            case 'actor':$this->p->exec("UPDATE users SET status='INACTIVE' WHERE id=".$f['actor']);break;
            case 'membership':$this->p->exec("UPDATE school_memberships SET status='INACTIVE' WHERE school_id=".$f['school']);break;
            case 'role':$this->p->exec("UPDATE user_role_assignments SET status='INACTIVE' WHERE user_id=".$f['actor']);break;
            case 'permission':case 'scoped_only':
                $role=GradebookFoundationDatabase::insert($this->p,'roles',['code'=>'B1-'.bin2hex(random_bytes(8)),'name_th'=>'Synthetic']);
                $this->p->exec('UPDATE user_role_assignments SET role_id='.$role.' WHERE user_id='.$f['actor']);
                if($v==='scoped_only'){$permission=$this->p->query("SELECT id FROM permissions WHERE code='GRADEBOOK_COMPONENT_MANAGE'")->fetchColumn();GradebookFoundationDatabase::insert($this->p,'role_permissions',['role_id'=>$role,'permission_id'=>$permission,'resource_scope_type'=>'SUBJECT_OFFERING']);}
                break;
            case 'missing_governance':$this->p->exec('DELETE FROM gradebook_offering_governance WHERE subject_offering_id='.$o['id']);break;
            case 'missing_cutover':$this->p->exec('DELETE FROM gradebook_offering_governance WHERE school_id='.$f['school']);$this->p->exec('DELETE FROM gradebook_school_cutovers WHERE school_id='.$f['school']);break;
            case 'ARMED':$this->p->exec("UPDATE gradebook_school_cutovers SET phase='ARMED',inventory_hash='".str_repeat('c',64)."',inventory_count=2,verified_release='synthetic' WHERE school_id=".$f['school']);break;
            case 'wrong_year':$this->args[2]=999999999;break;
            case 'wrong_room':$this->args[3]=999999999;break;
            case 'foreign_actor':$this->args[1]=GradebookFoundationDatabase::fixture($this->p)['actor'];break;
            case 'foreign_offering':$this->args[6]=array_column(GradebookFoundationDatabase::fixture($this->p)['offerings'],'id');break;
            case 'duplicate_term':$this->p->exec('UPDATE subject_offerings SET term_no=1 WHERE id='.$f['offerings'][1]['id']);break;
        }
        $this->rejects(fn()=>$this->create());
    }
    public function testClosedYearRejectsEvenIdenticalRetry(): void
    {
        $this->create();$this->p->exec("UPDATE academic_years SET status='CLOSED' WHERE id=".$this->f['year']);$this->rejects(fn()=>$this->create());
    }
    public function testAuditFailureRollsBackIdentityAndAllLinks(): void
    {
        $before=$this->snapshot();
        $fault=GradebookFoundationDatabase::connect(B1AuditFailurePDO::class);
        try{(new GradebookAnnualCourseFoundationService($fault))->create(...$this->args);self::fail('Expected audit failure');}
        catch(RuntimeException $e){self::assertSame('B1 audit fault',$e->getMessage());}
        self::assertSame($before,$this->snapshot());
    }
    public function testGovernanceReadsPreserveOriginAndFailClosedForMissingScopeAndEpoch(): void
    {
        $repo=new GradebookGovernanceRepository($this->p);$before=$this->snapshot();
        $this->p->beginTransaction();
        try{
            foreach([[999999999,$this->f['school'],$this->f['epoch']],[$this->f['offerings'][0]['id'],999999999,$this->f['epoch']],[$this->f['offerings'][0]['id'],$this->f['school'],GradebookFoundationDatabase::uuid()]] as [$offering,$school,$epoch]){
                try{$repo->lockOffering($school,$this->f['year'],$offering,$epoch);self::fail('Missing scope or epoch accepted');}
                catch(DomainException){self::assertTrue(true);}
            }
            $row=$repo->lockOffering($this->f['school'],$this->f['year'],$this->f['offerings'][0]['id'],$this->f['epoch']);
            self::assertSame('PRE_ACTIVATION',$row['origin']);
        }finally{$this->p->rollBack();}
        self::assertSame($before,$this->snapshot());
    }
    public function testConcurrentTabsSerializeAndReturnOneIdentityAndAudit(): void
    {
        $lock=GradebookFoundationDatabase::connect();$lock->beginTransaction();$lock->query('SELECT id FROM schools WHERE id='.$this->f['school'].' FOR UPDATE')->fetch();
        $script='require $argv[1]; require $argv[2]; $p=GradebookFoundationDatabase::connect(); echo (new App\\Services\\GradebookAnnualCourseFoundationService($p))->create(...json_decode($argv[3],true,512,JSON_THROW_ON_ERROR));';
        $children=[];
        try{
            for($i=0;$i<2;$i++){
                $pipes=[];$proc=proc_open([PHP_BINARY,'-r',$script,dirname(__DIR__,2).'/htdocs/vendor/autoload.php',dirname(__DIR__).'/Support/GradebookFoundationDatabase.php',json_encode($this->args,JSON_THROW_ON_ERROR)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                self::assertIsResource($proc);fclose($pipes[0]);$children[]=[$proc,$pipes];
            }
            // Both connections must actually reach a blocked lock request before release.
            $deadline=microtime(true)+5;$waiting=0;
            do{
                $waiting=(int)$lock->query("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='pp5_b1' AND INFO LIKE 'SELECT id, status FROM schools%' AND COMMAND IN ('Query','Execute')")->fetchColumn();
                if($waiting>=2)break;usleep(10000);
            }while(microtime(true)<$deadline);
        }finally{$lock->rollBack();}
        $ids=[];
        foreach($children as [$proc,$pipes]){$ids[]=trim(stream_get_contents($pipes[1]));$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($proc),$err);}
        self::assertSame(2,$waiting,'Two real concurrent service connections waiting on the school lock');
        self::assertSame($ids[0],$ids[1]);self::assertGreaterThan(0,(int)$ids[0]);$s=$this->snapshot();self::assertCount(1,$s['gradebook_annual_courses']);self::assertCount(1,$s['audit_logs']);self::assertCount(2,$s['gradebook_annual_course_offerings']);
    }
}

final class B1AuditFailurePDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_starts_with($query, 'INSERT INTO audit_logs')) { throw new RuntimeException('B1 audit fault'); }
        return parent::prepare($query, $options);
    }
}
