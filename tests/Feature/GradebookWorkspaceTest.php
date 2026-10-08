<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use App\Support\View;
require_once dirname(__DIR__) . '/Support/GradebookScoreFixtures.php';

final class GradebookWorkspaceTest extends TestCase
{
    use GradebookScoreFixtures;

    public static function modes(): array
    {
        return [['SCHOOL_ADMIN', true, true], ['SUBJECT_TEACHER', true, false], ['EXECUTIVE', false, false]];
    }

    #[DataProvider('modes')]
    public function testContextAndAuthorizedDestinations(string $role, bool $writable, bool $manage): void
    {
        $this->login($role);
        $r = $this->request('GET', $this->readPath());
        self::assertSame(200, $r->status());
        $x = $this->xpath($r->body());
        $model = $this->gradebook($role);
        self::assertSame(1, $x->query('//h1')->length);
        self::assertSame('สมุดคะแนน — ' . $model['offering']['subject_name'], $x->evaluate('string(//h1)'));
        $header = $x->evaluate('string(//header[@aria-labelledby="gradebook-context-title"])');
        foreach (['classroom_name', 'classroom_code', 'subject_code', 'year_be'] as $key) {
            self::assertStringContainsString((string) $model['offering'][$key], $header);
        }
        self::assertStringContainsString('ภาคเรียน ' . $model['offering']['term_no'], $header);
        self::assertSame($writable ? 'แก้ไขคะแนนได้' : 'อ่านอย่างเดียว', $x->evaluate('string(//*[@id="gradebook-mode"])'));
        $room = '/workspaces/classrooms/' . $this->f['roomA'];
        $selected = $room . '/subjects?offering_id=' . $this->f['offeringA'];
        self::assertSame(1, $x->query('//nav[@aria-label="งานสมุดคะแนน"]//a[@href="/gradebooks"]')->length);
        self::assertSame(1, $x->query('//nav[@aria-label="งานสมุดคะแนน"]//a[@href="' . $room . '"]')->length);
        self::assertSame($manage ? 2 : 1, $x->query('//nav[@aria-label="งานสมุดคะแนน"]//a[@href="' . $selected . '"]')->length);
        self::assertSame($manage ? 1 : 0, $x->query('//nav[@aria-label="งานสมุดคะแนน"]//a[contains(.,"จัดการรายการคะแนน")]')->length);
        self::assertSame(200, $this->request('GET', $room . '/subjects', [], ['offering_id' => (string) $this->f['offeringA']])->status());
        self::assertSame(200, $this->request('GET', $room)->status());
        self::assertSame(1, $x->query('//link[@href="/assets/vendor/tabler/tabler-1.6.1.min.css"]')->length);
        self::assertSame(0, $x->query('//link[contains(@href,"bootstrap")]')->length);
        $this->assertReadSafe($r->body());
    }

    public function testQuickViewUsesAuthoritativeActiveItemsAndPreservesGridBoundary(): void
    {
        $this->login();
        $this->pdo->queries = [];
        $r = $this->request('GET', $this->readPath());
        $x = $this->xpath($r->body());
        $summary = $x->evaluate('string(//details[@id="gradebook-components"]/summary)');
        self::assertStringContainsString('2 รายการ', $summary);
        self::assertStringContainsString('35.50', $summary);
        $panel = $x->evaluate('string(//details[@id="gradebook-components"])');
        self::assertStringContainsString('งาน', $panel);
        self::assertStringContainsString('เต็ม 15.50 คะแนน', $panel);
        self::assertStringContainsString('สอบ', $panel);
        self::assertStringContainsString('เต็ม 20.00 คะแนน', $panel);
        self::assertStringNotContainsString('ประวัติ', $panel);
        self::assertSame(1, count(array_filter($this->pdo->queries, static fn ($sql) => str_contains($sql, 'FROM gradebook_components'))));
        foreach (['gradebook-guidance', 'gradebook-range-status', 'gradebook-batch-status', 'gradebook-grid-data',
            'gradebook-tabulator', 'gradebook-range-actions', 'gradebook-range-summary', 'gradebook-fill-value',
            'gradebook-fill-submit', 'gradebook-clear-submit'] as $id) {
            self::assertSame(1, $x->query('//*[@id="' . $id . '"]')->length, $id);
        }
        self::assertSame(0, $x->query('//details//*[@id="gradebook-tabulator" or @id="gradebook-range-actions" or @data-grid-score-cell]')->length);
        self::assertSame(2, $x->query('//*[@aria-describedby="gradebook-guidance"]')->length);
        self::assertGreaterThan(0, $x->query('//*[@data-grid-score-cell and @data-grid-row and @data-grid-column and @data-grid-editable]')->length);
        self::assertSame(1, $x->query('//p[@id="gradebook-batch-status" and @role="status" and @aria-live="polite"]')->length);
    }

