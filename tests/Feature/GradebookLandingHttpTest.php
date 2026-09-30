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
        $links = array_map(fn ($n)=>$n->nodeValue, iterator_to_array($x->query('//main//a[starts-with(@href,"/gradebook/") and not(contains(@href,"/setup"))]/@href')));
        self::assertCount($count, $links);
        self::assertSame($expected, $links);
        self::assertSame($count ? 1 : 0, $x->query('//nav//a[@href="/gradebooks" and @aria-current="page"]')->length);
        self::assertSame(0, $x->query('//nav//a[starts-with(@href,"/gradebook/")]')->length);
        self::assertSame(1, $x->query('//h1')->length);
        self::assertSame(1, $x->query('//*[@class="pp5-shell"]')->length);
        self::assertStringContainsString('งานสอนของฉัน', $r->body());
        if ($count === 0) { self::assertStringContainsString('ยังไม่มีรายวิชาที่เข้าถึงได้ในขณะนี้', $r->body()); }
        else {
            foreach (['ปีการศึกษา', 'ห้องเรียน', 'รายวิชา', 'ภาคเรียน', 'งานปัจจุบัน', 'ร่าง'] as $label) {
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
        $revoked = $this->request('GET', '/gradebooks')->body();
        self::assertStringContainsString('ยังไม่มีรายวิชาที่เข้าถึงได้ในขณะนี้', $revoked);
        self::assertStringNotContainsString('วิทยาศาสตร์', $revoked);
        self::assertStringNotContainsString('ROOM_A', $revoked);
        self::assertSame($this->request('GET', '/gradebook/'.PHP_INT_MAX)->body(), $this->request('GET', $this->readPath())->body());
        self::assertSame(404, $this->request('GET', $this->readPath())->status());
        $this->login('VIEWER');
        $this->pdo->exec("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code='GRADEBOOK_VIEW'");
        self::assertSame(5, $this->xpath($this->request('GET', '/gradebooks')->body())->query('//main//a[starts-with(@href,"/gradebook/") and not(contains(@href,"/setup"))]')->length);
        self::assertSame(200, $this->request('GET', $this->readPath())->status());
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='VIEWER' AND p.code='GRADEBOOK_VIEW'");
        self::assertStringContainsString('ยังไม่มีรายวิชาที่เข้าถึงได้ในขณะนี้', $this->request('GET', '/gradebooks')->body());
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
            self::assertCount($path === '/gradebooks' ? 8 : ($path === '/academic/years' ? 1 : 5), $checks, $path);
            if ($path === '/gradebooks') {
                self::assertSame([], array_values(array_filter($this->pdo->queries, static fn ($sql) =>
                    str_contains($sql, 'student_enrollments') || str_contains($sql, 'gradebook_scores')
                    || str_contains($sql, 'teaching_assignments') || str_contains($sql, 'gradebook_components'))));
            }
        }
    }

    public function testScopedTeacherCanReachTwoDistinctTermsWithoutSeeingOtherWork(): void
    {
        $this->scope($this->users['SUBJECT_TEACHER']['assignment'], 'Other');
        $this->login('SUBJECT_TEACHER');
        $response = $this->request('GET', '/gradebooks');
        self::assertSame(200, $response->status());
        $x = $this->xpath($response->body());
        self::assertSame(2, $x->query('//main//a[starts-with(@href,"/gradebook/")]')->length);
        foreach (['A' => 'ภาคเรียน 1', 'Other' => 'ภาคเรียน 2'] as $key => $term) {
            $path = $this->readPath($key);
            self::assertSame(1, $x->query('//main//a[@href="'.$path.'" and contains(.,"'.$term.'")]')->length);
            self::assertSame(200, $this->request('GET', $path)->status());
        }
        self::assertStringNotContainsString('ROOM_Closed', $response->body());
        self::assertStringNotContainsString('ROOM_Next', $response->body());
        self::assertSame(404, $this->request('GET', $this->readPath('Closed'))->status());
        self::assertSame(403, $this->request('GET', $this->readPath('A').'/setup')->status());
    }

    public function testReadOnlyCurrentAndHistoricalActionsRemainDistinct(): void
    {
        $this->login('EXECUTIVE');
        $x = $this->xpath($this->request('GET', '/gradebooks')->body());
        self::assertSame(1, $x->query('//section[@aria-labelledby="teaching-current"]//a[@href="'.$this->readPath().'" and contains(.,"ดูคะแนน")]')->length);
        self::assertSame(1, $x->query('//section[@aria-labelledby="teaching-history"]//a[@href="'.$this->readPath('Closed').'" and contains(.,"ดูคะแนน")]')->length);
        self::assertSame(0, $x->query('//main//a[contains(.,"กรอกคะแนน") or contains(@href,"/setup")]')->length);
        self::assertSame(200, $this->request('GET', $this->readPath())->status());
        self::assertSame(200, $this->request('GET', $this->readPath('Closed'))->status());
    }

    public function testNavigationNamesTheTaskOnlyWhenWorkIsAccessible(): void
    {
        $this->login('SUBJECT_TEACHER');
        $x = $this->xpath($this->request('GET', '/gradebooks')->body());
        self::assertSame(1, $x->query('//nav//a[@href="/gradebooks" and @aria-current="page" and normalize-space(.)="งานสอนของฉัน"]')->length);
        $this->revoke('scope');
        $x = $this->xpath($this->request('GET', '/gradebooks')->body());
        self::assertSame(0, $x->query('//nav//a[@href="/gradebooks"]')->length);
        $this->login('SYSTEM_ADMIN');
        self::assertSame(403, $this->request('GET', '/gradebooks')->status());
    }

    public function testScoreAndSetupRevocationsUpdateActionsIndependently(): void
    {
        $this->login('SCHOOL_ADMIN');
        $path = $this->readPath();
        $initial = $this->request('GET', '/gradebooks')->body();
        self::assertSame(1, $this->xpath($initial)->query('//main//a[@href="'.$path.'" and contains(.,"กรอกคะแนน")]')->length);
        self::assertSame(1, $this->xpath($initial)->query('//main//a[@href="'.$path.'/setup"]')->length);
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='SCHOOL_ADMIN' AND p.code='GRADEBOOK_SCORE_ENTER'");
        $afterScore = $this->request('GET', '/gradebooks')->body();
        self::assertSame(1, $this->xpath($afterScore)->query('//main//a[@href="'.$path.'" and contains(.,"ดูคะแนน")]')->length);
        self::assertSame(1, $this->xpath($afterScore)->query('//main//a[@href="'.$path.'/setup"]')->length);
        self::assertStringContainsString('อ่านอย่างเดียว', $this->request('GET', $path)->body());
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='SCHOOL_ADMIN' AND p.code='GRADEBOOK_COMPONENT_MANAGE'");
        $afterSetup = $this->request('GET', '/gradebooks')->body();
        self::assertSame(0, $this->xpath($afterSetup)->query('//main//a[contains(@href,"/setup")]')->length);
        self::assertSame(1, $this->xpath($afterSetup)->query('//main//a[@href="'.$path.'"]')->length);
        self::assertSame(403, $this->request('GET', $path.'/setup')->status());
    }

    public function testSetupRevocationDoesNotRemoveWritableScoreAction(): void
    {
        $this->login('SCHOOL_ADMIN');
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='SCHOOL_ADMIN' AND p.code='GRADEBOOK_COMPONENT_MANAGE'");
        $path = $this->readPath();
        $x = $this->xpath($this->request('GET', '/gradebooks')->body());
        self::assertSame(1, $x->query('//main//a[@href="'.$path.'" and contains(.,"กรอกคะแนน")]')->length);
        self::assertSame(0, $x->query('//main//a[@href="'.$path.'/setup"]')->length);
        self::assertStringContainsString('แก้ไขคะแนนได้', $this->request('GET', $path)->body());
    }
}
