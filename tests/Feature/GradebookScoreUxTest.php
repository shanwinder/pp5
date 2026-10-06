<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookScoreFixtures.php';

final class GradebookScoreUxTest extends TestCase
{
    use GradebookScoreFixtures;

    #[DataProvider('matrixReaders')]
    public function testScoreMatrixMetadataIsOrderedAndExcludesIdentityAndSummaries(string $role, bool $editable): void
    {
        $this->login($role);
        $r = $this->request('GET', $this->readPath());
        self::assertSame(200, $r->status());
        $x = $this->xpath($r->body());
        $headers = $x->query('//thead//th[@data-component-id]');
        $rows = $x->query('//tbody/tr[@data-enrollment-id]');
        self::assertGreaterThan(0, $headers->length);
        self::assertGreaterThan(0, $rows->length);
        foreach ($rows as $rowIndex => $row) {
            $cells = $x->query('./td[@data-grid-score-cell]', $row);
            self::assertSame($headers->length, $cells->length);
            self::assertSame(0, $x->query('./th[@data-grid-score-cell]|./td[contains(@class,"pp5-gradebook-summary") and @data-grid-score-cell]', $row)->length);
            foreach ($cells as $columnIndex => $cell) {
                self::assertSame((string) $rowIndex, $cell->getAttribute('data-grid-row'));
                self::assertSame((string) $columnIndex, $cell->getAttribute('data-grid-column'));
                self::assertSame($headers->item($columnIndex)->getAttribute('data-component-id'), $cell->getAttribute('data-component-id'));
                self::assertSame($row->getAttribute('data-enrollment-id'), $cell->getAttribute('data-enrollment-id'));
                $canEditCell = $editable && str_contains($row->getAttribute('class'), 'pp5-current');
                self::assertSame($canEditCell ? 'true' : 'false', $cell->getAttribute('data-grid-editable'));
                self::assertSame($canEditCell ? 1 : 0, $x->query('.//input[@data-score-input]', $cell)->length);
                self::assertSame($canEditCell ? 0 : 1, $x->query('.//span[@data-grid-value]', $cell)->length);
                self::assertFalse($cell->hasAttribute('data-student-code'));
                self::assertFalse($cell->hasAttribute('data-national-id'));
            }
        }
    }

    public static function matrixReaders(): array { return [['SCHOOL_ADMIN', true], ['EXECUTIVE', false]]; }

    public static function readers(): array { return [['SCHOOL_ADMIN',8], ['ACADEMIC_ADMIN',8], ['SUBJECT_TEACHER',8], ['EXECUTIVE',0], ['VIEWER',0], ['HOMEROOM_TEACHER',0]]; }
    #[DataProvider('readers')]
    public function testOnlyAuthorizedCurrentRowsHaveAccessibleInputs(string $role, int $count): void
    {
        $this->login($role); $r = $this->request('GET', $this->readPath());
        self::assertSame(in_array($role, ['VIEWER','HOMEROOM_TEACHER'], true) ? 404 : 200, $r->status());
        $x = $this->xpath($r->body()); self::assertSame($count, $x->query('//input[@hx-post]')->length);
        foreach (['moved','exited','nullHistory','oldHistory'] as $key) {
            self::assertSame(0, $x->query('//tr[@data-enrollment-id="' . $this->f['enrollment_' . $key] . '"]//input')->length);
        }
        foreach ($x->query('//input[@hx-post]') as $input) {
            self::assertSame('text', $input->getAttribute('type')); self::assertSame('decimal', $input->getAttribute('inputmode'));
            self::assertSame('blur', $input->getAttribute('hx-trigger')); self::assertSame('score,_token', $input->getAttribute('hx-params'));
            self::assertSame('closest table:queue all', $input->getAttribute('hx-sync'));
            self::assertSame('outerHTML', $input->getAttribute('hx-swap'));
            self::assertSame((string) $this->f['offeringA'], $input->getAttribute('data-offering-id'));
            self::assertNotSame('', $input->getAttribute('data-enrollment-id')); self::assertNotSame('', $input->getAttribute('data-component-id'));
            self::assertNotSame('', $input->getAttribute('id')); self::assertNotSame('', $input->getAttribute('aria-labelledby'));
            self::assertFalse($input->hasAttribute('data-school-id')); self::assertFalse($input->hasAttribute('data-user-id'));
            self::assertSame(1, $x->query('//*[@id="' . $input->getAttribute('aria-describedby') . '" and @role="status"]')->length);
        }
        if ($count > 0) {
            self::assertSame($this->token(), $x->evaluate('string(//input[@id="gradebook-csrf"]/@value)'));
            self::assertSame(4, $x->query('//script[@src and @defer]')->length);
        }
    }