    public static function frozen(): array { return [['year'], ['offering']]; }
    #[DataProvider('frozen')]
    public function testLifecycleAndHistoricalScoresStayReadOnly(string $kind): void
    {
        $this->login('SUBJECT_TEACHER');
        $this->changePrerequisite($kind);
        $r = $this->request('GET', $this->readPath());
        $x = $this->xpath($r->body());
        self::assertSame('อ่านอย่างเดียว', $x->evaluate('string(//*[@id="gradebook-mode"])'));
        self::assertSame(0, $x->query('//main//form|//main//input|//main//a[contains(.,"จัดการรายการคะแนน")]')->length);
        self::assertGreaterThan(0, $x->query('//tr[contains(@class,"pp5-historical")]//*[@data-grid-score-cell and @data-grid-editable="false"]')->length);
        self::assertSame(1, $x->query('//script[contains(@src,"gradebook.js")]')->length);
    }

    public function testScopedAccessDoesNotDiscoverOtherOfferingsAndCannotManage(): void
    {
        $this->login('SUBJECT_TEACHER');
        $selected = '/workspaces/classrooms/' . $this->f['roomA'] . '/subjects?offering_id=' . $this->f['offeringA'];
        $path = '/workspaces/classrooms/' . $this->f['roomA'] . '/subjects';
        $query = ['offering_id' => (string) $this->f['offeringA']];
        $r = $this->request('GET', $path, [], $query);
        self::assertSame(200, $r->status());
        $x = $this->xpath($r->body());
        self::assertSame(0, $x->query('//*[@id="subject-context"]//form')->length);
        foreach (['Other', 'B', 'Closed'] as $offering) {
            self::assertSame(404, $this->request('GET', $this->readPath($offering))->status());
            self::assertStringNotContainsString('offering_id=' . $this->f['offering' . $offering] . '"', $r->body());
        }
        self::assertSame(403, $this->request('GET', $this->path())->status());
        $this->revoke('scope');
        self::assertSame(404, $this->request('GET', $this->readPath())->status());
        self::assertSame(404, $this->request('GET', $path, [], $query)->status());
    }

    public function testEmptyQuickViewAndEscapingAndAuthorizedFallback(): void
    {
        $this->login();
        $model = $this->gradebook();
        $model['components'] = [];
        $model['active_component_count'] = 0;
        $model['configured_max_total'] = '0.00';
        $model['rows'] = [];
        $hostile = '<script>alert("context")</script>';
        $model['offering']['subject_name'] = $hostile;
        $html = View::render('gradebook/view', ['gradebook' => $model, 'canScore' => false, 'canManageComponents' => true]);
        $x = $this->xpath($html);
        self::assertStringContainsString('0 รายการ', $x->evaluate('string(//details[@id="gradebook-components"]/summary)'));
        self::assertStringContainsString('0.00', $html);
        self::assertStringContainsString('ยังไม่มีรายการคะแนนที่ใช้งานอยู่', $html);
        self::assertStringContainsString('ไม่มีรายชื่อนักเรียน', $html);
        self::assertSame(1, $x->query('//nav[@aria-label="งานสมุดคะแนน"]//a[@href="' . $this->path() . '"]')->length);
        self::assertSame(0, $x->query('//nav[@aria-label="งานสมุดคะแนน"]//a[contains(@href,"workspaces")]')->length);
        self::assertStringContainsString(htmlspecialchars($hostile, ENT_QUOTES, 'UTF-8'), $html);
        self::assertStringNotContainsString($hostile, $html);
    }
}
