<?php
declare(strict_types=1);

use App\Support\View;
use PHPUnit\Framework\TestCase;

final class UiConfirmationTest extends TestCase
{
    private const MESSAGE = 'ยืนยันการเปลี่ยนสถานะโรงเรียนหรือไม่? การระงับหรือปิดใช้งานโรงเรียนจะหยุดการเข้าถึงข้อมูลในบริบทโรงเรียนนั้น';

    public function testSchoolStatusConfirmationIsStaticAndRetainsNativePostContract(): void
    {
        $html = View::render('system/schools/index', [
            'permissions'=>['SYSTEM_SCHOOL_CREATE'=>true], 'canChangeStatus'=>true,
            'error'=>null, 'csrfToken'=>'fixture-token',
            'schools'=>[['id'=>7, 'school_code'=>'TEST', 'name_th'=>'<script>hostile</script>', 'status'=>'ACTIVE']],
        ]);
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($document);
        $forms = $xpath->query('//form');
        self::assertCount(1, $forms);
        $form = $forms->item(0);
        self::assertSame(self::MESSAGE, $form->getAttribute('data-confirm'));
        self::assertSame('post', $form->getAttribute('method'));
        self::assertSame('/system/schools/7/status', $form->getAttribute('action'));
        self::assertSame('fixture-token', $xpath->evaluate('string(.//input[@name="_token"]/@value)', $form));
        self::assertSame(['ACTIVE', 'SUSPENDED', 'INACTIVE'], array_map(
            static fn ($node) => $node->nodeValue, iterator_to_array($xpath->query('.//select[@name="status"]/option/@value', $form))
        ));
        self::assertSame(1, $xpath->query('.//button[@type="submit" and not(@disabled)]', $form)->length);
        self::assertSame(0, $xpath->query('//script|//*[@onsubmit or @onclick]')->length);
    }

    public function testOrdinaryCreateFormDoesNotOptIn(): void
    {
        $html = View::render('system/schools/create', [
            'permissions'=>['SYSTEM_SCHOOL_VIEW'=>true], 'error'=>null, 'csrfToken'=>'fixture-token', 'values'=>[],
        ]);
        self::assertStringContainsString('method="post" action="/system/schools"', $html);
        self::assertStringNotContainsString('data-confirm', $html);
    }

    public function testGenericConfirmationEnhancementIsPresentationOnly(): void
    {
        $js = file_get_contents(dirname(__DIR__, 2).'/htdocs/assets/app.js');
        foreach (['form[data-confirm]', "getAttribute('data-confirm')", "addEventListener('submit'", 'window.confirm(', 'event.preventDefault()'] as $contract) {
            self::assertStringContainsString($contract, $js);
        }
        self::assertDoesNotMatchRegularExpression(
            '~\b(?:fetch|XMLHttpRequest|WebSocket|EventSource|sendBeacon|htmx|school_id|tenant|permission|role|score|gradebook|calculate|status|FormData)\b|innerHTML|btn-danger|\.submit\(|\.requestSubmit\(~i', $js
        );
    }
}
