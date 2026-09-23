<?php
declare(strict_types=1);

use App\Support\StatusLabel;
use App\Support\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookReadFixtures.php';

final class UiAdministrationTest extends TestCase
{
    use GradebookReadFixtures;

    public static function pages(): iterable
    {
        yield ['SYSTEM_ADMIN', '/system/schools', null];
        yield ['SYSTEM_ADMIN', '/system/schools/create', null];
        yield ['SCHOOL_ADMIN', '/admin/users', null];
        yield ['SCHOOL_ADMIN', '/admin/users/create', null];
        yield ['SCHOOL_ADMIN', '/admin/users/{user}/edit', null];
        foreach (['years'=>'yearA', 'classrooms'=>'roomA', 'subjects'=>'subjectA', 'offerings'=>'offeringA'] as $area=>$key) {
            yield ['SCHOOL_ADMIN', '/academic/'.$area, null];
            yield ['SCHOOL_ADMIN', '/academic/'.$area.'/create', 'yearA'];
            yield ['SCHOOL_ADMIN', '/academic/'.$area.'/{'.$key.'}/edit', null];
        }
        yield ['SCHOOL_ADMIN', '/academic/teaching-assignments', 'yearA'];
    }

    #[DataProvider('pages')]
    public function testAllMigratedPagesHaveOneShellLabelsLocalAssetsAndEscapedData(string $role, string $path, ?string $year): void
    {
        $this->login($role);
        $hostile = str_repeat('ชื่อไทย', 8).'<script>alert("admin")</script>';
        $_SESSION['display_name'] = $hostile;
        foreach (['schools'=>'schoolA', 'subjects'=>'subjectA', 'classrooms'=>'roomA'] as $table=>$key) {
            $this->pdo->prepare("UPDATE {$table} SET name_th=? WHERE id=?")->execute([$hostile,$this->f[$key]]);
        }
        $this->pdo->prepare('UPDATE users SET display_name=? WHERE id=?')->execute([$hostile,$this->users['VIEWER']['user']]);
        $path = $this->resolve($path);
        $r = $this->request('GET', $path, [], $year ? ['academic_year_id'=>(string)$this->f[$year]] : []);
        self::assertSame(200, $r->status(), $path);
        $x = $this->assertPage($r->body());
        if ($path !== '/system/schools') { self::assertSame(0, $x->query('//form[@data-confirm]')->length, $path); }
        self::assertStringContainsString(htmlspecialchars($hostile,ENT_QUOTES,'UTF-8'),$r->body());
        self::assertStringNotContainsString($hostile,$r->body());
        if ($role === 'SYSTEM_ADMIN') { self::assertSame(0,$x->query('//nav[@aria-label="เมนูหลัก"]//a[starts-with(@href,"/academic") or @href="/dashboard" or @href="/gradebooks"]')->length); }
        else { $this->assertReadSafe($r->body()); }
        foreach ($x->query('//main//input[not(@type="hidden")]|//main//select|//main//textarea') as $control) {
            $id = $control->getAttribute('id');
            self::assertNotSame('', $id, $path.' '.$control->getAttribute('name'));
            self::assertSame(1,$x->query('//label[@for="'.$id.'"]')->length,$id);
            self::assertSame(1,$x->query('//*[@id="'.$id.'"]')->length,$id.' unique');
        }
        foreach ($x->query('//main//table') as $table) {
            self::assertStringContainsString('pp5-table', $table->getAttribute('class'));
            self::assertSame(1,$x->query('ancestor::*[contains(@class,"pp5-table-scroll") and @role="region" and @aria-label and @tabindex="0"]',$table)->length);
            self::assertSame(0,$x->query('.//thead//th[not(@scope="col")]',$table)->length);
            self::assertGreaterThan(0,$x->query('.//tbody//th[@scope="row"]',$table)->length);
        }
        foreach ($x->query('//form[@method="post"]') as $form) { self::assertSame($this->token(),$x->evaluate('string(.//input[@name="_token"]/@value)',$form)); }
        self::assertSame(0,$x->query('//input[@type="password" and @value]')->length);
    }

    public static function listPermissions(): iterable
    {
        yield ['SYSTEM_ADMIN','SYSTEM_SCHOOL_CREATE','/system/schools','/system/schools/create'];
        yield ['SCHOOL_ADMIN','SCHOOL_USER_CREATE','/admin/users','/admin/users/create'];
        foreach (['years'=>'ACADEMIC_YEAR_MANAGE','classrooms'=>'CLASSROOM_MANAGE','subjects'=>'SUBJECT_MANAGE','offerings'=>'SUBJECT_OFFERING_MANAGE'] as $area=>$code) {
            yield ['SCHOOL_ADMIN',$code,'/academic/'.$area,'/academic/'.$area.'/create'];
        }
    }

    #[DataProvider('listPermissions')]
    public function testListCreateAndEditActionsFollowLivePermissions(string $role,string $code,string $path,string $create): void
    {
        $this->login($role);
        self::assertSame(1,$this->xpath($this->request('GET',$path)->body())->query('//main//a[@href="'.$create.'"]')->length);
        $this->removePermission($role,$code);
        $body=$this->request('GET',$path)->body();
        self::assertSame(0,$this->xpath($body)->query('//main//a[@href="'.$create.'"]')->length);
        if (str_starts_with($path,'/academic/')) { self::assertSame(0,$this->xpath($body)->query('//main//a[contains(@href,"/edit")]')->length); }
        self::assertSame(403,$this->request('GET',$create)->status());
    }

