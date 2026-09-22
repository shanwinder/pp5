<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookScoreFixtures.php';

final class GradebookScoreServiceTest extends TestCase
{
    use GradebookScoreFixtures;

    public function testInsertUpdateClearRetainIdentityAndAuditExactValuesAndActors(): void
    {
        $structure = $this->structureSnapshot();
        $id = null; $created = null; $old = null;
        foreach ([['5','5.00','SCHOOL_ADMIN'],['7.5','7.50','SUBJECT_TEACHER'],[null,null,'ACADEMIC_ADMIN']] as $index => [$input,$canonical,$role]) {
            $result = $this->setCell($input, $role);
            $id ??= $result['score_id'];
            self::assertSame(['score_id'=>$id,'subject_offering_id'=>$this->f['offeringA'],'component_id'=>$this->f['componentA'],
                'enrollment_id'=>$this->f['enrollment_current'],'score'=>$canonical,'changed'=>true], $result);
            $rows = $this->cellRows(); self::assertCount(1, $rows); $row = $rows[0];
            $created ??= $row['created_at'];
            self::assertSame($id, $row['id']); self::assertSame($created, $row['created_at']);
            self::assertSame($canonical, $row['score']); self::assertSame($this->users[$role]['user'], $row['updated_by']);
            $audits = $this->scoreAudits(); self::assertCount($index + 1, $audits); $a = $audits[$index];
            self::assertSame($id, $a['entity_id']); self::assertSame('gradebook_scores', $a['entity_type']);
            self::assertSame($this->f['schoolA'], $a['school_id']); self::assertSame($this->users[$role]['user'], $a['user_id']);
            self::assertSame('127.0.0.1', $a['ip_address']);
            $identity = ['subject_offering_id'=>$this->f['offeringA'],'enrollment_id'=>$this->f['enrollment_current'],'component_id'=>$this->f['componentA']];
            self::assertSame($identity + ['score'=>$old], json_decode($a['old_value'], true, 512, JSON_THROW_ON_ERROR));
            self::assertSame($identity + ['score'=>$canonical], json_decode($a['new_value'], true, 512, JSON_THROW_ON_ERROR));
            $this->assertReadSafe(json_encode([$result,$a], JSON_THROW_ON_ERROR)); $old = $canonical;
        }
        self::assertSame($structure, $this->structureSnapshot());
    }

    public static function decimals(): array
    {
        return [['0','0.00'],['0.0','0.00'],['0.00','0.00'],['1','1.00'],['1.5','1.50'],['01.50','1.50'],
            [" \t\n1.50\r\v\f",'1.50'],['19.99','19.99'],['20','20.00'],['99999.99','99999.99']];
    }
    #[DataProvider('decimals')]
    public function testCanonicalDecimalAndInclusiveLimits(string $input, string $expected): void
    {
        if ($expected === '99999.99') { $this->pdo->prepare('UPDATE gradebook_components SET max_score=? WHERE id=?')->execute([$expected,$this->f['componentA']]); }
        $r = $this->setCell($input); self::assertTrue($r['changed']); self::assertSame($expected, $r['score']);
        self::assertSame($expected, $this->cellRows()[0]['score']);
    }
    public static function invalidDecimals(): array
    {
        return array_map(fn ($v) => [$v], ['-1','+1','1e2','1E2','10,5','1.234','NaN','Infinity','-INF','1 2','100000.00','20.01','',' ','1.2.3','.5','1.',"\0".'1',"1\0","1\t2"]);
    }
    #[DataProvider('invalidDecimals')]
    public function testRejectsInvalidDecimalWithoutWrites(string $value): void { $this->assertRejected(fn () => $this->setCell($value)); }

    public function testFractionalMaximumUsesExactDecimalComparison(): void
    {
        $this->pdo->prepare('UPDATE gradebook_components SET max_score=? WHERE id=?')->execute(['0.30',$this->f['componentA']]);
        self::assertSame('0.29', $this->setCell('0.29')['score']);
        self::assertSame('0.30', $this->setCell('0.30')['score']);
        $this->assertRejected(fn () => $this->setCell('0.31'));
    }

    public static function noops(): array { return [['complete','5'],['complete','5.0'],['complete','05.00'],['zero','0'],['null',null],['current',null]]; }
    #[DataProvider('noops')]
    public function testNoopDoesNotWriteAuditOrChangeActorOrTimestamps(string $enrollment, ?string $input): void
    {
        $before = $this->snapshot(); $cell = $this->cellRows($enrollment); $this->pdo->queries = [];
        $r = $this->setCell($input, 'SUBJECT_TEACHER', $enrollment);
        self::assertFalse($r['changed']); self::assertSame($cell[0]['id'] ?? null, $r['score_id']);
        self::assertSame($cell[0]['score'] ?? null, $r['score']); $this->assertNoScoreWrites();
        self::assertSame($before, $this->snapshot()); self::assertCount($enrollment === 'current' ? 0 : 1, $this->cellRows($enrollment));
    }

    public function testNullToZeroIsARealEnteredScore(): void
    {
        $id = $this->cellRows('null')[0]['id']; $r = $this->setCell('0', 'SUBJECT_TEACHER', 'null');
        self::assertTrue($r['changed']); self::assertSame($id, $r['score_id']); self::assertSame('0.00', $r['score']);
        self::assertCount(1,$this->scoreAudits());
        $model = $this->modelRow($this->gradebook(), 'null');
        self::assertSame('0.00',$model['scores'][$this->f['componentA']]); self::assertSame(1,$model['entered_component_count']);
    }

    public function testClearedHistorySurvivesMoveButAbsentClearDoesNotManufactureHistory(): void
    {
        $r = $this->setCell('5'); $this->setCell(null); $this->changePrerequisite('placement');
        self::assertSame($r['score_id'],$this->cellRows()[0]['id']); self::assertCount(1,$this->cellRows());
        $row = $this->modelRow($this->gradebook(), 'current'); self::assertSame('HISTORICAL',$row['row_type']);
        self::assertNull($row['scores'][$this->f['componentA']]); self::assertSame(0,$row['entered_component_count']);
        foreach (['6',null] as $input) { $this->assertRejected(fn () => $this->setCell($input)); }
        $this->f['enrollment_current'] = $this->student('NEVER-ENTERED');
        self::assertFalse($this->setCell(null)['changed']); self::assertCount(0,$this->cellRows());
        $this->changePrerequisite('placement');
        self::assertNotContains($this->f['enrollment_current'],array_column($this->gradebook()['rows'],'enrollment_id'));
    }

    public function testActiveAcademicYearAllowsScores(): void
    {
        self::assertSame('2.00',$this->setCell('2','SCHOOL_ADMIN','wrongYear','componentNext','Next')['score']);
    }
}
