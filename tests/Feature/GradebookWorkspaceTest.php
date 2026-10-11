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
        self::assertSame(0, $x->query('//*[@id="gradebook-components" or contains(@class,"pp5-gradebook-heading")]')->length);
        $headers = $x->evaluate('string(//table/thead)');
        foreach (['งาน', '15.50', 'สอบ', '20.00'] as $text) {
            self::assertStringContainsString($text, $headers);
        }
        self::assertSame(1, $x->query('//table/thead/tr/th[contains(.,"คะแนนที่บันทึก")]')->length);
        self::assertSame(6, $x->query('//table/thead/tr/th')->length);
        self::assertSame(0, $x->query('//*[@id="gradebook-range-tool" or @id="gradebook-range-actions" or @id="gradebook-fill-value" or @id="gradebook-fill-submit" or @id="gradebook-clear-submit"]')->length);
        self::assertSame(1, count(array_filter($this->pdo->queries, static fn ($sql) => str_contains($sql, 'FROM gradebook_components'))));
        foreach (['gradebook-guidance', 'gradebook-range-status', 'gradebook-batch-status', 'gradebook-grid-data',
            'gradebook-tabulator'] as $id) {
            self::assertSame(1, $x->query('//*[@id="' . $id . '"]')->length, $id);
        }
        self::assertSame(0, $x->query('//details//*[@id="gradebook-tabulator" or @data-grid-score-cell]')->length);
        self::assertSame(2, $x->query('//*[@aria-describedby="gradebook-guidance"]')->length);
        self::assertGreaterThan(0, $x->query('//*[@data-grid-score-cell and @data-grid-row and @data-grid-column and @data-grid-editable]')->length);
        self::assertSame(1, $x->query('//p[@id="gradebook-batch-status" and @role="status" and @aria-live="polite"]')->length);
    }

    #[DataProvider('modes')]
    public function testRetiredToolsKeepCompleteGuidanceAndVisibleLiveFeedback(string $role, bool $writable, bool $manage): void
    {
        $this->login($role);
        $x = $this->xpath($this->request('GET', $this->readPath())->body());
        self::assertSame(0, $x->query('//*[@id="gradebook-help" or @id="gradebook-range-tool" or @id="gradebook-range-actions"]')->length);
        self::assertSame(1, $x->query('//*[@id="gradebook-guidance" and contains(@class,"visually-hidden") and not(@hidden) and not(@aria-hidden)]')->length);
        self::assertSame(0, $x->query('//details//*[@id="gradebook-guidance" or @id="gradebook-range-status" or @id="gradebook-batch-status"]')->length);
        $guidance = $x->evaluate('string(//*[@id="gradebook-guidance"])');
        self::assertStringNotContainsString('เครื่องมือช่วงคะแนน', $guidance);
        self::assertStringNotContainsString('วิธีกรอกคะแนน', $guidance);
        foreach (['Ctrl+C', 'Cmd+C', 'Escape นอกช่องแก้ไขล้างช่วงที่เลือก', 'ช่องว่างคือยังไม่มีคะแนน', '0.00 คือศูนย์ที่บันทึกแล้ว'] as $text) {
            self::assertStringContainsString($text, $guidance);
        }
        self::assertSame(1, $x->query('//*[@id="gradebook-range-status" and @role="status" and @aria-live="polite"]')->length);
        if ($writable) {
            foreach (['ดับเบิลคลิก, Enter หรือ F2', 'ลูกศรซ้ายขวาจะย้ายเคอร์เซอร์', 'Escape ในช่องแก้ไขยกเลิกโดยไม่บันทึก', 'Delete/Backspace เพื่อล้างช่วงที่เลือก'] as $text) {
                self::assertStringContainsString($text, $guidance);
            }
            self::assertSame('พร้อมกรอกคะแนน', $x->evaluate('string(//*[@id="gradebook-batch-status"])'));
            self::assertSame(1, $x->query('//*[@id="gradebook-batch-status" and @role="status" and @aria-live="polite" and @aria-atomic="true" and not(@hidden) and not(@aria-hidden) and not(contains(@class,"visually-hidden"))]')->length);
            self::assertStringContainsString('ช่องว่างที่วางจะล้างคะแนน', $guidance);
            self::assertStringContainsString('วางรูปแบบซ้ำให้เต็มช่วง', $guidance);
        } else {
            self::assertStringContainsString('อ่านอย่างเดียว', $guidance);
            self::assertStringNotContainsString('Ctrl+V', $guidance);
            self::assertSame(0, $x->query('//*[@id="gradebook-batch-status"]')->length);
        }
        self::assertSame(0, $x->query('//main/p[contains(.,"คลิกช่องคะแนนแล้วพิมพ์")]')->length);
    }

    public static function frozen(): array { return [['year'], ['offering']]; }

    public function testRosterOrderLiteralCodesNamesAndHistoricalIdentity(): void
    {
        $this->login();
        $model = $this->gradebook();
        $name = 'เด็กหญิง' . str_repeat('ชื่อไทยยาว', 12) . ' <script>hostile</script>';
        $model['rows'][0]['student_code'] = '0000123';
        $model['rows'][0]['display_name'] = $name;
        $html = View::render('gradebook/view', ['gradebook' => $model, 'canScore' => true,
            'canManageComponents' => false, 'csrfToken' => 'roster-test']);
        $x = $this->xpath($html);
        self::assertSame(['ลำดับ', 'เลขประจำตัว', 'ชื่อ-สกุล'], array_map(
            static fn ($n) => $n->textContent, iterator_to_array($x->query('//thead/tr/th[position()<=3]'))));
        self::assertSame(0, $x->query('//thead/tr/th[contains(.,"ประเภทแถว") or contains(.,"นักเรียน")]')->length);
        self::assertSame('คะแนนที่บันทึก', $x->evaluate('string(//thead/tr/th[last()])'));
        self::assertSame('0000123', $x->evaluate('string(//tbody/tr[1]/td[2])'));
        self::assertSame($name, $x->evaluate('string(//tbody/tr[1]/th)'));
        foreach ($x->query('//tbody/tr[@data-enrollment-id]') as $index => $row) {
            self::assertSame((string) ($index + 1), $x->evaluate('string(td[1])', $row));
            self::assertSame((string) $model['rows'][$index]['enrollment_id'], $row->getAttribute('data-enrollment-id'));
            self::assertSame($model['rows'][$index]['row_type'], $row->getAttribute('data-row-type'));
            self::assertSame($model['rows'][$index]['enrollment_status'], $row->getAttribute('data-enrollment-status'));
        }
        self::assertGreaterThan(0, $x->query('//tr[@data-row-type="HISTORICAL"]/th/span[contains(.,"แถวประวัติ อ่านอย่างเดียว")]')->length);
        self::assertSame(0, $x->query('//tr[@data-row-type="HISTORICAL"]//*[@data-grid-editable="true"]')->length);
        $data = json_decode($x->evaluate('string(//*[@id="gradebook-grid-data"])'), true, 16, JSON_THROW_ON_ERROR);
        self::assertSame('0000123', $data['rows'][0]['studentCode']);
        self::assertSame($name, $data['rows'][0]['name']);
        self::assertStringNotContainsString('<script>hostile</script>', $html);
    }

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
        self::assertSame(0, $x->query('//*[@id="gradebook-components"]')->length);
        self::assertSame(4, $x->query('//thead/tr/th')->length);
        self::assertSame('4', $x->evaluate('string(//tbody/tr/td/@colspan)'));
        self::assertStringContainsString('ยังไม่มีองค์ประกอบคะแนนที่เปิดใช้งาน', $html);
        self::assertStringContainsString('ไม่มีรายชื่อนักเรียน', $html);
        self::assertSame(1, $x->query('//nav[@aria-label="งานสมุดคะแนน"]//a[@href="' . $this->path() . '"]')->length);
        self::assertSame(0, $x->query('//nav[@aria-label="งานสมุดคะแนน"]//a[contains(@href,"workspaces")]')->length);
        self::assertStringContainsString(htmlspecialchars($hostile, ENT_QUOTES, 'UTF-8'), $html);
        self::assertStringNotContainsString($hostile, $html);
    }
}