    public static function userActions(): iterable
    {
        yield ['SCHOOL_USER_UPDATE','profile'];
        yield ['SCHOOL_MEMBERSHIP_STATUS_MANAGE','membership-status'];
        yield ['SCHOOL_ROLE_MANAGE','roles'];
        yield ['SCHOOL_PASSWORD_RESET','reset-password'];
    }

    #[DataProvider('userActions')]
    public function testUserActionsAreIndependentAndRevocationHidesOnlyThatForm(string $code,string $action): void
    {
        $this->login();
        $base='/admin/users/'.$this->users['VIEWER']['user'];
        $body=$this->request('GET',$base.'/edit')->body();
        foreach (['ข้อมูลผู้ใช้','สถานะสมาชิกโรงเรียน','บทบาท/สิทธิ์ที่กำหนด','รีเซ็ตรหัสผ่าน'] as $heading) { self::assertStringContainsString($heading,$body); }
        self::assertSame(4,$this->xpath($body)->query('//main//form[@method="post"]')->length);
        $this->removePermission('SCHOOL_ADMIN',$code);
        $body=$this->request('GET',$base.'/edit')->body();
        self::assertSame(0,$this->xpath($body)->query('//main//form[@action="'.$base.'/'.$action.'"]')->length);
        self::assertSame(3,$this->xpath($body)->query('//main//form[@method="post"]')->length);
        self::assertSame(403,$this->request('POST',$base.'/'.$action,['_token'=>$this->token()])->status());
    }

    public function testViewOnlyUserCannotSeeMutationActions(): void
    {
        $this->login('VIEWER');
        $this->pdo->exec("INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code IN ('SCHOOL_USER_VIEW','ACADEMIC_SETUP_VIEW')");
        foreach (['/admin/users/'.$this->users['SUBJECT_TEACHER']['user'].'/edit','/admin/users','/academic/classrooms','/academic/subjects','/academic/offerings'] as $path) {
            $r=$this->request('GET',$path); self::assertSame(200,$r->status());
            self::assertSame(0,$this->xpath($r->body())->query('//main//form[@method="post"]|//main//a[contains(@href,"/create") or contains(@href,"/setup")]')->length);
        }
    }

    public function testClosedYearPagesRemainReadOnlyAndFiltersRemainSelected(): void
    {
        $this->login();
        foreach (['years'=>'yearClosed','classrooms'=>'roomClosed','offerings'=>'offeringClosed'] as $area=>$key) {
            $body=$this->request('GET','/academic/'.$area.'/'.$this->f[$key].'/edit')->body();
            $this->assertPage($body);
            self::assertStringContainsString('ปิดปีแล้ว',$body);
            self::assertSame(0,$this->xpath($body)->query('//main//form[@method="post"]')->length);
        }
        foreach (['classrooms','offerings','teaching-assignments'] as $area) {
            $r=$this->request('GET','/academic/'.$area,[],['academic_year_id'=>(string)$this->f['yearClosed']]);
            self::assertSame(200,$r->status());
            self::assertSame((string)$this->f['yearClosed'],$this->xpath($r->body())->evaluate('string(//main//select[@name="academic_year_id"]/option[@selected]/@value)'));
        }
    }

    public static function invalidCreates(): iterable
    {
        foreach (['/system/schools','/admin/users','/academic/years','/academic/classrooms','/academic/subjects','/academic/offerings','/academic/teaching-assignments'] as $path) { yield [$path]; }
    }

    #[DataProvider('invalidCreates')]
    public function testValidationResponsesKeepShellAndNeverEchoPasswords(string $path): void
    {
        $this->login($path==='/system/schools'?'SYSTEM_ADMIN':'SCHOOL_ADMIN');
        $r=$this->request('POST',$path,['_token'=>$this->token(),'password'=>'private-password-marker','admin_password'=>'private-password-marker']);
        self::assertSame(422,$r->status());
        $x=$this->assertPage($r->body());
        self::assertSame(1,$x->query('//main//*[@role="alert"]')->length);
        self::assertStringNotContainsString('private-password-marker',$r->body());
    }

    public function testStatusVocabularyAndUnknownStatusEscaping(): void
    {
        foreach (['school','membership','user'] as $kind) { self::assertSame('ระงับ',StatusLabel::text('SUSPENDED',$kind)); }
        $html=View::render('ui/status',['status'=>'<script>unknown</script>','kind'=>'school']);
        self::assertStringContainsString('&lt;script&gt;unknown&lt;/script&gt;',$html);
        self::assertStringNotContainsString('<script>',$html);
    }

    private function assertPage(string $html): DOMXPath
    {
        $x=$this->xpath($html);
        self::assertSame(1,substr_count($html,'<!doctype html>'));
        foreach (['//html[@lang="th"]','//head','//body','//main','//h1','//*[@class="pp5-shell"]','//form[@action="/logout"]','//link[@href="/assets/app.css"]'] as $selector) { self::assertSame(1,$x->query($selector)->length,$selector); }
        self::assertSame(0,$x->query('//script[not(@src)]|//*[@style]|//link[starts-with(@href,"http")]|//script[starts-with(@src,"http")]')->length);
        self::assertStringNotContainsString('กลับแดชบอร์ด',$html);
        return $x;
    }
    private function resolve(string $path): string
    {
        $path=str_replace('{user}',(string)$this->users['VIEWER']['user'],$path);
        foreach ($this->f as $key=>$id) { $path=str_replace('{'.$key.'}',(string)$id,$path); }
        return $path;
    }
    private function removePermission(string $role,string $permission): void
    {
        $this->pdo->prepare('DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code=? AND p.code=?')->execute([$role,$permission]);
    }
}
