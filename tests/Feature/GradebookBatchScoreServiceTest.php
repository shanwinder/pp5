<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookScoreFixtures.php';

final class GradebookBatchScoreServiceTest extends TestCase
{
    use GradebookScoreFixtures;

    private function matrix(array $values = [['1','2'],['3','4']], array $students = ['current','zero'], array $components = ['componentA','componentSecond']): array
    {
        return ['enrollment_ids'=>array_map(fn ($key) => $this->f['enrollment_'.$key], $students),
            'component_ids'=>array_map(fn ($key) => $this->f[$key], $components), 'values'=>$values];
    }
    private function batch(array $matrix, string $role = 'SUBJECT_TEACHER', string $offering = 'A'): array
    {
        return $this->scoreService()->setScoresBatch($this->f['schoolA'], $this->users[$role]['user'], $this->f['offering'.$offering], $matrix, '127.0.0.1');
    }

    public function testMatrixCommitsEveryCellAndAuditInDisplayOrder(): void
    {
        $m = $this->matrix(); $r = $this->batch($m);
        self::assertSame(4, $r['targeted_count']); self::assertSame(4, $r['changed_count']);
        self::assertSame(2, $r['row_count']); self::assertSame(2, $r['column_count']);
        self::assertSame(['1.00','2.00','3.00','4.00'], array_column($r['cells'], 'score'));
        self::assertSame([$m['enrollment_ids'][0],$m['enrollment_ids'][0],$m['enrollment_ids'][1],$m['enrollment_ids'][1]], array_column($r['cells'],'enrollment_id'));
        self::assertCount(4,$this->scoreAudits()); self::assertSame(1,$this->pdo->depth);
        foreach ($r['cells'] as $cell) {
            $stored = $this->rows('SELECT * FROM gradebook_scores WHERE school_id=? AND subject_offering_id=? AND enrollment_id=? AND component_id=?',
                [$this->f['schoolA'],$this->f['offeringA'],$cell['enrollment_id'],$cell['component_id']]);
            self::assertSame($cell['score'],$stored[0]['score']);
        }
        foreach ($this->scoreAudits() as $audit) {
            self::assertSame($this->users['SUBJECT_TEACHER']['user'],$audit['user_id']);
            self::assertSame($this->f['schoolA'],$audit['school_id']); self::assertSame('127.0.0.1',$audit['ip_address']);
            self::assertSame('gradebook_scores',$audit['entity_type']); self::assertNotEmpty($audit['created_at']);
            self::assertSame($this->f['offeringA'],json_decode($audit['new_value'],true)['subject_offering_id']);
        }
    }

