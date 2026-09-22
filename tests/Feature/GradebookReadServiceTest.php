<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookReadFixtures.php';

final class GradebookReadServiceTest extends TestCase
{
    use GradebookReadFixtures;

    public static function allowed(): iterable { foreach (['SCHOOL_ADMIN','ACADEMIC_ADMIN','EXECUTIVE','SUBJECT_TEACHER'] as $r) { yield [$r]; } }
    #[DataProvider('allowed')]
    public function testAuthorizedReadHasDeterministicRosterComponentsAndNoPii(string $role): void
    {
        $before = $this->readSnapshot(); $model = $this->gradebook($role); self::assertNotNull($model);
        self::assertSame($this->f['offeringA'], $model['offering']['id']);
        self::assertSame([$this->f['componentSecond'], $this->f['componentA']], array_column($model['components'], 'id'));
        $keys = ['current','zero','null','complete','moved','exited','nullHistory','oldHistory'];
        self::assertSame(array_map(fn ($k) => $this->f['enrollment_' . $k], $keys), array_column($model['rows'], 'enrollment_id'));
        foreach ($keys as $index => $key) { self::assertSame($index < 4 ? 'CURRENT' : 'HISTORICAL', $this->modelRow($model, $key)['row_type']); }
        self::assertSame('35.50', $model['configured_max_total']); self::assertSame(2, $model['active_component_count']);
        self::assertSame(['SUBJECT_TEACHER'], array_column($model['teachers'], 'display_name'));
        $this->assertReadSafe(json_encode($model, JSON_UNESCAPED_UNICODE)); self::assertSame($before, $this->readSnapshot());
    }
    public function testNullMissingAndZeroAreDistinctInCellsCountsAndCompleteness(): void
    {
        $model = $this->gradebook(); $cid = $this->f['componentA'];
        foreach (['current','null','nullHistory','oldHistory'] as $key) {
            $row = $this->modelRow($model, $key); self::assertNull($row['scores'][$cid]);
            self::assertSame(0, $row['entered_component_count']); self::assertSame('0.00', $row['entered_score_total']); self::assertFalse($row['complete']);
        }
        $zero = $this->modelRow($model, 'zero'); self::assertSame('0.00', $zero['scores'][$cid]);
        self::assertSame(1, $zero['entered_component_count']); self::assertSame('0.00', $zero['entered_score_total']); self::assertFalse($zero['complete']);
        $complete = $this->modelRow($model, 'complete'); self::assertSame('5.00', $complete['entered_score_total']);
        self::assertSame(2, $complete['entered_component_count']); self::assertTrue($complete['complete']);
        self::assertSame('2.25', $this->modelRow($model, 'moved')['entered_score_total']);
        self::assertSame('4.00', $this->modelRow($model, 'exited')['entered_score_total']);
        $this->pdo->prepare('UPDATE gradebook_scores SET score=NULL WHERE component_id=? AND enrollment_id=?')->execute([$this->f['componentSecond'],$this->f['enrollment_complete']]);
        $partial = $this->modelRow($this->gradebook(), 'complete'); self::assertSame(1, $partial['entered_component_count']); self::assertFalse($partial['complete']);
        $this->pdo->prepare("UPDATE gradebook_components SET status='INACTIVE' WHERE id=?")->execute([$this->f['componentSecond']]);
        self::assertTrue($this->modelRow($this->gradebook(), 'zero')['complete']);
    }
    public function testNoActiveComponentsKeepsHistoryAndCompletenessFalse(): void
    {
        $this->pdo->prepare("UPDATE gradebook_components SET status='INACTIVE' WHERE subject_offering_id=?")->execute([$this->f['offeringA']]);
        $model = $this->gradebook(); self::assertSame([], $model['components']); self::assertSame('0.00', $model['configured_max_total']); self::assertCount(8,$model['rows']);
        foreach ($model['rows'] as $row) {
            self::assertSame([], $row['scores']); self::assertSame('0.00', $row['entered_score_total']); self::assertSame('0.00', $row['configured_max_total']);
            self::assertSame(0, $row['entered_component_count']); self::assertSame(0, $row['active_component_count']); self::assertFalse($row['complete']);
        }
    }
    public function testExactDecimalTotalsAndTotalsAboveStorageMaximum(): void
    {
        $this->pdo->prepare('UPDATE gradebook_components SET max_score=? WHERE id=?')->execute(['0.10', $this->f['componentA']]);
        $this->pdo->prepare('UPDATE gradebook_components SET max_score=? WHERE id=?')->execute(['0.20', $this->f['componentSecond']]);
        $third = $this->insert('gradebook_components', ['school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'], 'subject_offering_id' => $this->f['offeringA'],
            'code' => 'THIRD', 'name_th' => 'สาม', 'max_score' => '0.30', 'sort_order' => 10]);
        foreach ([[$this->f['componentA'],'0.10'],[$this->f['componentSecond'],'0.20'],[$third,'0.00']] as [$c,$v]) { $this->scoreCell($this->f['enrollment_current'],$c,$v); }
        $m = $this->gradebook(); self::assertSame('0.60',$m['configured_max_total']); self::assertSame('0.30',$this->modelRow($m,'current')['entered_score_total']);
        self::assertSame([$this->f['componentSecond'],$this->f['componentA'],$third],array_column($m['components'],'id'));
        $this->pdo->prepare('UPDATE gradebook_components SET max_score=? WHERE subject_offering_id=? AND status=?')->execute(['99999.99',$this->f['offeringA'],'ACTIVE']);
        self::assertSame('299999.97',$this->gradebook()['configured_max_total']);
    }
    public static function revocations(): iterable { foreach (['scope','assignment','role','membership','permission','school'] as $v) { yield [$v]; } }
    #[DataProvider('revocations')]
    public function testEveryRevocationTakesEffectWithoutCache(string $kind): void
    {
        $service = $this->readService(); $uid = $this->users['SUBJECT_TEACHER']['user']; $sid = $this->f['schoolA']; $oid = $this->f['offeringA'];
        self::assertNotNull($service->getGradebook($uid,'SCHOOL',$sid,$oid)); self::assertCount(1,$service->listAccessibleOfferings($uid,'SCHOOL',$sid));
        $this->revoke($kind); $this->pdo->queries = [];
        self::assertNull($service->getGradebook($uid,'SCHOOL',$sid,$oid));
        self::assertCount(1,$this->pdo->queries, 'Denied read must stop at resource authorization');
        self::assertSame([],$service->listAccessibleOfferings($uid,'SCHOOL',$sid));
    }
    public function testDeniedForeignMissingAndWrongContextReturnNullBeforeDataReads(): void
    {
        foreach (['VIEWER','HOMEROOM_TEACHER','SYSTEM_ADMIN'] as $role) { self::assertNull($this->gradebook($role)); }
        self::assertNull($this->gradebook('SUBJECT_TEACHER','Other')); self::assertNull($this->gradebook('SCHOOL_ADMIN','B'));
        self::assertNull($this->readService()->getGradebook($this->users['SCHOOL_ADMIN']['user'],'SCHOOL',$this->f['schoolA'],PHP_INT_MAX));
        $this->pdo->queries = []; self::assertNull($this->gradebook('VIEWER')); self::assertCount(1,$this->pdo->queries);
    }
    public function testReadStateIsIndependentOfOfferingAndYearMutationState(): void
    {
        $this->pdo->prepare("UPDATE academic_years SET status='CLOSED' WHERE id=?")->execute([$this->f['yearA']]);
        $this->pdo->prepare("UPDATE subject_offerings SET status='INACTIVE' WHERE id=?")->execute([$this->f['offeringA']]);
        foreach (['SCHOOL_ADMIN','EXECUTIVE','SUBJECT_TEACHER'] as $role) {
            self::assertCount(8,$this->gradebook($role)['rows']); self::assertContains($this->f['offeringA'],array_column($this->accessible($role),'id'));
        }
    }
    public function testNavigationUsesResourcePermissionsAndRetainsHistoricalOfferings(): void
    {
        foreach (['SCHOOL_ADMIN','ACADEMIC_ADMIN','EXECUTIVE'] as $role) {
            $offerings = $this->accessible($role); self::assertCount(5,$offerings); self::assertNotContains($this->f['offeringB'],array_column($offerings,'id'));
        }
        self::assertSame([$this->f['offeringA']],array_column($this->accessible('SUBJECT_TEACHER'),'id')); self::assertSame([],$this->accessible('VIEWER'));
        $this->pdo->exec("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code='GRADEBOOK_VIEW'");
        self::assertNotNull($this->gradebook('VIEWER')); self::assertCount(5,$this->accessible('VIEWER'));
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='SCHOOL_ADMIN' AND p.code='GRADEBOOK_VIEW'");
        self::assertNull($this->gradebook()); self::assertSame([],$this->accessible('SCHOOL_ADMIN'));
    }
    public function testUnrelatedRoleScopeCannotSatisfyPermissionGrantingAssignment(): void
    {
        $role = $this->rows("SELECT id FROM roles WHERE code='VIEWER'")[0]['id'];
        $assignment = $this->insert('user_role_assignments',['school_id'=>$this->f['schoolA'],'user_id'=>$this->users['SUBJECT_TEACHER']['user'],'role_id'=>$role]);
        $this->scope($assignment,'Other');
        self::assertNull($this->gradebook('SUBJECT_TEACHER','Other'));
        self::assertSame([$this->f['offeringA']],array_column($this->accessible('SUBJECT_TEACHER'),'id'));
    }
    public function testReadsUseBoundedQueriesRegardlessOfMatrixSize(): void
    {
        $this->pdo->queries = []; $this->gradebook(); $small = count($this->pdo->queries); self::assertSame(6,$small);
        for ($i=0;$i<12;++$i) { $e=$this->student('EXPAND-'.$i); $this->scoreCell($e,$this->f['componentA'],'1.00'); }
        $before=$this->readSnapshot(); $this->pdo->queries=[]; $model=$this->gradebook();
        self::assertCount(20,$model['rows']); self::assertSame($small,count($this->pdo->queries));
        foreach ($this->pdo->queries as $sql) { self::assertDoesNotMatchRegularExpression('/^\s*(INSERT|UPDATE|DELETE)|FOR UPDATE/i',$sql); self::assertStringNotContainsString('national_id',$sql); }
        self::assertSame($before,$this->readSnapshot());
    }
    public function testStudentMasterStatusDoesNotOverrideActiveEnrollmentPlacement(): void
    {
        $e=$this->row('student_enrollments',$this->f['enrollment_current']);
        $this->pdo->prepare("UPDATE students SET status='INACTIVE' WHERE id=?")->execute([$e['student_id']]);
        self::assertSame('CURRENT',$this->modelRow($this->gradebook(),'current')['row_type']);
    }
    public function testDuplicateHistoricalPlacementsDoNotDuplicateRosterRows(): void
    {
        $this->placement($this->f['enrollment_current'],'A','INACTIVE');
        self::assertCount(8,$this->gradebook()['rows']);
    }
}
