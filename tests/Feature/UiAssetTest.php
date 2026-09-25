<?php
declare(strict_types=1);

use App\Support\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UiAssetTest extends TestCase
{
    private const BOOTSTRAP = '/assets/vendor/bootstrap-5.3.8.min.css';

    public function testPinnedBootstrapAndMatchingLicenseAreVendoredLocally(): void
    {
        $css = $this->asset(self::BOOTSTRAP);
        self::assertMatchesRegularExpression('/Bootstrap\s+v5\.3\.8\b/', substr($css, 0, 250));
        self::assertSame('d85327d99c7a3ee1f9b5d0500d1370acea3ad2db39c163c2f51f232baedbdede', hash('sha256', $css));
        self::assertStringContainsString('.btn', $css);
        self::assertStringContainsString('.table', $css);
        $license = $this->asset('/assets/vendor/bootstrap-LICENSE.txt');
        self::assertStringContainsString('The MIT License (MIT)', $license);
        self::assertStringContainsString('2011-2025 The Bootstrap Authors', $license);
        self::assertStringContainsString('Permission is hereby granted', $license);
        self::assertSame('4620c84ad5ce8602ff65640ed6b7c8b78ebb9e036584f0ebc1ccc88206a4bb51', hash('sha256', $license));
    }

    public static function layouts(): array
    {
        return [['app', ['/assets/app.js']], ['guest', []], ['error', []]];
    }

    #[DataProvider('layouts')]
    public function testLayoutsLoadLocalCssInOrderAndOnlyIntendedScripts(string $layout, array $scripts): void
    {
        $html = View::page('../../tests/Fixtures/views/page-content', ['message' => 'ทดสอบ'], ['layout' => $layout]);
        $document = new DOMDocument();
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $styles = $xpath->query('//head/link[@rel="stylesheet"]/@href');
        self::assertSame([self::BOOTSTRAP, '/assets/app.css'], array_map(
            static fn ($node) => $node->nodeValue, iterator_to_array($styles)
        ));
        self::assertSame($scripts, array_map(static fn ($node) => $node->nodeValue,
            iterator_to_array($xpath->query('//script/@src'))));
        self::assertSame(count($scripts), $xpath->query('//script[@src and @defer]')->length);
        foreach ($xpath->query('//link[@rel="stylesheet"]/@href | //script/@src') as $asset) {
            self::assertMatchesRegularExpression('~^/assets/[a-zA-Z0-9./-]+$~', $asset->nodeValue);
            self::assertFileExists(dirname(__DIR__, 2) . '/htdocs' . $asset->nodeValue);
        }
        self::assertSame(1, $xpath->query('//head/meta[@name="viewport" and @content="width=device-width, initial-scale=1"]')->length);
        self::assertSame(0, $xpath->query('//*[@style or @onclick]')->length);
        self::assertStringNotContainsString('htmx', $html);
        self::assertStringNotContainsString('gradebook.js', $html);
        self::assertDoesNotMatchRegularExpression('~(?:src|href)=["\'](?:https?:)?//~', $html);
    }

    public function testStylesDefineSemanticColorsAndLayoutTokens(): void
    {
        $css = $this->asset('/assets/app.css');
        foreach (['background', 'surface', 'surface-muted', 'text', 'text-muted', 'border', 'primary',
            'primary-hover', 'success', 'warning', 'danger', 'info', 'focus', 'space-1', 'space-2',
            'space-3', 'space-4', 'space-6', 'radius-sm', 'radius-md', 'shadow-sm', 'sidebar-width',
            'header-height', 'content-width', 'table-cell-padding', 'font-family'] as $token) {
            self::assertMatchesRegularExpression('/--pp5-' . $token . '\s*:\s*[^;}]+[;}]/', $css);
        }
        foreach (['system-ui', 'Tahoma', '.pp5-shell', '.pp5-sidebar', '.pp5-topbar', '.pp5-skip-link',
            '.pp5-page-header', '.breadcrumb', '.btn-primary', '.btn-secondary', '.btn-danger', '.form-control',
            '.pp5-field', '.pp5-alert', '.pp5-badge', '.pp5-table', '.pp5-empty-state', '.pp5-filter-bar',
            '.pp5-gradebook', ':focus-visible', 'prefers-reduced-motion', 'overflow-x: auto', '@media'] as $hook) {
            self::assertStringContainsString($hook, $css);
        }
        self::assertDoesNotMatchRegularExpression('~@import|@font-face|https?://~i', $css);
    }

    public function testSemanticTextColorsHaveAccessibleContrast(): void
    {
        $css = $this->asset('/assets/app.css');
        preg_match_all('/--pp5-([a-z-]+):\s*(#[0-9a-f]{6})\s*;/i', $css, $matches, PREG_SET_ORDER);
        $colors = array_column($matches, 2, 1);
        foreach ([['text', 'surface'], ['text', 'background'], ['text-muted', 'surface'],
            ['text-muted', 'surface-muted'], ['on-primary', 'primary'], ['on-primary', 'primary-hover'],
            ['success', 'success-bg'], ['warning', 'warning-bg'], ['danger', 'danger-bg'], ['info', 'info-bg']] as [$fg, $bg]) {
            self::assertArrayHasKey($fg, $colors);
            self::assertArrayHasKey($bg, $colors);
            $a = $this->luminance($colors[$fg]);
            $b = $this->luminance($colors[$bg]);
            self::assertGreaterThanOrEqual(4.5, (max($a, $b) + 0.05) / (min($a, $b) + 0.05), "$fg on $bg");
        }
        foreach (['surface', 'background'] as $background) {
            $a = $this->luminance($colors['focus']);
            $b = $this->luminance($colors[$background]);
            self::assertGreaterThanOrEqual(3, (max($a, $b) + 0.05) / (min($a, $b) + 0.05));
        }
        self::assertArrayHasKey('control-border', $colors);
        $a = $this->luminance($colors['control-border']);
        $b = $this->luminance($colors['surface']);
        self::assertGreaterThanOrEqual(3, (max($a, $b) + 0.05) / (min($a, $b) + 0.05));
    }

    public function testShellScriptHasNoNetworkOrBusinessResponsibilities(): void
    {
        $js = $this->asset('/assets/app.js');
        self::assertLessThan(6000, strlen($js));
        self::assertDoesNotMatchRegularExpression(
            '~\b(?:fetch|XMLHttpRequest|WebSocket|EventSource|sendBeacon|htmx|school_id|tenant|permission|role|score|gradebook|calculate)\b~i', $js
        );
        foreach (['data-nav-toggle', 'aria-controls', 'aria-expanded', 'Escape', '.focus()', 'matchMedia'] as $contract) {
            self::assertStringContainsString($contract, $js);
        }
        self::assertStringNotContainsString('innerHTML', $js);
    }

    public function testCssDoesNotFetchExternalResources(): void
    {
        foreach ([self::BOOTSTRAP, '/assets/app.css'] as $asset) {
            $css = preg_replace('~/\*.*?\*/~s', '', $this->asset($asset));
            self::assertDoesNotMatchRegularExpression('~@import|@font-face|url\(\s*["\']?(?:https?:)?//~i', $css);
        }
    }

    private function asset(string $path): string
    {
        $file = dirname(__DIR__, 2) . '/htdocs' . $path;
        self::assertFileExists($file);
        return file_get_contents($file);
    }

    private function luminance(string $color): float
    {
        $channels = array_map(static function ($hex): float {
            $value = hexdec($hex) / 255;
            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($color, 1), 2));
        return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
    }
}