    public static function scalarValues(): array
    {
        return [['5','5.00'],['0','0.00'],['0.0','0.00'],['0.00','0.00'],['01.50','1.50'],[" \t12.5\r",'12.50'],['20','20.00'],['',null]];
    }
    #[DataProvider('scalarValues')]
    public function testOneByOneSharesSingleCellNormalization(string $input, ?string $expected): void
    {
        $r = $this->batch($this->matrix([[$input]],['current'],['componentA']));
        self::assertSame($expected,$r['cells'][0]['score']); self::assertSame($expected !== null,$r['cells'][0]['changed']);
    }
    public function testVerticalBlankZeroClearAndNoopAuditCounts(): void
    {
        $id = $this->cellRows('complete')[0]['id'];
        $r = $this->batch($this->matrix([[''],['0'],['05.00'],['']],['current','zero','complete','null'],['componentA']));
        self::assertSame(0,$r['changed_count']); self::assertSame([], $this->scoreAudits()); self::assertSame([],$this->cellRows());
        $r = $this->batch($this->matrix([[''],['0']],['complete','current'],['componentA']));
        self::assertSame(2,$r['changed_count']); self::assertSame($id,$this->cellRows('complete')[0]['id']);
        self::assertNull($this->cellRows('complete')[0]['score']); self::assertSame('0.00',$this->cellRows()[0]['score']);
        self::assertCount(2,$this->scoreAudits());
        $r = $this->batch($this->matrix([['1','2'],['3','4'],['','0']],['zero','null','complete']));
        self::assertSame(6,$r['targeted_count']); self::assertSame(4,$r['changed_count']); self::assertCount(6,$this->scoreAudits());
    }
    public static function invalidValues(): array
    {
        return array_map(fn ($v)=>[$v], ['20.01','-1','+1','1e2','1,5','1,000','1.234','NaN','Infinity',' ','=SUM(A1:B1)','๑','50%','$5','<img src=x onerror=alert(1)>']);
    }
    #[DataProvider('invalidValues')]
    public function testInvalidMiddleCellRejectsWholeMatrixWithSafeHumanLocation(string $invalid): void
    {
        $m = $this->matrix([['1','2'],[$invalid,'4']]); $before = $this->readSnapshot();
        try { $this->batch($m); self::fail('Expected rejection'); }
        catch (App\Services\GradebookBatchException $e) {
            self::assertSame(['row'=>2,'column'=>1,'enrollment_id'=>$m['enrollment_ids'][1],'component_id'=>$m['component_ids'][0]],$e->location);
            self::assertStringContainsString('02-ZERO',$e->getMessage()); self::assertStringContainsString('สอบ',$e->getMessage());
            $this->assertReadSafe($e->getMessage()); if (str_contains($invalid, '<')) { self::assertStringNotContainsString($invalid,$e->getMessage()); }
        }
        self::assertSame($before,$this->readSnapshot()); $this->assertNoScoreWrites();
    }
    public static function malformed(): array { return array_map(fn ($v)=>[$v],['empty','ragged','missing','duplicate-row','duplicate-column','string-id','float-id','zero-id','boolean-value','null-value','object-values','too-many','extra']); }
    #[DataProvider('malformed')]
    public function testMalformedMatrixRejectedBeforeTransaction(string $kind): void
    {
        $m=$this->matrix();
        switch ($kind) {
            case 'empty': $m['values']=[]; break;
            case 'ragged': $m['values'][1]=['3']; break;
            case 'missing': unset($m['component_ids']); break;
            case 'duplicate-row': $m['enrollment_ids'][1]=$m['enrollment_ids'][0]; break;
            case 'duplicate-column': $m['component_ids'][1]=$m['component_ids'][0]; break;
            case 'string-id': $m['component_ids'][0]=(string)$m['component_ids'][0]; break;
            case 'float-id': $m['component_ids'][0]=1.2; break;
            case 'zero-id': $m['enrollment_ids'][0]=0; break;
            case 'boolean-value': $m['values'][0][0]=false; break;
            case 'null-value': $m['values'][0][0]=null; break;
            case 'object-values': $m['values']=['x'=>['1','2'],'y'=>['3','4']]; break;
            case 'too-many': $m=['enrollment_ids'=>range(1,1001),'component_ids'=>[1,2],'values'=>array_fill(0,1001,['1','2'])]; break;
            case 'extra': $m['school_id']=$this->f['schoolB']; break;
        }
        $this->assertRejected(fn ()=>$this->batch($m));
        self::assertSame([],array_values(array_filter($this->pdo->queries,fn ($q)=>str_contains($q,'FOR UPDATE'))));
    }
    public static function targets(): array
    {
        return [['moved','componentA'],['exited','componentA'],['nullHistory','componentA'],['wrongRoom','componentA'],['wrongYear','componentA'],
            ['foreign','componentA'],['current','componentB'],['current','componentOther'],['current','componentNext'],['current','inactiveComponent']];
    }
    #[DataProvider('targets')]
    public function testInvalidRelationshipDoesNotDiscloseNamesOrWrite(string $student,string $component): void
    {
        $this->assertRejected(fn ()=>$this->batch($this->matrix([['2']],[$student],[$component])));
        $this->assertNoScoreWrites();
    }
    public static function stale(): array { return array_map(fn ($s)=>[$s],['scope','assignment','membership','school','role','permission','year','offering','component','enrollment','placement']); }
    #[DataProvider('stale')]
    public function testLiveRevocationAndLifecycle(string $state): void
    {
        self::assertNotNull($this->gradebook('SUBJECT_TEACHER')); $this->changePrerequisite($state);
        $this->assertRejected(fn ()=>$this->batch($this->matrix())); $this->assertNoScoreWrites();
    }
    public static function failures(): array { return [['INSERT INTO gradebook_scores',1],['INSERT INTO gradebook_scores',2],['INSERT INTO gradebook_scores',3],['INSERT INTO audit_logs',1],['INSERT INTO audit_logs',2],['INSERT INTO audit_logs',4]]; }
    #[DataProvider('failures')]
    public function testInjectedFailureAfterRealMutationsRollsBackScoresAndAudits(string $query,int $occurrence): void
    {
        $this->pdo->failPrepare=$query; $this->pdo->failPrepareOccurrence=$occurrence;
        $this->assertRejected(fn ()=>$this->batch($this->matrix()));
        self::assertTrue($this->pdo->failureTriggered); self::assertSame(1,$this->pdo->depth);
        self::assertCount($occurrence,array_filter($this->pdo->queries,fn ($q)=>str_contains($q,$query)));
    }
    public function testLocksAreSortedAndCommonResourcesAreResolvedOnce(): void
    {
        require_once dirname(__DIR__).'/Support/GradebookBatchStatement.php';
        $executions=[];
        $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS,[GradebookBatchStatement::class,[function ($q,$p) use (&$executions) { $executions[]=[$q,$p]; }]]);
        $m=$this->matrix([['1','2'],['3','4']],['zero','current'],['componentSecond','componentA']);
        $r=$this->batch($m);
        $locks=array_values(array_filter($executions,fn ($e)=>str_contains($e[0],'FOR UPDATE')));
        $components=array_values(array_filter($locks,fn ($e)=>str_contains($e[0],'FROM gradebook_components')));
        self::assertSame([$this->f['componentA'],$this->f['componentSecond']],array_map(fn ($e)=>$e[1][2],$components));
        $enrollments=array_values(array_filter($locks,fn ($e)=>str_contains($e[0],'FROM student_enrollments')));
        $sorted=$m['enrollment_ids']; sort($sorted,SORT_NUMERIC);
        self::assertSame($sorted,array_map(fn ($e)=>$e[1]['enrollment_id'],$enrollments));
        $cells=array_values(array_filter($locks,fn ($e)=>str_contains($e[0],'FROM gradebook_scores')));
        $expected=[]; foreach ($sorted as $e) { foreach ([$this->f['componentA'],$this->f['componentSecond']] as $c) { $expected[]=[$e,$c]; } }
        self::assertSame($expected,array_map(fn ($e)=>array_slice($e[1],3),$cells));
        foreach (['FROM schools','FROM academic_years','FROM subject_offerings','FROM user_role_assignments'] as $table) {
            self::assertCount(1,array_filter($locks,fn ($e)=>str_contains($e[0],$table)));
        }
        self::assertSame([$m['enrollment_ids'][0],$m['enrollment_ids'][0],$m['enrollment_ids'][1],$m['enrollment_ids'][1]],array_column($r['cells'],'enrollment_id'));
        self::assertSame(['1.00','2.00','3.00','4.00'],array_column($r['cells'],'score'));
        $queries=implode("\n",$this->pdo->queries);
        self::assertSame(1,count(array_filter($this->pdo->queries,fn ($q)=>$q==='BEGIN')));
        self::assertSame(1,count(array_filter($this->pdo->queries,fn ($q)=>$q==='COMMIT')));
    }

    public static function afterMutationFailures(): array { return [['gradebook_scores',1],['gradebook_scores',3],['audit_logs',1],['audit_logs',3]]; }
    #[DataProvider('afterMutationFailures')]
    public function testFailureAfterExecutedMutationObservesPendingChangesThenRollsBack(string $table,int $nth): void
    {
        require_once dirname(__DIR__).'/Support/GradebookBatchStatement.php';
        $before=$this->readSnapshot(); $observed=null; $seen=0;
        $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS,[GradebookBatchStatement::class,[function ($q) use ($table,$nth,&$seen,&$observed): void {
            if (preg_match('/\A(?:INSERT INTO|UPDATE) '.$table.'\b/',$q) && ++$seen===$nth) {
                $observed=$this->readSnapshot();
                throw new PDOException('private-db-details after actual mutation');
            }
        }]]);
        $this->assertRejected(fn ()=>$this->batch($this->matrix()));
        self::assertNotNull($observed); self::assertNotSame($before['gradebook_scores'],$observed['gradebook_scores']);
        if ($table==='audit_logs' || $nth>1) { self::assertNotSame($before['audit_logs'],$observed['audit_logs']); }
        self::assertSame($before,$this->readSnapshot());
    }

    public function testMaximumRealClassroomMatrixIsAcceptedAndValidatedPerUniqueRowAndColumn(): void
    {
        $students=[]; for ($i=0;$i<50;$i++) { $students[]=$this->student('BATCH-LIMIT-'.$i); }
        $components=[];
        for ($i=0;$i<40;$i++) {
            $components[]=$this->insert('gradebook_components',['school_id'=>$this->f['schoolA'],'academic_year_id'=>$this->f['yearA'],
                'subject_offering_id'=>$this->f['offeringA'],'code'=>'BATCH-'.$i,'name_th'=>'หัวข้อ '.$i,'max_score'=>'10.00','sort_order'=>$i]);
        }
        $this->pdo->queries=[];
        $r=$this->batch(['enrollment_ids'=>$students,'component_ids'=>$components,'values'=>array_fill(0,50,array_fill(0,40,''))]);
        self::assertSame(2000,$r['targeted_count']); self::assertSame(0,$r['changed_count']);
        self::assertSame([],$this->scoreAudits()); $this->assertNoScoreWrites();
        foreach (['FROM gradebook_components'=>40,'FROM student_enrollments'=>50,'FROM student_classroom_placements'=>50,'FROM user_role_assignments'=>1] as $table=>$count) {
            self::assertCount($count,array_filter($this->pdo->queries,fn ($q)=>str_contains($q,$table) && str_contains($q,'FOR UPDATE')));
        }
    }

    public function testSuspendedActorIsRejectedInsideTransaction(): void
    {
        $this->pdo->prepare("UPDATE users SET status='SUSPENDED' WHERE id=?")->execute([$this->users['SUBJECT_TEACHER']['user']]);
        $this->assertRejected(fn ()=>$this->batch($this->matrix())); $this->assertNoScoreWrites();
    }

    public function testLivePermissionLockBlocksConcurrentRevocationForEntireBatch(): void
    {
        $config=require dirname(__DIR__,2).'/htdocs/config/database.php'; $config['database']='pp5_test';
        $other=new PDO(App\Support\Database::dsn($config),$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $other->exec('SET SESSION innodb_lock_wait_timeout=1');
        $role=$this->rows("SELECT id FROM roles WHERE code='SUBJECT_TEACHER'")[0]['id'];
        $permission=$this->rows("SELECT id FROM permissions WHERE code='GRADEBOOK_SCORE_ENTER'")[0]['id'];
        $revoke=$other->prepare('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?');
        $other->beginTransaction(); $revoke->execute([$role,$permission]); self::assertSame(1,$revoke->rowCount()); $other->rollBack();
        $blocked=false;
        $this->pdo->beforeLock=function ($sql) use ($other,$revoke,$role,$permission,&$blocked): void {
            if (!str_contains($sql,'FROM gradebook_scores')) { return; }
            $this->pdo->beforeLock=null; $other->beginTransaction();
            try { $revoke->execute([$role,$permission]); self::fail('Revocation must wait until batch commits'); }
            catch (PDOException $e) { self::assertSame(1205,$e->errorInfo[1]); $blocked=true; }
            finally { $other->rollBack(); }
        };
        self::assertSame(4,$this->batch($this->matrix())['changed_count']); self::assertTrue($blocked);
    }

    public function testOverlappingCommandsUseCurrentLockedValuesForNoopAndAudit(): void
    {
        $m=$this->matrix(); $this->batch($m);
        $this->pdo->beforeLock=function ($sql): void {
            if (str_contains($sql,'FROM gradebook_scores')) {
                $this->pdo->beforeLock=null;
                $this->pdo->prepare('UPDATE gradebook_scores SET score=? WHERE enrollment_id=? AND component_id=?')->execute(['7.00',$this->f['enrollment_current'],$this->f['componentA']]);
            }
        };
        self::assertSame(1,$this->batch($m)['changed_count']);
        self::assertSame('7.00',json_decode($this->scoreAudits()[4]['old_value'],true)['score']);
        self::assertFalse($this->setCell('1','SUBJECT_TEACHER')['changed']);
        self::assertTrue($this->setCell('6','SUBJECT_TEACHER')['changed']);
        self::assertSame(1,$this->batch($m)['changed_count']); self::assertSame('1.00',$this->cellRows()[0]['score']);
    }
}
