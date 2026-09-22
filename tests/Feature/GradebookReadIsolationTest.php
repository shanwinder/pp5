<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookReadFixtures.php';

final class GradebookReadIsolationTest extends TestCase
{
    use GradebookReadFixtures;

    public function testBrowserCannotOverrideSessionAuthorityOrTargetOffering(): void
    {
        $this->login('SUBJECT_TEACHER'); $before=$this->readSnapshot();
        $injection=['school_id'=>$this->f['schoolB'],'user_id'=>$this->users['FOREIGN']['user'],'actor_user_id'=>$this->users['FOREIGN']['user'],
            'context_type'=>'SYSTEM','role'=>'SYSTEM_ADMIN','permission'=>'GRADEBOOK_VIEW','scope'=>'*','offeringId'=>$this->f['offeringB'],
            'subject_offering_id'=>$this->f['offeringB'],'classroom_id'=>$this->f['roomB'],'academic_year_id'=>$this->f['yearB']];
        $r=$this->request('GET',$this->readPath(),$injection,$injection); self::assertSame(200,$r->status()); $this->assertReadSafe($r->body());
        $denied=$this->request('GET',$this->readPath('Other'),$injection,$injection);
        self::assertSame(404,$denied->status()); $this->assertReadSafe($denied->body()); self::assertSame($before,$this->readSnapshot());
    }
    public function testForeignMissingOffScopeAndRevokedHaveIdenticalResponses(): void
    {
        $this->login('SUBJECT_TEACHER'); $reference=$this->request('GET','/gradebook/'.PHP_INT_MAX); self::assertSame(404,$reference->status());
        foreach ([$this->readPath('B'),$this->readPath('Other'),'/gradebook/0','/gradebook/999999999999999999999999'] as $path) {
            $r=$this->request('GET',$path); self::assertSame(404,$r->status()); self::assertSame($reference->body(),$r->body()); $this->assertReadSafe($r->body());
        }
        $this->revoke('scope'); $r=$this->request('GET',$this->readPath()); self::assertSame(404,$r->status()); self::assertSame($reference->body(),$r->body());
        foreach (['VIEWER','HOMEROOM_TEACHER','SYSTEM_ADMIN'] as $role) {
            $this->login($role); $r=$this->request('GET',$this->readPath()); self::assertSame(404,$r->status()); self::assertSame($reference->body(),$r->body());
        }
    }
    public function testForeignAndOtherOfferingDataNeverEnterModelHtmlOrNavigation(): void
    {
        $this->login(); $model=$this->gradebook(); $this->assertReadSafe(json_encode($model,JSON_UNESCAPED_UNICODE));
        $r=$this->request('GET',$this->readPath()); self::assertSame(200,$r->status()); $this->assertReadSafe($r->body());
        foreach (['18.75','17.65','FOREIGN_SECRET'] as $marker) { self::assertStringNotContainsString($marker,$r->body()); }
        $dashboard=$this->request('GET','/dashboard'); self::assertSame(200,$dashboard->status());
        self::assertStringNotContainsString('FOREIGN_SECRET',$dashboard->body()); self::assertStringNotContainsString('href="'.$this->readPath('B').'"',$dashboard->body());
        self::assertSame(404,$this->request('GET',$this->readPath('B'))->status());
        $other=$this->request('GET',$this->readPath('Other')); self::assertSame(200,$other->status());
        self::assertStringContainsString('OTHER_COMPONENT',$other->body()); self::assertStringContainsString('18.75',$other->body());
        self::assertStringNotContainsString('01-CURRENT',$other->body());
    }
    public function testMultipleRolesCannotBorrowAnotherAssignmentsScope(): void
    {
        $role=$this->rows("SELECT id FROM roles WHERE code='VIEWER'")[0]['id'];
        $assignment=$this->insert('user_role_assignments',['school_id'=>$this->f['schoolA'],'user_id'=>$this->users['SUBJECT_TEACHER']['user'],'role_id'=>$role]);
        $this->scope($assignment,'Other'); $this->login('SUBJECT_TEACHER');
        self::assertSame(404,$this->request('GET',$this->readPath('Other'))->status());
        self::assertStringNotContainsString('href="'.$this->readPath('Other').'"',$this->request('GET','/dashboard')->body());
        self::assertSame(200,$this->request('GET',$this->readPath())->status());
    }
}
