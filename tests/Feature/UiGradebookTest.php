<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/Support/GradebookScoreFixtures.php';

final class UiGradebookTest extends TestCase
{
    use GradebookScoreFixtures;

    public static function modes(): array { return [['SCHOOL_ADMIN',true,true],['ACADEMIC_ADMIN',true,true],['SUBJECT_TEACHER',true,false],['EXECUTIVE',false,false]]; }
    #[DataProvider('modes')]
    public function testShellGridAndConditionalAssets(string $role,bool $score,bool $setup): void
    {
        $this->login($role); $r=$this->request('GET',$this->readPath()); self::assertSame(200,$r->status());
        $x=$this->assertPage($r->body()); $this->assertReadSafe($r->body());
        foreach (['/assets/vendor/htmx-2.0.8.min.js','/assets/gradebook.js'] as $src) {
            self::assertSame($score?1:0,$x->query('//script[@src="'.$src.'" and @defer]')->length);
        }
        self::assertSame($score?1:0,$x->query('//meta[@name="htmx-config"]')->length);
        self::assertSame($setup?1:0,$x->query('//main//a[@href="'.$this->path().'"]')->length);
        self::assertStringContainsString($score?'แก้ไขคะแนนได้':'อ่านอย่างเดียว',$r->body());
        self::assertSame(1,$x->query('//*[@class="pp5-table-scroll pp5-gradebook" and @role="region" and @aria-label and @tabindex="0"]')->length);
        self::assertSame(1,$x->query('//table/caption')->length);
        self::assertSame(4,$x->query('//tr[contains(@class,"pp5-historical")]')->length);
        self::assertSame(0,$x->query('//tr[contains(@class,"pp5-historical")]//input|//tr[contains(@class,"pp5-historical")]//*[@hx-post]')->length);
        foreach ($x->query('//input[@data-score-input]') as $input) {
            $eid=$input->getAttribute('data-enrollment-id'); $cid=$input->getAttribute('data-component-id');
            self::assertSame('/hx'.$this->readPath().'/components/'.$cid.'/enrollments/'.$eid.'/score',$input->getAttribute('hx-post'));
            self::assertSame('#gradebook-csrf',$input->getAttribute('hx-include'));
            self::assertSame('score,_token',$input->getAttribute('hx-params'));
            self::assertSame('blur',$input->getAttribute('hx-trigger'));
            self::assertFalse($input->hasAttribute('max')); self::assertFalse($input->hasAttribute('pattern'));
            foreach (explode(' ',$input->getAttribute('aria-labelledby')) as $id) { self::assertSame(1,$x->query('//*[@id="'.$id.'"]')->length); }
            self::assertStringContainsString('ทดสอบ',$x->evaluate('string(//*[@id="student-'.$eid.'"])'));
            self::assertSame(1,$x->query('//*[@id="'.$input->getAttribute('aria-describedby').'" and @role="status" and @aria-live="polite"]')->length);
        }
        if ($score) { self::assertSame($this->token(),$x->evaluate('string(//input[@id="gradebook-csrf"]/@value)')); self::assertSame(1,$x->query('//noscript')->length); }
        else { self::assertSame(0,$x->query('//main//input|//main//*[@hx-post]')->length); }
    }

    public function testSetupFormsAndNullHistoryRuleAreAccessibleAndStillBackendEnforced(): void
    {
        $this->login(); $this->score(null); $r=$this->request('GET',$this->path()); $x=$this->assertPage($r->body());
        self::assertStringContainsString('แม้ล้างคะแนนจนเป็นช่องว่าง',$r->body());
        self::assertStringContainsString('ไม่รวมในคะแนนรวมปัจจุบัน',$r->body());
        self::assertSame(0,$x->query('//script[contains(@src,"htmx") or contains(@src,"gradebook.js")]')->length);
        foreach (['create','update','status'] as $action) { self::assertSame(1,$x->query('//main//form[@method="post" and @action="'.$this->path($action).'"]')->length); }
        foreach ($x->query('//main//input[not(@type="hidden")]') as $input) {
            $id=$input->getAttribute('id'); self::assertNotSame('',$id);
            self::assertSame(1,$x->query('//*[@id="'.$id.'"]')->length); self::assertSame(1,$x->query('//label[@for="'.$id.'"]')->length);
        }
        foreach ($x->query('//form[@method="post"]') as $form) { self::assertSame($this->token(),$x->evaluate('string(.//input[@name="_token"]/@value)',$form)); }
        $before=$this->readSnapshot(); $r=$this->request('POST',$this->path('update'),$this->payload(['max_score'=>'25']));
        self::assertSame(422,$r->status()); $this->assertPage($r->body()); self::assertSame($before,$this->readSnapshot());
        self::assertStringContainsString('ไม่สามารถเปลี่ยนคะแนนเต็ม',$r->body());
    }

    public static function frozen(): array { return [['year'],['offering']]; }
    #[DataProvider('frozen')]
    public function testFrozenPagesHaveNoMutationControlsOrScoringAssets(string $kind): void
    {
        $this->login(); $this->changePrerequisite($kind);
        foreach ([$this->readPath(),$this->path()] as $path) {
            $r=$this->request('GET',$path); self::assertSame(200,$r->status()); $x=$this->assertPage($r->body());
            self::assertSame(0,$x->query('//main//form|//main//input|//script[contains(@src,"htmx") or contains(@src,"gradebook.js")]')->length);
        }
    }

