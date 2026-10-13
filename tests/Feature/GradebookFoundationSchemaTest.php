<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/Support/GradebookFoundationDatabase.php';

final class GradebookFoundationSchemaTest extends TestCase
{
    private PDO $p;
    private array $f;
    protected function setUp(): void
    {
        if (!getenv('PP5_B1_TEST_OWNER')) { $this->markTestSkipped('B1 requires explicit disposable ownership configuration'); }
        $this->p=GradebookFoundationDatabase::connect();$this->p->beginTransaction();
        $this->f=GradebookFoundationDatabase::fixture($this->p);
    }
    protected function tearDown(): void { if(isset($this->p)&&$this->p->inTransaction())$this->p->rollBack(); }
    private function annual(array $changes=[]): int
    {
        return GradebookFoundationDatabase::insert($this->p,'gradebook_annual_courses',array_replace([
            'school_id'=>$this->f['school'],'academic_year_id'=>$this->f['year'],'classroom_id'=>$this->f['room'],
            'course_key'=>GradebookFoundationDatabase::uuid(),'name_th'=>'Annual synthetic','created_by'=>$this->f['actor'],'updated_by'=>$this->f['actor']],$changes));
    }
    private function link(int $annual, array $o, array $changes=[]): int
    {
        return GradebookFoundationDatabase::insert($this->p,'gradebook_annual_course_offerings',array_replace([
            'annual_course_id'=>$annual,'subject_offering_id'=>$o['id'],'school_id'=>$o['school_id'],'academic_year_id'=>$o['academic_year_id'],
            'classroom_id'=>$o['classroom_id'],'subject_id'=>$o['subject_id'],'term_no'=>$o['term_no'],'created_by'=>$this->f['actor'],'updated_by'=>$this->f['actor']],$changes));
    }
    private function rejects(callable $operation,int $code): void
    {
        try {$operation();}catch(PDOException $e){self::assertSame($code,$e->errorInfo[1],$e->getMessage());return;}
        self::fail('Database accepted invalid data');
    }
    public function testEightTablesAndFinalReceiptForeignKeys(): void
    {
        $names=['gradebook_school_cutovers','gradebook_offering_governance','gradebook_annual_courses','gradebook_annual_course_offerings','gradebook_score_sections','gradebook_component_sections','gradebook_structure_drafts','gradebook_structure_publish_receipts'];
        foreach($names as $name){$q=$this->p->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$name]);self::assertSame(['ENGINE'=>'InnoDB','TABLE_COLLATION'=>'utf8mb4_unicode_ci'],$q->fetch());}
        $q=$this->p->query("SELECT TABLE_NAME,CONSTRAINT_NAME,DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME IN ('".implode("','",$names)."')");
        $fks=$q->fetchAll();self::assertCount(32,$fks);
        foreach($fks as $fk){self::assertSame('RESTRICT',$fk['DELETE_RULE']);self::assertSame('RESTRICT',$fk['UPDATE_RULE']);}
        self::assertContains('fk_governance_first_receipt',array_column($fks,'CONSTRAINT_NAME'));
        self::assertContains('fk_governance_latest_receipt',array_column($fks,'CONSTRAINT_NAME'));
        self::assertSame(10,(int)$this->p->query("SELECT COUNT(*) FROM schema_migrations WHERE migration LIKE '20261013_%'")->fetchColumn());
        $columns=$this->p->query('SHOW FULL COLUMNS FROM gradebook_annual_courses')->fetchAll(PDO::FETCH_UNIQUE);
        self::assertSame('ascii_bin',$columns['course_key']['Collation']);self::assertSame('YES',$columns['coursework_percent']['Null']);
        self::assertNull($columns['coursework_percent']['Default']);
    }
    public function testNullableRatioAndExplicitDifferentSubjectLinks(): void
    {
        $a=$this->annual(); foreach($this->f['offerings'] as $o)$this->link($a,$o);
        self::assertNotSame($this->f['offerings'][0]['subject_id'],$this->f['offerings'][1]['subject_id']);
        $this->rejects(fn()=>$this->link($this->annual(),$this->f['offerings'][0]),1062);
        $o=$this->f['offerings'][1];$o['id']=GradebookFoundationDatabase::insert($this->p,'subject_offerings',array_replace(array_diff_key($o,['id'=>1]),['term_no'=>1]));$o['term_no']=1;
        // Unique annual/term rejects before any alternative pairing can be persisted.
        $this->rejects(fn()=>$this->link($a,$o),1062);
    }
    public static function badAnnual(): iterable
    {
        foreach ([['coursework_percent'=>70],['final_percent'=>30],['coursework_percent'=>0,'final_percent'=>100],['coursework_percent'=>70,'final_percent'=>29],['revision'=>0],['status'=>'UNKNOWN'],['ratio_frozen_at'=>'2026-10-13 00:00:00'],['ratio_frozen_by'=>'ACTOR'],['ratio_frozen_at'=>'2026-10-13 00:00:00','ratio_frozen_by'=>'ACTOR']] as $v)yield [$v];
    }
    #[DataProvider('badAnnual')]
    public function testActualCheckEnforcementAndNullBranches(array $v): void
    {
        if(isset($v['ratio_frozen_by']))$v['ratio_frozen_by']=$this->f['actor'];
        $this->rejects(fn()=>$this->annual($v),3819);
    }
    public static function wrongScopes(): iterable {foreach(['school_id','academic_year_id','classroom_id','subject_id','term_no'] as $k)yield [$k];}
    #[DataProvider('wrongScopes')]
    public function testCompositeOfferingIdentityRejectsEachMismatch(string $key): void
    {
        $a=$this->annual();$o=$this->f['offerings'][0];$v=$key==='term_no'?2:99999999;
        $this->rejects(fn()=>$this->link($a,$o,[$key=>$v]),1452);
    }
    public function testCrossScopeWithExistingValidParentsAndActorForeignKey(): void
    {
        $other=GradebookFoundationDatabase::fixture($this->p);
        $this->rejects(fn()=>$this->annual(['classroom_id'=>$other['room']]),1452);
        $this->rejects(fn()=>$this->annual(['created_by'=>999999999]),1452);
        $this->rejects(fn()=>$this->link($this->annual(),$other['offerings'][0]),1452);
        $a=$this->annual(['coursework_percent'=>65,'final_percent'=>35,'ratio_frozen_at'=>'2026-10-13 00:00:00','ratio_frozen_by'=>$this->f['actor']]);
        self::assertGreaterThan(0,$a);
    }
    public function testExistingOtherYearAndClassroomCannotBeLinkedToAnnualScope(): void
    {
        $f=$this->f;$a=$this->annual();$insert=fn($t,$v)=>GradebookFoundationDatabase::insert($this->p,$t,$v);
        $grade=$this->p->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        $next=$insert('academic_years',['school_id'=>$f['school'],'year_be'=>2570,'status'=>'ACTIVE']);
        foreach([$f['year'],$next] as $year){
            $room=$insert('classrooms',['school_id'=>$f['school'],'academic_year_id'=>$year,'grade_level_id'=>$grade,'code'=>'OTHER','name_th'=>'Synthetic other room']);
            $o=['school_id'=>$f['school'],'academic_year_id'=>$year,'classroom_id'=>$room,'subject_id'=>$f['offerings'][0]['subject_id'],'term_no'=>1];
            $o['id']=$insert('subject_offerings',$o);
            $insert('gradebook_offering_governance',['subject_offering_id'=>$o['id'],'school_id'=>$f['school'],'academic_year_id'=>$year,'cutover_epoch_key'=>$f['epoch'],'origin'=>'PRE_ACTIVATION','state'=>'LEGACY','classified_via'=>'INVENTORY','classification_reason'=>'Synthetic scope','classified_by'=>$f['actor'],'classification_hash'=>str_repeat('a',64)]);
            $this->rejects(fn()=>$this->link($a,$o),1452);
        }
    }
    public static function badGovernance(): iterable
    {
        foreach ([['origin'=>'UNKNOWN'],['state'=>'UNKNOWN'],['origin'=>'POST_ACTIVATION'],['state'=>'SETUP_REQUIRED'],['state'=>'PUBLISHED'],['first_publish_receipt_id'=>99999],['adopted_at'=>'2026-10-13 00:00:00'],['revision'=>0],['structure_revision'=>0],['classified_via'=>'UNKNOWN']] as $v)yield [$v];
    }
    #[DataProvider('badGovernance')]
    public function testGovernanceCheckConstraints(array $v): void
    {
        $sql='UPDATE gradebook_offering_governance SET '.implode(',',array_map(fn($k)=>$k.'=?',array_keys($v))).' WHERE subject_offering_id=?';
        $this->rejects(fn()=>$this->p->prepare($sql)->execute([...array_values($v),$this->f['offerings'][0]['id']]),3819);
    }
    public function testNewUnlinkedAndPrivatePreRatioDraftRepresentable(): void
    {
        $o=$this->f['offerings'][0];$this->p->prepare("UPDATE gradebook_offering_governance SET origin='POST_ACTIVATION',state='SETUP_REQUIRED',classified_via='CREATE' WHERE subject_offering_id=?")->execute([$o['id']]);
        // Raw synthetic setup only; application repository deliberately has no origin-changing method.
        $d=['draft_key'=>GradebookFoundationDatabase::uuid(),'school_id'=>$o['school_id'],'academic_year_id'=>$o['academic_year_id'],'subject_offering_id'=>$o['id'],'owner_user_id'=>$this->f['actor'],
            'base_structure_revision'=>1,'base_snapshot'=>'{"annual":null}','base_snapshot_hash'=>str_repeat('b',64),'payload'=>'{"schema_version":1}', 'validation_errors'=>'[]','created_by'=>$this->f['actor'],'updated_by'=>$this->f['actor']];
        self::assertGreaterThan(0,GradebookFoundationDatabase::insert($this->p,'gradebook_structure_drafts',$d));
        $this->rejects(fn()=>GradebookFoundationDatabase::insert($this->p,'gradebook_structure_drafts',array_replace($d,['draft_key'=>GradebookFoundationDatabase::uuid()])),1062);
        $this->p->prepare('DELETE FROM gradebook_structure_drafts WHERE subject_offering_id=?')->execute([$o['id']]);
        $this->rejects(fn()=>GradebookFoundationDatabase::insert($this->p,'gradebook_structure_drafts',array_replace($d,['payload'=>'{broken'])),3140);
        self::assertSame(0,(int)$this->p->query('SELECT COUNT(*) FROM gradebook_annual_courses WHERE school_id='.$this->f['school'])->fetchColumn());
    }
    public function testSectionsMembershipAndReceiptScopeIntegrity(): void
    {
        $a=$this->annual();$f=$this->f;$o=$f['offerings'][0];$other=$f['offerings'][1];
        $this->link($a,$o);$this->link($a,$other);
        $insert=fn($t,$v)=>GradebookFoundationDatabase::insert($this->p,$t,$v);
        $base=['school_id'=>$f['school'],'academic_year_id'=>$f['year'],'subject_offering_id'=>$o['id']];
        $actors=['created_by'=>$f['actor'],'updated_by'=>$f['actor']];
        $section=$insert('gradebook_score_sections',$base+$actors+['section_key'=>GradebookFoundationDatabase::uuid(),'name_th'=>'Synthetic','classification'=>'COURSEWORK','sort_order'=>0]);
        $component=$insert('gradebook_components',$base+['code'=>'B1','name_th'=>'Synthetic','max_score'=>'20.00','sort_order'=>0]);
        $membership=$base+$actors+['component_id'=>$component,'section_id'=>$section];
        $this->rejects(fn()=>$insert('gradebook_component_sections',array_replace($membership,['subject_offering_id'=>$other['id']])),1452);
        $insert('gradebook_component_sections',$membership);
        $this->rejects(fn()=>$this->p->exec('DELETE FROM gradebook_score_sections WHERE id='.$section),1451);
        $this->rejects(fn()=>$this->p->exec('DELETE FROM gradebook_annual_courses WHERE id='.$a),1451);
        $draft=$base+$actors+['draft_key'=>GradebookFoundationDatabase::uuid(),'owner_user_id'=>$f['actor'],'base_structure_revision'=>1,'base_snapshot'=>'{}','base_snapshot_hash'=>str_repeat('a',64),'payload'=>'{}','validation_errors'=>'[]'];
        $draftId=$insert('gradebook_structure_drafts',$draft);
        $receipt=$base+['draft_id'=>$draftId,'actor_user_id'=>$f['actor'],'idempotency_key'=>GradebookFoundationDatabase::uuid(),'request_hash'=>str_repeat('b',64),'payload_hash'=>str_repeat('c',64),'draft_revision'=>1,'annual_revision'=>1,'result_annual_revision'=>2,'published_structure_revision'=>2,'result'=>'{}'];
        $this->rejects(fn()=>$insert('gradebook_structure_publish_receipts',array_replace($receipt,['subject_offering_id'=>$other['id']])),1452);
        $receiptId=$insert('gradebook_structure_publish_receipts',$receipt);
        $this->rejects(fn()=>$insert('gradebook_structure_publish_receipts',array_replace($receipt,['idempotency_key'=>GradebookFoundationDatabase::uuid()])),1062);
        $this->rejects(fn()=>$insert('gradebook_structure_publish_receipts',array_replace($receipt,['draft_revision'=>2])),1062);
        $publish="UPDATE gradebook_offering_governance SET state='PUBLISHED',first_publish_receipt_id=?,latest_publish_receipt_id=?,adopted_at=NOW(),adopted_by=?,adoption_reason='Synthetic evidence',adoption_evidence_hash=? WHERE subject_offering_id=?";
        $this->rejects(fn()=>$this->p->prepare($publish)->execute([$receiptId,$receiptId,$f['actor'],str_repeat('d',64),$other['id']]),1452);
        $this->p->prepare($publish)->execute([$receiptId,$receiptId,$f['actor'],str_repeat('d',64),$o['id']]);
        $this->rejects(fn()=>$this->p->exec('DELETE FROM gradebook_structure_publish_receipts WHERE id='.$receiptId),1451);
        self::assertSame('PUBLISHED',$this->p->query('SELECT state FROM gradebook_offering_governance WHERE subject_offering_id='.$o['id'])->fetchColumn());
        // Relational validity alone is not a publish/readiness proof; these are raw isolated fixtures.
    }
    public static function badCutover(): iterable
    {
        foreach(["phase='UNKNOWN'","revision=0","phase='ARMED'","phase='ACTIVE'","activated_at=NOW()"] as $set)yield [$set];
    }
    #[DataProvider('badCutover')]
    public function testCutoverCannotSkipRequiredEvidence(string $set): void
    {
        $this->rejects(fn()=>$this->p->exec('UPDATE gradebook_school_cutovers SET '.$set.' WHERE school_id='.$this->f['school']),3819);
    }
}