    public static function immutableStates(): array { return [['year'], ['offering'], ['component']]; }
    #[DataProvider('immutableStates')]
    public function testClosedYearInactiveOfferingAndInactiveColumnsAreReadonly(string $state): void
    {
        $this->login(); $this->changePrerequisite($state); $r = $this->request('GET', $this->readPath()); self::assertSame(200, $r->status());
        $x = $this->xpath($r->body());
        if ($state === 'component') { self::assertSame(0, $x->query('//input[@data-component-id="' . $this->f['componentA'] . '"]')->length); }
        else { self::assertSame(0, $x->query('//input[@hx-post]')->length); self::assertStringContainsString('อ่านอย่างเดียว', $r->body()); }
    }

    public function testEditabilityFollowsLivePermissionMetadataNotRoleName(): void
    {
        $this->login('SUBJECT_TEACHER'); $this->revokeScore('permission');
        $r = $this->request('GET', $this->readPath()); self::assertSame(200, $r->status());
        self::assertSame(0, $this->xpath($r->body())->query('//input[@hx-post]')->length);
        $this->pdo->exec("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='EXECUTIVE' AND p.code='GRADEBOOK_SCORE_ENTER'");
        $this->login('EXECUTIVE'); $r = $this->request('GET', $this->readPath());
        self::assertSame(8, $this->xpath($r->body())->query('//input[@hx-post]')->length);
    }

    public function testAutosaveAssetsAndActiveCellNavigationRemainPinnedAndLocal(): void
    {
        $this->login();
        $r = $this->request('GET', $this->readPath());
        self::assertSame(200, $r->status());

        $x = $this->xpath($r->body());
        $sources = [];
        foreach ($x->query('//script[@src]') as $script) {
            $sources[] = $script->getAttribute('src');
        }
        self::assertSame([
            '/assets/app.js?v='.filemtime(dirname(__DIR__,2).'/htdocs/assets/app.js'),
            '/assets/vendor/htmx-2.0.8.min.js',
            '/assets/vendor/tabulator/tabulator.min.js',
            '/assets/gradebook.js?v='.filemtime(dirname(__DIR__,2).'/htdocs/assets/gradebook.js'),
        ], $sources);

        foreach ($sources as $source) {
            self::assertFalse(str_starts_with($source, 'http://'));
            self::assertFalse(str_starts_with($source, 'https://'));
        }

        $path = dirname(__DIR__, 2) . '/htdocs/assets/gradebook.js';
        self::assertFileExists($path);
        $js = file_get_contents($path);
        self::assertIsString($js);

        foreach ([
            "event.key === 'Enter'",
            "event.key === 'ArrowUp'",
            "event.key === 'ArrowDown'",
            'new Tabulator(',
            'const componentByField = new Map(',
            'const authoritative = new Map(',
            'state.pendingSingles',
            'state.singleQueue',
            'event.isComposing',
            'event.preventDefault();',
            'element.focus({ preventScroll: true });',
            "response.headers.get('X-Gradebook-Saved') !== '1'",
            "response.headers.get('X-Gradebook-Batch-Saved') !== '1'",
            'clipboard: false',
        ] as $contract) {
            self::assertStringContainsString($contract, $js);
        }
        self::assertStringContainsString("event.key === 'Tab'", $js);
        self::assertStringContainsString("state.editIntent = 'direct'", $js);
        self::assertStringContainsString("state.editIntent = 'explicit'", $js);
        self::assertStringContainsString("intent === 'direct' && event.key === 'ArrowLeft'", $js);
        self::assertStringContainsString("intent === 'direct' && event.key === 'ArrowRight'", $js);
    }
}
