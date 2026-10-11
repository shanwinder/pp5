<?php
declare(strict_types=1);

use App\Support\{GradebookSummaryPresentation, View};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GradebookSummaryPresentationTest extends TestCase
{
    public static function examples(): array
    {
        return [['42.00', '50.00', '42/50'], ['42.50', '50.00', '42.5/50'],
            ['25.75', '35.50', '25.75/35.5'], ['0.00', '50.00', '0/50'],
            ['0.00', '0.00', '0/0'], ['100.00', '1000.00', '100/1000'],
            ['12345678901234567890.25', '12345678901234567890.50', '12345678901234567890.25/12345678901234567890.5']];
    }

    #[DataProvider('examples')]
    public function testExactProjectionAndFallbackOobParity(string $total, string $max, string $display): void
    {
        self::assertSame($display, GradebookSummaryPresentation::display($total, $max));
        $row = ['enrollment_id' => 7, 'entered_score_total' => $total, 'configured_max_total' => $max,
            'entered_component_count' => 0, 'active_component_count' => 2, 'complete' => false];
        foreach ([false, true] as $oob) {
            $html = View::render('gradebook/row-summary', ['offeringId' => 1, 'row' => $row, 'outOfBand' => $oob]);
            $doc = new DOMDocument();
            @$doc->loadHTML('<?xml encoding="UTF-8"><table><tr>' . $html . '</tr></table>');
            $x = new DOMXPath($doc);
            self::assertSame($display, $x->evaluate('string(//*[@id="row-1-7-display"])'));
            foreach (['total' => $total, 'max' => $max, 'count' => '0 / 2', 'complete' => 'ยังไม่ครบ'] as $suffix => $value) {
                self::assertSame($value, $x->evaluate('string(//*[@id="row-1-7-' . $suffix . '"])'));
                self::assertSame(1, $x->query('//*[@id="row-1-7-' . $suffix . '"]')->length);
                if ($oob) {
                    self::assertSame('outerHTML', $x->evaluate('string(//*[@id="row-1-7-' . $suffix . '"]/@hx-swap-oob)'));
                } else {
                    self::assertSame(1, $x->query('//td/span[@hidden and @aria-hidden="true"]//*[@id="row-1-7-' . $suffix . '"]')->length);
                }
            }
            self::assertSame($oob ? 0 : 1, $x->query('//td')->length);
        }
    }
}
