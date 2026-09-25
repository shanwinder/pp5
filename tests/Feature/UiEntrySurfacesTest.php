<?php
declare(strict_types=1);

use App\Support\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookReadFixtures.php';

final class UiEntrySurfacesTest extends TestCase
{
    use GradebookReadFixtures;

    public function testLoginUsesGuestLayoutAndKeyboardFriendlyForm(): void
    {
        $r = $this->request('GET', '/login');
        self::assertSame(200, $r->status());
        $x = $this->document($r->body());
        self::assertSame(1, $x->query('//body[contains(@class,"pp5-guest")]')->length);
        foreach (['username'=>'username', 'password'=>'current-password'] as $name=>$autocomplete) {
            self::assertSame(1, $x->query('//label[@for="'.$name.'"]')->length);
            self::assertSame(1, $x->query('//input[@id="'.$name.'" and @name="'.$name.'" and @autocomplete="'.$autocomplete.'" and @required]')->length);
        }
        self::assertSame(1, $x->query('//form[@method="post" and @action="/login"]//button[@type="submit"]')->length);
        self::assertSame(0, $x->query('//select|//input[@name="school_id" or @name="role" or @name="context_type"]')->length);
    }

    public function testInvalidLoginsAreGenericAndNeverReflectSubmittedValues(): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash('correct-password', PASSWORD_DEFAULT), $this->users['VIEWER']['user']]);
        $bodies = [];
        foreach (['component-test-VIEWER', 'no-such-username', '<script>alert("login")</script>'] as $username) {
            $r = $this->request('POST', '/login', ['_token'=>$this->token(), 'username'=>$username, 'password'=>'<script>password</script>']);
            self::assertSame(422, $r->status());
            $x = $this->document($r->body());
            self::assertSame('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', trim($x->evaluate('string(//*[@role="alert"])')));
            self::assertStringNotContainsString($username, $r->body());
            self::assertSame(0, $x->query('//input[@type="password" and @value]')->length);
            $bodies[] = $r->body();
        }
        self::assertSame($bodies[0], $bodies[1]);
        self::assertSame($bodies[1], $bodies[2]);
    }

    public function testSharedErrorsAreContextFreeAndDoNotReflectHiddenData(): void
    {
        $this->login('VIEWER');
        $hostile = '/<script>SQLSTATE Stack trace /Applications/MAMP/private FOREIGN_SECRET</script>';
        foreach (['/admin/users'=>403, $hostile=>404, $this->readPath('B')=>404] as $path=>$status) {
            $r = $this->request('GET', $path);
            self::assertSame($status, $r->status());
            $x = $this->document($r->body());
            self::assertSame(1, $x->query('//body[contains(@class,"pp5-error")]')->length);
            self::assertSame(0, $x->query('//nav|//aside')->length);
            self::assertStringNotContainsString($hostile, $r->body());
            $this->assertReadSafe($r->body());
            self::assertSame(1, $x->query('//a[@href="/login"]')->length);
        }
    }

    public function testDashboardHasConciseWorkAreasAndEscapedIdentityWithoutMetrics(): void
    {
        $this->login();
        $name = str_repeat('ชื่อภาษาไทยยาว', 8).'<script>alert("entry")</script>';
        $_SESSION['display_name'] = $name;
        $this->pdo->prepare('UPDATE schools SET name_th=? WHERE id=?')->execute([$name, $this->f['schoolA']]);
        $r = $this->request('GET', '/dashboard');
        $x = $this->document($r->body());
        self::assertSame(1, $x->query('//main//section[@aria-labelledby="work-areas"]')->length);
        self::assertSame(3, $x->query('//main//a[starts-with(@href,"/gradebook/")]')->length);
        self::assertLessThanOrEqual(3, $x->query('//main//section[@aria-labelledby="work-areas"]//a')->length);
        self::assertStringContainsString(htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $r->body());
        self::assertStringNotContainsString($name, $r->body());
        foreach (['นักเรียนทั้งหมด', 'คะแนนเฉลี่ย', 'อัตราการเข้าเรียน'] as $metric) { self::assertStringNotContainsString($metric, $r->body()); }
        $this->login('VIEWER');
        $body = $this->request('GET', '/dashboard')->body();
        self::assertStringContainsString('ยังไม่มีสมุดคะแนนที่เข้าถึงได้', $body);
        self::assertStringContainsString('ยังไม่มีงานจัดการที่เข้าถึงได้', $body);
        self::assertSame(0, $this->xpath($body)->query('//main//a[starts-with(@href,"/admin/") or starts-with(@href,"/academic/") or @href="/students"]')->length);
    }

    private function document(string $body): DOMXPath
    {
        self::assertSame(1, substr_count($body, '<!doctype html>'));
        $x = $this->xpath($body);
        foreach (['html', 'head', 'body', 'main', 'h1'] as $tag) { self::assertSame(1, $x->query('//'.$tag)->length); }
        self::assertSame(1, $x->query('//link[@href="/assets/app.css"]')->length);
        self::assertSame(0, $x->query('//script[not(@src)]|//*[@style]|//*[@onclick]|//link[starts-with(@href,"http")]')->length);
        return $x;
    }
}