    public function testSetupLinkFollowsLivePermissionAndTeacherScopeRevocationStillDenies(): void
    {
        $this->login(); self::assertStringContainsString('href="'.$this->path().'"',$this->request('GET',$this->readPath())->body());
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='SCHOOL_ADMIN' AND p.code='GRADEBOOK_COMPONENT_MANAGE'");
        self::assertStringNotContainsString('href="'.$this->path().'"',$this->request('GET',$this->readPath())->body());
        self::assertSame(403,$this->request('GET',$this->path())->status());
        self::assertSame(403,$this->request('POST',$this->path('update'),$this->payload())->status());
        $this->login('SUBJECT_TEACHER'); self::assertSame(200,$this->request('GET',$this->readPath())->status());
        self::assertSame(404,$this->request('GET',$this->readPath('Other'))->status()); $this->revoke('scope');
        self::assertSame(404,$this->request('GET',$this->readPath())->status());
        $before=$this->readSnapshot(); self::assertSame(422,$this->postCell('5')->status()); self::assertSame($before,$this->readSnapshot());
    }

    public function testBlankAndZeroSurviveFragmentsReloadAndServerCompleteness(): void
    {
        $this->login(); $id='score-'.$this->f['offeringA'].'-'.$this->f['componentA'].'-'.$this->f['enrollment_current'].'-input';
        $prefix='row-'.$this->f['offeringA'].'-'.$this->f['enrollment_current'].'-';
        self::assertSame('',$this->xpath($this->request('GET',$this->readPath())->body())->evaluate('string(//*[@id="'.$id.'"]/@value)'));
        foreach ([['0','0.00','1 / 2'],['','','0 / 2']] as [$typed,$value,$count]) {
            $r=$this->postCell($typed); self::assertSame(200,$r->status()); self::assertSame('1',(new ReflectionProperty($r,'headers'))->getValue($r)['X-Gradebook-Saved']); $this->assertFragment($r->body());
            foreach ([$r,$this->request('GET',$this->readPath())] as $response) {
                $x=$this->xpath($response->body()); self::assertSame($value,$x->evaluate('string(//*[@id="'.$id.'"]/@value)'));
                self::assertSame('0.00',$x->evaluate('string(//*[@id="'.$prefix.'total"])'));
                self::assertSame($count,$x->evaluate('string(//*[@id="'.$prefix.'count"])'));
                self::assertSame('ยังไม่ครบ',$x->evaluate('string(//*[@id="'.$prefix.'complete"])'));
            }
        }
    }

    public static function badScores(): array { return [['20.01'],['1.234'],['<script>bad</script>']]; }
    #[DataProvider('badScores')]
    public function testFailureFragmentsDoNotClaimSavedOrChangeServerState(string $value): void
    {
        $this->login(); $before=$this->readSnapshot(); $r=$this->postCell($value);
        self::assertSame(422,$r->status()); self::assertArrayNotHasKey('X-Gradebook-Saved',(new ReflectionProperty($r,'headers'))->getValue($r));
        $this->assertFragment($r->body()); self::assertStringContainsString('data-save-state="error"',$r->body());
        self::assertStringNotContainsString('บันทึกแล้ว',$r->body()); $this->assertReadSafe($r->body());
        self::assertSame($before,$this->readSnapshot());
    }

    public function testHostileNamesAreEscapedWithoutNewPii(): void
    {
        $this->login(); $hostile='ชื่อไทย<script>alert("x")</script>';
        foreach (['schools'=>'schoolA','classrooms'=>'roomA','subjects'=>'subjectA','gradebook_components'=>'componentA'] as $table=>$key) {
            $this->pdo->prepare("UPDATE {$table} SET name_th=? WHERE id=?")->execute([$hostile,$this->f[$key]]);
        }
        foreach ([$this->readPath(),$this->path()] as $path) {
            $r=$this->request('GET',$path); $this->assertPage($r->body()); $this->assertReadSafe($r->body());
            self::assertStringContainsString(htmlspecialchars($hostile,ENT_QUOTES,'UTF-8'),$r->body()); self::assertStringNotContainsString($hostile,$r->body());
        }
    }

    public function testSaveLoopRemainsPresentationOnlyAndOnlyEnterIsIntercepted(): void
    {
        $js=file_get_contents(dirname(__DIR__,2).'/htdocs/assets/gradebook.js');
        foreach (["event.key !== 'Enter'",'event.detail.xhr.status !== 200',"getResponseHeader('X-Gradebook-Saved') !== '1'",'event.detail.shouldSwap = false',"status(input, 'error', message)",'if (next) next.focus();','else input.blur();'] as $contract) { self::assertStringContainsString($contract,$js); }
        self::assertSame(1,preg_match_all('/event\.key\s*[!=]==?/',$js));
        self::assertDoesNotMatchRegularExpression('/fetch\s*\(|XMLHttpRequest|parseFloat|parseInt|\.reduce\s*\(|GRADEBOOK_|school_id|Math\./',$js);
    }

    private function postCell(string $value): App\Http\Response
    {
        return $this->request('POST','/hx'.$this->readPath().'/components/'.$this->f['componentA'].'/enrollments/'.$this->f['enrollment_current'].'/score',['_token'=>$this->token(),'score'=>$value]);
    }
    private function assertFragment(string $html): void
    {
        foreach (['<!doctype','<html','<head','<body','<main','pp5-shell','<script','style='] as $tag) { self::assertStringNotContainsString($tag,$html); }
    }
    private function assertPage(string $html): DOMXPath
    {
        $x=$this->xpath($html);
        foreach (['//html','//head','//body','//main','//h1','//*[@class="pp5-shell"]','//script[@src="/assets/app.js"]'] as $selector) { self::assertSame(1,$x->query($selector)->length,$selector); }
        self::assertSame(1,substr_count($html,'<!doctype html>'));
        self::assertSame(0,$x->query('//*[@style or @onclick]|//script[not(@src)]|//script[starts-with(@src,"http")]')->length);
        return $x;
    }
}
