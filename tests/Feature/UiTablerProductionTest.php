<?php
declare(strict_types=1);

use App\Support\View;
use PHPUnit\Framework\TestCase;

final class UiTablerProductionTest extends TestCase
{
    public function testPinnedProductionAssetsAndNoDuplicateCssFoundation(): void
    {
        $root = dirname(__DIR__, 2) . '/htdocs/assets/';
        self::assertSame('370a2d4e608ae518351d10528fc7dbe19b354ae5062c51b2a9cb294850d65403', hash_file('sha256', $root.'vendor/tabler/tabler-1.6.1.min.css'));
        self::assertSame('4f88a82d13be3c5c63a12c5631eae914aa4381b6dc17641bf1ab85f3f8f6c8a5', hash_file('sha256', $root.'vendor/tabler/LICENSE'));
        self::assertSame('b740a1d46122672da62833e97f7e7c8a13fa85cbc7445b584b297cc00dde93db', hash_file('sha256', $root.'vendor/tabler-icons/LICENSE'));
        foreach (['books'=>'2233a0d65ee61671830faea76c341a6c655c433d89b76209bc0ac5abdd3b5657',
            'dots-vertical'=>'73e04f1b6340a6fe6b7b8305836bfafab84d2ec1e735554c9c196ea4bf709e67',
            'notebook'=>'c00d2c085c55edc9e517aa9329f6cdecdae990afc0a4fe20d3b001e500b5ab5f',
            'users'=>'ee1ec96e17087cbcb5663d84d2e5e1e46d56356c19d88bc42f8100154c1e298f'] as $name=>$sha) {
            self::assertSame($sha, hash_file('sha256', $root.'vendor/tabler-icons/'.$name.'.svg'));
        }
        $html = View::page('../../tests/Fixtures/views/page-content', ['message'=>'test']);
        self::assertStringContainsString('/assets/vendor/tabler/tabler-1.6.1.min.css', $html);
        self::assertStringNotContainsString('/assets/vendor/bootstrap-5.3.8.min.css', $html);
        self::assertStringContainsString('/assets/app-compat.css', $html);
    }

    public function testStudentRowActionHasNameHelpAndPermissionDrivenVisibleMenu(): void
    {
        $workspace = ['academicYear'=>['id'=>1,'year_be'=>2569,'status'=>'ACTIVE'],
            'gradeLevel'=>['id'=>2], 'classroom'=>['id'=>3,'name'=>'ห้องทดสอบ','status'=>'ACTIVE']];
        $student = ['code'=>'S01','name'=>'นักเรียนทดสอบ','status'=>'ACTIVE','studentId'=>4,'enrollmentId'=>5];
        $data = ['workspace'=>$workspace, 'students'=>[$student], 'canAdd'=>false, 'canImport'=>false, 'canManage'=>false, 'openYear'=>true];
        $readonly = $this->xpath(View::render('workspaces/classroom/students', $data));
        self::assertSame(1, $readonly->query('//details[@data-student-detail]/summary[@aria-label and @title and @data-tooltip]')->length);
        self::assertSame(1, $readonly->query('//details[@data-student-detail]/summary/svg[@aria-hidden="true" and @focusable="false"]')->length);
        self::assertSame(1, $readonly->query('//details[@data-student-detail]//a[contains(@href,"/students/") and contains(.,"ดูข้อมูลและประวัติ")]')->length);
        self::assertSame(0, $readonly->query('//details[@data-student-detail]//a[contains(@href,"/edit")]')->length);
        $data['canManage'] = true;
        $managed = $this->xpath(View::render('workspaces/classroom/students', $data));
        self::assertSame(2, $managed->query('//details[@data-student-detail]//a[contains(@href,"/edit") and normalize-space(.)!=""]')->length);
        $js = file_get_contents(dirname(__DIR__, 2).'/htdocs/assets/app.js');
        self::assertStringContainsString("event.key !== 'Escape'", $js);
        self::assertStringContainsString("detail.querySelector('summary').focus()", $js);
        self::assertStringContainsString("target.addEventListener('focus'", $js);
        self::assertStringContainsString("target.setAttribute('aria-describedby'", $js);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new DOMXPath($document);
    }
}
