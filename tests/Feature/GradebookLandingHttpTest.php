<?php
declare(strict_types=1);

use App\Support\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/GradebookReadFixtures.php';

final class GradebookLandingHttpTest extends TestCase
{
    use GradebookReadFixtures;

    public static function readers(): iterable
    {
        foreach (['SCHOOL_ADMIN'=>5, 'ACADEMIC_ADMIN'=>5, 'SUBJECT_TEACHER'=>1, 'EXECUTIVE'=>5, 'VIEWER'=>0, 'HOMEROOM_TEACHER'=>0] as $role=>$count) {
            yield [$role, $count];
        }
    }

    public function testRouteIsAuthenticatedSchoolContextWithoutGenericPermissionGate(): void
    {
        $route = FastRoute\simpleDispatcher(require dirname(__DIR__, 2).'/htdocs/routes/web.php')->dispatch('GET', '/gradebooks');
        self::assertSame(FastRoute\Dispatcher::FOUND, $route[0]);
        self::assertSame(['action'=>'gradebook.index', 'protected'=>true, 'context'=>'SCHOOL'], $route[1]);
        self::assertSame(302, $this->request('GET', '/gradebooks')->status());
        $this->login('SYSTEM_ADMIN');
        self::assertSame(403, $this->request('GET', '/gradebooks')->status());
        $this->login();
        self::assertSame(405, $this->request('POST', '/gradebooks')->status());
    }

    #[DataProvider('readers')]
    public function testLandingListsExactlyAuthorizedResourcesAndSafeEmptyState(string $role, int $count): void
    {
        $this->login($role);
        $expected = array_map(fn ($o)=>'/gradebook/'.$o['id'], $this->accessible($role));
        $before = $this->readSnapshot();
        $r = $this->request('GET', '/gradebooks');
        self::assertSame(200, $r->status());
        $x = $this->xpath($r->body());
        $links = array_map(fn ($n)=>$n->nodeValue, iterator_to_array($x->query('//main//a[starts-with(@href,"/gradebook/")]/@href')));
        self::assertCount($count, $links);
        self::assertSame($expected, $links);
        self::assertSame($count ? 1 : 0, $x->query('//nav//a[@href="/gradebooks" and @aria-current="page"]')->length);
        self::assertSame(0, $x->query('//nav//a[starts-with(@href,"/gradebook/")]')->length);
        self::assertSame(1, $x->query('//h1')->length);
        self::assertSame(1, $x->query('//*[@class="pp5-shell"]')->length);
        if ($count === 0) { self::assertStringContainsString('ยังไม่มีสมุดคะแนนที่เข้าถึงได้', $r->body()); }
        else {
            foreach (['ปีการศึกษา', 'ห้องเรียน', 'รายวิชา', 'ภาคเรียน', 'สถานะปี', 'สถานะรายวิชา', 'เปิดสมุดคะแนน', 'ร่าง'] as $label) {
                self::assertStringContainsString($label, $r->body());
            }
        }
        $this->assertReadSafe($r->body());
        self::assertNotContains($this->readPath('B'), $links);
        self::assertSame($before, $this->readSnapshot());
    }

    public function testScopeRevocationAndDirectGrantRevokeAreLiveWithoutRelogin(): void
    {
        $this->login('SUBJECT_TEACHER');
        self::assertStringContainsString('href="'.$this->readPath().'"', $this->request('GET', '/gradebooks')->body());
        $this->revoke('scope');
        self::assertStringContainsString('ยังไม่มีสมุดคะแนนที่เข้าถึงได้', $this->request('GET', '/gradebooks')->body());
        self::assertSame($this->request('GET', '/gradebook/'.PHP_INT_MAX)->body(), $this->request('GET', $this->readPath())->body());
        self::assertSame(404, $this->request('GET', $this->readPath())->status());
        $this->login('VIEWER');
        $this->pdo->exec("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code='GRADEBOOK_VIEW'");
        self::assertSame(5, $this->xpath($this->request('GET', '/gradebooks')->body())->query('//main//a[starts-with(@href,"/gradebook/")]')->length);
        self::assertSame(200, $this->request('GET', $this->readPath())->status());
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='VIEWER' AND p.code='GRADEBOOK_VIEW'");
        self::assertStringContainsString('ยังไม่มีสมุดคะแนนที่เข้าถึงได้', $this->request('GET', '/gradebooks')->body());
        self::assertSame(404, $this->request('GET', $this->readPath())->status());
    }

    public function testBrowserAuthorityIgnoredAndHostileIdentityEscaped(): void
    {
        $this->login('SUBJECT_TEACHER');
        $hostile = str_repeat('ชื่อภาษาไทยยาว', 8).'<script>alert("landing")</script>';
        $this->pdo->prepare('UPDATE subjects SET name_th=? WHERE id=?')->execute([$hostile, $this->f['subjectA']]);
        $normal = $this->request('GET', '/gradebooks')->body();
        $forged = ['school_id'=>$this->f['schoolB'], 'user_id'=>$this->users['SCHOOL_ADMIN']['user'], 'role'=>'SCHOOL_ADMIN', 'context_type'=>'SYSTEM', 'scope'=>'*'];
        self::assertSame($normal, $this->request('GET', '/gradebooks', $forged, $forged)->body());
        self::assertStringContainsString(htmlspecialchars($hostile, ENT_QUOTES, 'UTF-8'), $normal);
        self::assertStringNotContainsString($hostile, $normal);
        $this->assertReadSafe($normal);
    }

    public function testHistoricalStatusesAndUnknownStatusArePresentationOnly(): void
    {
        $this->login();
        $body = $this->request('GET', '/gradebooks')->body();
        foreach (['ปิดปีแล้ว', 'ปิดใช้งาน', 'กำลังใช้งาน', 'data-status="CLOSED"', 'data-status="INACTIVE"'] as $label) {
            self::assertStringContainsString($label, $body);
        }
        $offering = $this->accessible('SCHOOL_ADMIN')[0];
        $offering['status'] = '<script>unknown</script>';
        $body = View::render('gradebook/offering-list', ['offerings'=>[$offering]]);
        self::assertStringContainsString('&lt;script&gt;unknown&lt;/script&gt;', $body);
        self::assertStringNotContainsString($offering['status'], $body);
    }

    public function testEachResponseListsOfferingsOnceAndShellShortCircuitsAuthorization(): void
    {
        $this->login();
        foreach (['/dashboard', '/gradebooks', '/academic/years'] as $path) {
            $this->pdo->queries = [];
            self::assertSame(200, $this->request('GET', $path)->status());
            $lists = array_filter($this->pdo->queries, fn ($sql)=>str_contains($sql, 'FROM subject_offerings o') && str_contains($sql, 'ORDER BY y.year_be'));
            self::assertCount(1, $lists, $path);
            $checks = array_filter($this->pdo->queries, fn ($sql)=>str_contains($sql, 'INNER JOIN subject_offerings o ON o.school_id = ura.school_id'));
            self::assertCount($path === '/academic/years' ? 1 : 5, $checks, $path);
        }
    }
}
