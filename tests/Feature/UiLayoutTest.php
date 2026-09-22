<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;
use App\Http\Session;
use App\Support\Csrf;
use App\Support\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UiLayoutTest extends TestCase
{
    private const CONTENT = '../../tests/Fixtures/views/page-content';

    public static function layouts(): array
    {
        return [['app'], ['guest'], ['error']];
    }

    public function testRenderStillReturnsOnlyRawTemplateOutput(): void
    {
        $html = View::render(self::CONTENT, ['message' => 'เนื้อหา <safe>']);

        self::assertSame('<p data-page-content>เนื้อหา &lt;safe&gt;</p>' . "\n", $html);
        $this->assertFragment($html);
    }

    #[DataProvider('layouts')]
    public function testPageRendersExactlyOneDocumentAndPreservesContent(string $layout): void
    {
        $html = View::page(self::CONTENT, ['message' => 'เนื้อหา'], [
            'layout' => $layout, 'documentTitle' => 'ปพ.5', 'pageTitle' => 'หน้าทดสอบ',
        ]);

        $this->assertDocument($html);
        self::assertStringContainsString('<title>ปพ.5</title>', $html);
        self::assertStringContainsString('<h1>หน้าทดสอบ</h1>', $html);
        self::assertStringContainsString(View::render(self::CONTENT, ['message' => 'เนื้อหา']), $html);
    }

    #[DataProvider('layouts')]
    public function testDynamicTitlesAndBodyClassAreEscaped(string $layout): void
    {
        $hostile = '\'"></title><script>alert("x")</script>&';
        $escaped = htmlspecialchars($hostile, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = View::page(self::CONTENT, ['message' => $hostile], [
            'layout' => $layout, 'documentTitle' => $hostile, 'pageTitle' => $hostile, 'bodyClass' => $hostile,
        ]);

        self::assertStringContainsString('<title>' . $escaped . '</title>', $html);
        self::assertStringContainsString('<h1>' . $escaped . '</h1>', $html);
        self::assertStringContainsString('<body class="' . $escaped . '">', $html);
        self::assertStringNotContainsString($hostile, $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    #[DataProvider('layouts')]
    public function testTrustedAssetSlotsAreOptionalAndStayInTheirRegions(string $layout): void
    {
        $head = '<meta name="htmx-config" content="{}">';
        $scripts = '<script src="/assets/gradebook.js" defer></script>';
        $html = View::page(self::CONTENT, ['message' => 'content'], [
            'layout' => $layout, 'headAssets' => $head, 'scripts' => $scripts,
        ]);

        self::assertMatchesRegularExpression('~<head>.*' . preg_quote($head, '~') . '.*</head>~s', $html);
        self::assertMatchesRegularExpression('~</main>\s*' . preg_quote($scripts, '~') . '\s*</body>~s', $html);
        self::assertSame(1, substr_count($html, $head));
        self::assertSame(1, substr_count($html, $scripts));

        $plain = View::page(self::CONTENT, ['message' => 'content'], ['layout' => $layout]);
        self::assertSame($layout === 'app' ? 1 : 0, substr_count($plain, '<script'));
        if ($layout === 'app') {
            self::assertStringContainsString('<script src="/assets/app.js" defer></script>', $plain);
        }
        self::assertStringNotContainsString('htmx', $plain);
        self::assertStringNotContainsString('<h1>', $plain);
    }

    public function testContextCannotReplaceRenderedContentAndCallsDoNotShareState(): void
    {
        $html = View::page(self::CONTENT, [
            'message' => 'actual content', 'documentTitle' => 'data title', 'scripts' => 'data scripts',
        ], ['documentTitle' => 'context title', 'content' => 'forged content', 'bodyClass' => 'first-page']);

        self::assertStringContainsString('<title>context title</title>', $html);
        self::assertStringContainsString('<p data-page-content>actual content</p>', $html);
        foreach (['forged content', 'data title', 'data scripts'] as $unexpected) {
            self::assertStringNotContainsString($unexpected, $html);
        }

        $next = View::page(self::CONTENT, ['message' => 'next']);
        self::assertStringContainsString('<title>ปพ.5</title>', $next);
        self::assertStringNotContainsString('context title', $next);
        self::assertStringNotContainsString('first-page', $next);
    }

    public static function missingViews(): array
    {
        return [
            'content' => ['missing/private-content', []],
            'layout' => [self::CONTENT, ['layout' => 'missing-private-layout']],
        ];
    }

    #[DataProvider('missingViews')]
    public function testMissingViewThrowsWithoutLeakingBufferedOutput(string $template, array $context): void
    {
        $level = ob_get_level();
        ob_start();
        try {
            try {
                View::page($template, ['message' => 'PRIVATE CONTENT'], $context);
                self::fail('Expected the existing missing-view exception contract.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('View not found:', $exception->getMessage());
                self::assertSame($level + 1, ob_get_level());
                self::assertSame('', ob_get_contents());
            }
        } finally {
            ob_end_clean();
        }
    }

    #[DataProvider('missingViews')]
    public function testMissingViewUsesExistingApplicationSafeErrorBoundary(string $template, array $context): void
    {
        // Inject a rendering failure during dispatch to exercise the real catch boundary,
        // without adding test routes or changing application dependency wiring.
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('prepare')->willReturnCallback(
            static fn () => View::page($template, ['message' => 'PRIVATE CONTENT'], $context)
        );
        $sessionBefore = $_SESSION ?? [];
        try {
            $response = (new Application($pdo))->handle(new Request('POST', '/login', [], [
                'username' => 'test', 'password' => 'test', '_token' => (new Csrf())->token(new Session()),
            ], []));
        } finally {
            $_SESSION = $sessionBefore;
        }

        self::assertSame(500, $response->status());
        self::assertSame('Internal Server Error', $response->body());
    }

    public static function gradebookFragments(): array
    {
        return [
            'blank score' => ['gradebook/score-cell', ['offeringId' => 1, 'componentId' => 2, 'enrollmentId' => 3, 'score' => null], 'value=""'],
            'zero score' => ['gradebook/score-cell', ['offeringId' => 1, 'componentId' => 2, 'enrollmentId' => 3, 'score' => '0.00', 'saved' => true], 'value="0.00"'],
            'summary' => ['gradebook/row-summary', ['offeringId' => 1, 'outOfBand' => true, 'row' => [
                'enrollment_id' => 3, 'entered_score_total' => '0.00', 'configured_max_total' => '20.00',
                'entered_component_count' => 1, 'active_component_count' => 2, 'complete' => false,
            ]], 'hx-swap-oob="outerHTML"'],
            'error' => ['gradebook/score-error', ['message' => '<private>'], '&lt;private&gt;'],
        ];
    }

    #[DataProvider('gradebookFragments')]
    public function testGradebookRenderRemainsFragmentOnly(string $template, array $data, string $expected): void
    {
        $html = View::render($template, $data);
        self::assertStringContainsString($expected, $html);
        $this->assertFragment($html);
    }

    public function testRenderingWorksWithPdoDisabledAndNoApplicationDependencies(): void
    {
        $code = 'require ' . var_export(dirname(__DIR__, 2) . '/htdocs/app/Support/View.php', true) . ';'
            . 'if (class_exists("PDO") && get_class_methods("PDO") !== []) { exit(2); }'
            . 'set_error_handler(static function ($severity, $message) { throw new RuntimeException($message); });'
            . 'foreach (["app", "guest", "error"] as $layout) {'
            . 'echo App\\Support\\View::page(' . var_export(self::CONTENT, true)
            . ', ["message" => "no database"], ["layout" => $layout]); }';
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -n -d disable_classes=PDO -r ' . escapeshellarg($code) . ' 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
        self::assertSame(3, substr_count(implode("\n", $output), '<!doctype html>'));
    }

    public function testExistingErrorsKeepSafeContent(): void
    {
        $hostile = '<script>PRIVATE RESOURCE /config/local.php SQLSTATE</script>';
        foreach (['403', '404'] as $status) {
            $html = View::render('errors/' . $status, ['message' => $hostile, 'path' => $hostile]);
            self::assertStringNotContainsString($hostile, $html);
            self::assertStringNotContainsString('PRIVATE RESOURCE', $html);
            $this->assertDocument($html);
        }
        $response = (new Application())->handle(new Request('GET', '/' . $hostile, [], [], []));
        self::assertSame(404, $response->status());
        self::assertSame(View::render('errors/404'), $response->body());
    }

    private function assertDocument(string $html): void
    {
        self::assertSame(1, substr_count(strtolower($html), '<!doctype html>'));
        foreach (['html', 'head', 'body', 'main'] as $tag) {
            self::assertSame(1, preg_match_all('~<' . $tag . '\b[^>]*>~i', $html));
            self::assertSame(1, substr_count($html, '</' . $tag . '>'));
        }
        self::assertStringContainsString('<html lang="th">', $html);
        self::assertStringContainsString('<meta charset="utf-8">', $html);
        self::assertStringContainsString('name="viewport"', $html);
    }

    private function assertFragment(string $html): void
    {
        self::assertDoesNotMatchRegularExpression('~<!doctype|<(?:html|head|body|main|nav)\b~i', $html);
        self::assertStringNotContainsString('app-shell', $html);
    }
}
