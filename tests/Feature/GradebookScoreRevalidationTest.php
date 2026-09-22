<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookScoreFixtures.php';

final class GradebookScoreRevalidationTest extends TestCase
{
    use GradebookScoreFixtures;

    public static function prerequisites(): array
    {
        $cases = [];
        foreach (['scope','assignment','membership','school','role','permission','year','offering','component','enrollment','placement'] as $kind) {
            foreach (['6',null] as $score) { $cases[] = [$kind,$score]; }
        }
        return $cases;
    }
    #[DataProvider('prerequisites')]
    public function testStateChangedAfterValidReadDeniesUpdateAndClear(string $kind, ?string $score): void
    {
        $this->setCell('5','SUBJECT_TEACHER');
        $render = $this->gradebook('SUBJECT_TEACHER'); self::assertNotNull($render);
        self::assertSame('CURRENT',$this->modelRow($render,'current')['row_type']);
        $this->changePrerequisite($kind);
        $this->assertRejected(fn () => $this->setCell($score,'SUBJECT_TEACHER'));
    }

    public static function lockChanges(): array
    {
        return [['schools','school'],['academic_years','year'],['subject_offerings','offering'],['gradebook_components','component'],
            ['student_enrollments','enrollment'],['student_classroom_placements','placement'],['user_role_assignments','scope'],['user_role_assignments','permission']];
    }
    #[DataProvider('lockChanges')]
    public function testAuthoritativeStateIsReadAtLockTime(string $table, string $kind): void
    {
        $triggered = false;
        $this->pdo->beforeLock = function (string $sql) use ($table,$kind,&$triggered): void {
            if (str_contains($sql,'FROM ' . $table)) {
                $this->pdo->beforeLock = null; $triggered = true; $this->changePrerequisite($kind);
            }
        };
        $this->assertRejected(fn () => $this->setCell('3','SUBJECT_TEACHER')); self::assertTrue($triggered);
        self::assertSame(1,$this->pdo->depth);
    }

    public function testLockedMaximumAndCurrentCellDetermineValidationAuditAndNoop(): void
    {
        $this->pdo->beforeLock = function (string $sql): void {
            if (str_contains($sql,'FROM gradebook_components')) {
                $this->pdo->beforeLock = null;
                $this->pdo->prepare('UPDATE gradebook_components SET max_score=? WHERE id=?')->execute(['0.30',$this->f['componentA']]);
            }
        };
        $this->assertRejected(fn () => $this->setCell('0.31'));
        $this->setCell('5'); $render = $this->gradebook(); self::assertSame('5.00',$this->modelRow($render,'current')['scores'][$this->f['componentA']]);
        $this->pdo->beforeLock = function (string $sql): void {
            if (str_contains($sql,'FROM gradebook_scores')) {
                $this->pdo->beforeLock = null;
                $this->pdo->prepare('UPDATE gradebook_scores SET score=? WHERE enrollment_id=?')->execute(['7.00',$this->f['enrollment_current']]);
            }
        };
        self::assertTrue($this->setCell('5')['changed']);
        self::assertSame('7.00',json_decode($this->scoreAudits()[1]['old_value'],true)['score']);
        $this->pdo->prepare('UPDATE gradebook_scores SET score=? WHERE enrollment_id=?')->execute(['8.00',$this->f['enrollment_current']]);
        $this->pdo->queries = []; self::assertFalse($this->setCell('08')['changed']); $this->assertNoScoreWrites();
        self::assertCount(2,$this->scoreAudits());
    }

    public function testStableLockOrderAndScopedGrantRowsAreInLockingQuery(): void
    {
        $this->pdo->queries = []; $this->setCell('5','SUBJECT_TEACHER');
        $locks = array_values(array_filter($this->pdo->queries,fn ($sql) => str_contains($sql,'FOR UPDATE')));
        $expected = ['schools','academic_years','subject_offerings','gradebook_components','student_enrollments','student_classroom_placements'];
        foreach ($expected as $i => $table) { self::assertStringContainsString('FROM ' . $table,$locks[$i]); }
        self::assertStringContainsString('FROM gradebook_scores',$locks[array_key_last($locks)]);
        $auth = implode("\n",array_filter($locks,fn ($sql) => str_contains($sql,'FROM user_role_assignments')));
        foreach (['JOIN roles','JOIN role_permissions','JOIN school_memberships','JOIN permission_scopes','ps.user_role_assignment_id = ura.id'] as $part) { self::assertStringContainsString($part,$auth); }
        self::assertSame(1,$this->pdo->depth);
    }

    public static function failures(): array
    {
        return [['INSERT INTO audit_logs',null,'5'],['INSERT INTO audit_logs','5','6'],['INSERT INTO audit_logs','5',null],
            ['INSERT INTO gradebook_scores',null,'5'],['UPDATE gradebook_scores','5','6'],['UPDATE gradebook_scores','5',null],
            ['FROM user_role_assignments',null,'5']];
    }
    #[DataProvider('failures')]
    public function testFailuresRollBackCompletely(string $query, ?string $existing, ?string $input): void
    {
        if ($existing !== null) { $this->setCell($existing); }
        $before = $this->structureSnapshot(); $this->pdo->failPrepare = $query;
        $this->assertRejected(fn () => $this->setCell($input,'SUBJECT_TEACHER'));
        self::assertTrue($this->pdo->failureTriggered); self::assertSame(1,$this->pdo->depth);
        self::assertSame($before,$this->structureSnapshot());
    }

    public static function grantKinds(): array { return [['SCHOOL_ADMIN'],['SUBJECT_TEACHER']]; }
    #[DataProvider('grantKinds')]
    public function testConcurrentPermissionRevocationCannotPassBetweenAuthorizationAndScoreWrite(string $role): void
    {
        $config = require dirname(__DIR__,2) . '/htdocs/config/database.php'; $config['database'] = 'pp5_test';
        $other = new PDO(App\Support\Database::dsn($config),$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $other->exec('SET SESSION innodb_lock_wait_timeout=1');
        $roleId = $this->rows('SELECT id FROM roles WHERE code=?',[$role])[0]['id'];
        $permissionId = $this->rows("SELECT id FROM permissions WHERE code='GRADEBOOK_SCORE_ENTER'")[0]['id'];
        // Prove fixture setup itself has not locked this committed permission mapping.
        $revoke = $other->prepare('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?');
        $other->beginTransaction(); $revoke->execute([$roleId,$permissionId]); self::assertSame(1,$revoke->rowCount()); $other->rollBack();
        $blocked = false;
        $this->pdo->beforeLock = function (string $sql) use ($other,$revoke,$roleId,$permissionId,&$blocked): void {
            if (!str_contains($sql,'FROM gradebook_scores')) { return; }
            $this->pdo->beforeLock = null; $other->beginTransaction();
            try { $revoke->execute([$roleId,$permissionId]); self::fail('Revocation must wait for score transaction'); }
            catch (PDOException $e) { self::assertSame(1205,$e->errorInfo[1]); $blocked = true; }
            finally { if ($other->inTransaction()) { $other->rollBack(); } }
        };
        try { self::assertTrue($this->setCell('3',$role)['changed']); self::assertTrue($blocked); }
        finally { if ($other->inTransaction()) { $other->rollBack(); } }
    }
}
