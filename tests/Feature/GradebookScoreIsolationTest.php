<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookScoreFixtures.php';

final class GradebookScoreIsolationTest extends TestCase
{
    use GradebookScoreFixtures;

    public static function deniedTargets(): array
    {
        $targets = [['current','componentB','A'],['foreign','componentA','A'],['current','componentA','B'],
            ['foreign','componentB','B'],['current','componentOther','A'],['wrongRoom','componentA','A'],
            ['wrongRoom','componentOther','A'],['wrongYear','componentA','A'],['ended','componentA','A'],
            ['inactive','componentA','A'],['moved','componentA','A'],['exited','componentA','A'],
            ['nullHistory','componentA','A'],['oldHistory','inactiveComponent','A'],['current','inactiveComponent','A'],
            ['current','componentInactive','Inactive'],['current','componentClosed','Closed'],['current','componentNext','A']];
        $cases = [];
        foreach ($targets as $target) { foreach (['3',null] as $score) { $cases[] = [...$target,$score]; } }
        return $cases;
    }
    #[DataProvider('deniedTargets')]
    public function testForeignWrongOfferingAndHistoricalTargetsCannotBeEditedOrCleared(string $enrollment, string $component, string $offering, ?string $score): void
    {
        $before = $this->structureSnapshot();
        $this->assertRejected(fn () => $this->setCell($score,'SCHOOL_ADMIN',$enrollment,$component,$offering));
        self::assertSame($before,$this->structureSnapshot());
    }

    public static function roles(): array
    {
        return [['SCHOOL_ADMIN',true],['ACADEMIC_ADMIN',true],['SUBJECT_TEACHER',true],['EXECUTIVE',false],
            ['HOMEROOM_TEACHER',false],['VIEWER',false],['SYSTEM_ADMIN',false],['FOREIGN',false]];
    }
    #[DataProvider('roles')]
    public function testSeededPermissionOutcomes(string $role, bool $allowed): void
    {
        if ($role === 'EXECUTIVE') { self::assertNotNull($this->gradebook($role)); }
        if ($allowed) { self::assertTrue($this->setCell('3',$role)['changed']); }
        else { $this->assertRejected(fn () => $this->setCell('3',$role)); }
    }

    public function testTeacherCannotWriteOutsideScopeEvenWithValidRoster(): void
    {
        $this->assertRejected(fn () => $this->setCell('3','SUBJECT_TEACHER','wrongRoom','componentOther','Other'));
        $this->scope($this->users['SUBJECT_TEACHER']['assignment'],'Other');
        self::assertTrue($this->setCell('3','SUBJECT_TEACHER','wrongRoom','componentOther','Other')['changed']);
    }

    public function testDynamicUnscopedGrantAndImmediateRevoke(): void
    {
        $role = $this->rows("SELECT id FROM roles WHERE code='VIEWER'")[0]['id'];
        $permission = $this->rows("SELECT id FROM permissions WHERE code='GRADEBOOK_SCORE_ENTER'")[0]['id'];
        $this->insert('role_permissions',['role_id'=>$role,'permission_id'=>$permission,'resource_scope_type'=>null]);
        self::assertTrue($this->setCell('3','VIEWER')['changed']);
        $this->pdo->prepare('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?')->execute([$role,$permission]);
        $this->assertRejected(fn () => $this->setCell('4','VIEWER'));
    }

    public function testScopeCannotBeBorrowedFromUnrelatedRoleAssignment(): void
    {
        $this->pdo->prepare("UPDATE permission_scopes SET status='INACTIVE' WHERE id=?")->execute([$this->f['scopeA']]);
        $role = $this->rows("SELECT id FROM roles WHERE code='VIEWER'")[0]['id'];
        $unrelated = $this->insert('user_role_assignments',['school_id'=>$this->f['schoolA'],'user_id'=>$this->users['SUBJECT_TEACHER']['user'],'role_id'=>$role]);
        $this->scope($unrelated,'A');
        $this->assertRejected(fn () => $this->setCell('3','SUBJECT_TEACHER'));
        $this->pdo->prepare("UPDATE permission_scopes SET status='ACTIVE' WHERE id=?")->execute([$this->f['scopeA']]);
        self::assertTrue($this->setCell('3','SUBJECT_TEACHER')['changed']);
    }

    public function testUnknownResourceScopeDeniesAndViewAloneCannotAuthorizeTeacher(): void
    {
        $this->pdo->exec("UPDATE role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id
            SET rp.resource_scope_type='UNKNOWN' WHERE r.code='SUBJECT_TEACHER' AND p.code='GRADEBOOK_SCORE_ENTER'");
        self::assertNotNull($this->gradebook('SUBJECT_TEACHER'));
        $this->assertRejected(fn () => $this->setCell('3','SUBJECT_TEACHER'));
        $this->revokeScore('permission'); self::assertNotNull($this->gradebook('SUBJECT_TEACHER'));
        $this->assertRejected(fn () => $this->setCell('3','SUBJECT_TEACHER'));
    }

    public function testRepositoryBindsAllFiveIdentityPartsForLocksAndUpdates(): void
    {
        $repo = new App\Repositories\GradebookScoreRepository($this->pdo);
        $identity = [$this->f['schoolB'],$this->f['yearB'],$this->f['offeringB'],$this->f['enrollment_foreign'],$this->f['componentB']];
        $before = $this->snapshot();
        foreach ([$this->f['schoolA'],$this->f['yearA'],$this->f['offeringA'],$this->f['enrollment_current'],$this->f['componentA']] as $i => $wrong) {
            $mismatch = $identity; $mismatch[$i] = $wrong;
            self::assertNull($repo->lockCell(...$mismatch));
            $repo->updateScore(...[...$mismatch,null,$this->users['SCHOOL_ADMIN']['user']]);
            self::assertSame($before,$this->snapshot());
        }
        self::assertSame('17.65',$repo->lockCell(...$identity)['score']);
    }

    public function testMissingAndForeignTargetsUseTheSameSafeError(): void
    {
        $messages = [];
        foreach ([$this->f['offeringB'],PHP_INT_MAX] as $offering) {
            try { $this->scoreService()->setScore($this->f['schoolA'],$this->users['SCHOOL_ADMIN']['user'],$offering,$this->f['componentA'],$this->f['enrollment_current'],'3'); self::fail('Expected denial'); }
            catch (DomainException $e) { $messages[] = $e->getMessage(); $this->assertReadSafe($e->getMessage()); }
        }
        self::assertSame($messages[0],$messages[1]);
    }
}
