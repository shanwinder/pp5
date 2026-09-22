<?php
declare(strict_types=1);

use App\Repositories\GradebookComponentRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/GradebookComponentFixtures.php';

final class GradebookComponentServiceTest extends TestCase
{
    use GradebookComponentFixtures;

    public static function decimals(): iterable
    {
        foreach (['1' => '1.00', '1.5' => '1.50', '01.50' => '1.50', '99999.99' => '99999.99', '0.01' => '0.01', ' 00020.0 ' => '20.00'] as $in => $out) { yield [(string) $in, $out]; }
    }
    #[DataProvider('decimals')]
    public function testCanonicalDecimalPersistenceAndCreateAudit(string $input, string $expected): void
    {
        $before = $this->snapshot(); $id = $this->create(['max_score' => $input]); $row = $this->row('gradebook_components', $id);
        self::assertSame($expected, $row['max_score']); self::assertSame('ACTIVE', $row['status']); self::assertSame(0, $row['sort_order']);
        self::assertSame($this->f['schoolA'], $row['school_id']); self::assertSame($this->f['yearA'], $row['academic_year_id']);
        $after = $this->snapshot(); self::assertCount(count($before['audit_logs']) + 1, $after['audit_logs']); $audit = end($after['audit_logs']);
        self::assertSame('GRADEBOOK_COMPONENT_CREATED', $audit['action']); self::assertSame($this->users['SCHOOL_ADMIN']['user'], $audit['user_id']);
        self::assertSame($id, $audit['entity_id']); self::assertSame($this->f['schoolA'], $audit['school_id']);
        self::assertSame($expected, json_decode($audit['new_value'], true)['max_score']); self::assertNull($audit['old_value']);
        self::assertSame('127.0.0.1', $audit['ip_address']);
    }
    public static function invalidFields(): iterable
    {
        foreach (['', '0', '0.00', '-1', '+1', '1e2', '1E2', '10,50', '1.234', '99999.991', '100000', '100000.00', 'NaN', 'Infinity', '-INF', '1 0', '1..2', '.5', '1.', '000.000', '999999999999999999999999999'] as $v) { yield ['max_score', $v]; }
        foreach (['', ' ', str_repeat('ก', 51), "A\0B", "A\nB", "A\rB", "A\tB", "\xFF"] as $v) { yield ['code', $v]; }
        foreach (['', ' ', str_repeat('ก', 191), "A\0B", "A\nB", "A\rB", "A\tB", "\xFF"] as $v) { yield ['name_th', $v]; }
        foreach ([-1, '-1', '1.0', 1.5, '1e2', 'a', '', '65536', '999999999999999999999', true, false, null, [], new stdClass(), '+1', ' 1'] as $v) { yield ['sort_order', $v]; }
    }
    #[DataProvider('invalidFields')]
    public function testInvalidFieldsRejectCreateAndUpdateWithoutWrites(string $field, mixed $value): void
    {
        $this->assertRejected(fn () => $this->create([$field => $value]));
        $this->assertRejected(fn () => $this->update([$field => $value]));
    }
    public function testUnicodeBoundariesTrimAndSortRange(): void
    {
        $id = $this->create(['code' => ' ' . str_repeat('ก', 50) . ' ', 'name_th' => str_repeat('ก', 190), 'sort_order' => '65535'], 'Next');
        $row = $this->row('gradebook_components', $id);
        self::assertSame(str_repeat('ก', 50), $row['code']); self::assertSame(str_repeat('ก', 190), $row['name_th']);
        self::assertSame(65535, $row['sort_order']); self::assertSame($this->f['yearNext'], $row['academic_year_id']);
        $this->update(['sort_order' => '00000']); self::assertSame(0, $this->row('gradebook_components', $this->f['componentA'])['sort_order']);
    }
    public static function collisions(): iterable
    {
        yield ['EXAM', 'EXAM']; yield ['EXAM', 'exam']; yield ['café', 'cafe']; yield ['café', "cafe\u{0301}"];
    }
    #[DataProvider('collisions')]
    public function testDatabaseCollationControlsDuplicateCreateAndUpdate(string $existing, string $collision): void
    {
        $this->pdo->prepare('UPDATE gradebook_components SET code=? WHERE id=?')->execute([$existing, $this->f['componentA']]);
        $this->assertRejected(fn () => $this->create(['code' => $collision]));
        $id = $this->create(['code' => 'DISTINCT']); $this->f['componentA'] = $id;
        $this->assertRejected(fn () => $this->update(['code' => $collision]));
        $this->pdo->prepare('UPDATE gradebook_components SET code=? WHERE id=?')->execute(['OTHER_EXISTING', $this->f['componentOther']]);
        self::assertGreaterThan(0, $this->create(['code' => $collision], 'Other'));
    }
    public function testUpdateBeforeHistoryAndNormalizedNoOps(): void
    {
        $before = $this->snapshot(); $this->pdo->queries = [];
        $this->update(['code' => ' EXAM ', 'name_th' => ' สอบ ', 'max_score' => '020', 'sort_order' => '010']); $this->componentStatus('ACTIVE');
        self::assertSame($before, $this->snapshot()); $this->assertNoComponentUpdates();
        $this->update(['code' => 'MID', 'name_th' => 'กลางภาค', 'max_score' => '25.5', 'sort_order' => 2]);
        $after = $this->snapshot(); $audit = end($after['audit_logs']);
        self::assertSame('GRADEBOOK_COMPONENT_UPDATED', $audit['action']);
        self::assertSame('20.00', json_decode($audit['old_value'], true)['max_score']);
        self::assertSame('25.50', json_decode($audit['new_value'], true)['max_score']);
        self::assertSame('25.50', $this->row('gradebook_components', $this->f['componentA'])['max_score']);
        self::assertCount(count($before['audit_logs']) + 1, $after['audit_logs']);
        $this->componentStatus('INACTIVE'); $before = $this->snapshot(); $this->pdo->queries = []; $this->componentStatus('INACTIVE');
        self::assertSame($before, $this->snapshot()); $this->assertNoComponentUpdates();
    }
    public static function scores(): iterable { yield [null]; yield ['0.00']; yield ['5.00']; }
    #[DataProvider('scores')]
    public function testAnyScoreHistoryFreezesMaxButAllowsMetadataAndStatus(?string $score): void
    {
        $this->score($score); $before = $this->snapshot();
        $repo = new GradebookComponentRepository($this->pdo);
        self::assertTrue($repo->hasScoreHistory($this->f['schoolA'], $this->f['offeringA'], $this->f['componentA']));
        self::assertFalse($repo->hasScoreHistory($this->f['schoolB'], $this->f['offeringA'], $this->f['componentA']));
        self::assertFalse($repo->hasScoreHistory($this->f['schoolA'], $this->f['offeringOther'], $this->f['componentA']));
        $this->assertRejected(fn () => $this->update(['max_score' => '25']));
        $this->update(['code' => 'RENAMED', 'name_th' => 'ใหม่', 'sort_order' => '2', 'max_score' => '020.0']);
        foreach (['INACTIVE', 'ACTIVE'] as $status) {
            $this->componentStatus($status); $snapshot = $this->snapshot(); $audit = end($snapshot['audit_logs']);
            self::assertSame('GRADEBOOK_COMPONENT_STATUS_CHANGED', $audit['action']);
            self::assertSame(['status' => $status], json_decode($audit['new_value'], true));
            self::assertSame(['status' => $status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'], json_decode($audit['old_value'], true));
        }
        self::assertSame($before['gradebook_scores'], $this->snapshot()['gradebook_scores']);
        self::assertSame('20.00', $this->row('gradebook_components', $this->f['componentA'])['max_score']);
    }
    public static function frozen(): iterable
    {
        foreach (['create','update','activate','deactivate','noop'] as $action) {
            foreach (['Closed','Inactive','B','school'] as $parent) { yield [$action, $parent]; }
        }
    }
    #[DataProvider('frozen')]
    public function testAllMutationFamiliesRequireActiveSchoolOpenYearActiveOffering(string $action, string $key): void
    {
        if ($key === 'school') { $this->pdo->prepare("UPDATE schools SET status='SUSPENDED' WHERE id=?")->execute([$this->f['schoolA']]); $key = 'A'; }
        $this->assertRejected(fn () => $this->mutate($action, $key));
    }
    private function mutate(string $action, string $key = 'A'): mixed
    {
        return match ($action) {
            'create' => $this->create([], $key), 'update' => $this->update(['name_th' => 'แก้ไข'], $key),
            'activate' => $this->componentStatus('ACTIVE', $key), 'deactivate' => $this->componentStatus('INACTIVE', $key), 'noop' => $this->update([], $key),
        };
    }
    public static function failures(): iterable
    {
        foreach (['create','update','deactivate'] as $action) { foreach (['audit','write'] as $failure) { yield [$action, $failure]; } }
    }
    #[DataProvider('failures')]
    public function testWriteAndAuditFailuresRollBackEveryMutationFamily(string $action, string $failure): void
    {
        $this->pdo->failPrepare = $failure === 'audit' ? 'INSERT INTO audit_logs' : ($action === 'create' ? 'INSERT INTO gradebook_components' : 'UPDATE gradebook_components');
        $this->assertRejected(fn () => $this->mutate($action)); self::assertTrue($this->pdo->failureTriggered); self::assertSame(1, $this->pdo->depth);
    }
    public static function races(): iterable
    {
        foreach (['create','update','deactivate'] as $action) {
            yield [$action, 'academic_years', 'yearA', 'CLOSED']; yield [$action, 'subject_offerings', 'offeringA', 'INACTIVE'];
        }
    }
    #[DataProvider('races')]
    public function testStateChangesBeforeAuthoritativeLocksAreRevalidated(string $action, string $table, string $key, string $status): void
    {
        $triggered = false;
        $this->pdo->beforeLock = function (string $sql) use ($table, $key, $status, &$triggered): void {
            if (str_contains($sql, 'FROM ' . $table)) {
                $this->pdo->beforeLock = null; $triggered = true;
                $this->pdo->prepare("UPDATE {$table} SET status=? WHERE id=?")->execute([$status, $this->f[$key]]);
            }
        };
        $this->assertRejected(fn () => $this->mutate($action)); self::assertTrue($triggered);
    }
    public function testComponentIsLockedBeforeCurrentScoreHistoryRead(): void
    {
        $this->pdo->queries = []; $this->update(['max_score' => '21']);
        $locks = array_values(array_filter($this->pdo->queries, fn ($sql) => str_contains($sql, 'FOR UPDATE')));
        foreach (['schools','academic_years','subject_offerings','gradebook_components','gradebook_scores'] as $index => $table) {
            self::assertStringContainsString('FROM ' . $table, $locks[$index]);
        }
        $triggered = false;
        $this->pdo->beforeLock = function (string $sql) use (&$triggered): void {
            if (str_contains($sql, 'FROM gradebook_components')) {
                $this->pdo->beforeLock = null; $triggered = true; $this->score(null);
            }
        };
        $this->assertRejected(fn () => $this->update(['max_score' => '22'])); self::assertTrue($triggered);
    }
    public function testRepositoryScopesAndOrdersInactiveHistory(): void
    {
        $repo = new GradebookComponentRepository($this->pdo);
        $rows = $repo->listForOffering($this->f['schoolA'], $this->f['offeringA']);
        self::assertSame([$this->f['inactiveComponent'], $this->f['componentA']], array_column($rows, 'id'));
        self::assertSame('INACTIVE', $rows[0]['status']);
        self::assertSame([], $repo->listForOffering($this->f['schoolA'], $this->f['offeringB']));
        foreach (['findForOffering','lockForOffering'] as $method) {
            self::assertNull($repo->$method($this->f['schoolA'], $this->f['offeringOther'], $this->f['componentA']));
            self::assertNull($repo->$method($this->f['schoolB'], $this->f['offeringA'], $this->f['componentA']));
        }
        $id = $this->create(['sort_order' => 10]);
        self::assertSame([$this->f['inactiveComponent'], $this->f['componentA'], $id], array_column($repo->listForOffering($this->f['schoolA'], $this->f['offeringA']), 'id'));
    }
    public function testDecimalOuterNulIsNotWhitespace(): void
    {
        $this->assertRejected(fn () => $this->create(['max_score' => "\0" . '1' . "\0"]));
    }

    public function testComponentIdentityIsRevalidatedUnderItsLock(): void
    {
        $triggered = false;
        $this->pdo->beforeLock = function (string $sql) use (&$triggered): void {
            if (str_contains($sql, 'FROM gradebook_components')) {
                $this->pdo->beforeLock = null; $triggered = true;
                $this->pdo->prepare('UPDATE gradebook_components SET subject_offering_id=?,code=? WHERE id=?')
                    ->execute([$this->f['offeringOther'], 'MOVED', $this->f['componentA']]);
            }
        };
        $this->assertRejected(fn () => $this->update(['name_th' => 'changed']));
        self::assertTrue($triggered);
    }

    public function testInvalidStatusIsRejected(): void { $this->assertRejected(fn () => $this->componentStatus('DELETED')); }
    private function assertNoComponentUpdates(): void
    {
        self::assertSame([], array_values(array_filter($this->pdo->queries, fn ($sql) => str_starts_with($sql, 'UPDATE gradebook_components'))));
    }
}
