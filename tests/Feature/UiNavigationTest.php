<?php
declare(strict_types=1);

use App\Http\Session;
use App\Repositories\AuthorizationRepository;
use App\Repositories\SchoolRepository;
use App\Services\AppUiContextService;
use App\Services\AuthorizationService;
use App\Support\Csrf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/GradebookReadFixtures.php';

final class UiNavigationTest extends TestCase
{
    use GradebookReadFixtures;

    public static function schoolRoles(): iterable
    {
        yield ['SCHOOL_ADMIN', true, true, 5];
        yield ['ACADEMIC_ADMIN', true, false, 5];
        yield ['SUBJECT_TEACHER', false, false, 1];
        yield ['EXECUTIVE', false, false, 5];
        yield ['VIEWER', false, false, 0];
        yield ['HOMEROOM_TEACHER', false, false, 0];
    }

    #[DataProvider('schoolRoles')]
    public function testSeededNavigationAndEveryLinkMatchBackendAccess(string $role, bool $academic, bool $users, int $books): void
    {
        $this->login($role);
        $response = $this->request('GET', '/dashboard');
        self::assertSame(200, $response->status());
        $links = $this->navLinks($response->body());
        $expected = ['/dashboard'];
        if ($academic) {
            array_push($expected, '/students', '/academic/enrollments', '/academic/student-import', '/academic/years',
                '/academic/classrooms', '/academic/subjects', '/academic/offerings', '/academic/teaching-assignments');
        }
        foreach ($this->accessible($role) as $offering) { $expected[] = '/gradebook/' . $offering['id']; }
        if ($users) { $expected[] = '/admin/users'; }
        self::assertSame($expected, $links);
        self::assertCount($books, array_filter($links, fn ($link) => str_starts_with($link, '/gradebook/')));
        foreach ($links as $link) { self::assertSame(200, $this->request('GET', $link)->status(), $link); }
        self::assertNotContains($this->readPath('B'), $links);
        self::assertNotContains('/gradebooks', $links);
        $this->assertReadSafe($response->body());
        self::assertSame(404, $this->request('GET', '/gradebooks')->status());
    }

    public static function grants(): iterable
    {
        yield ['STUDENT_VIEW', '/students'];
        yield ['STUDENT_IMPORT', '/academic/student-import'];
        yield ['ACADEMIC_SETUP_VIEW', '/academic/years'];
        yield ['TEACHING_ASSIGNMENT_MANAGE', '/academic/teaching-assignments'];
        yield ['SCHOOL_USER_VIEW', '/admin/users'];
    }

    #[DataProvider('grants')]
    public function testLiveGrantAndRevocationAgreeWithBackendWithoutLogin(string $permission, string $path): void
    {
        $this->login('VIEWER');
        self::assertNotContains($path, $this->navLinks($this->request('GET', '/dashboard')->body()));
        self::assertSame(403, $this->request('GET', $path)->status());
        $this->pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code=?")->execute([$permission]);
        self::assertContains($path, $this->navLinks($this->request('GET', '/dashboard')->body()));
        self::assertSame(200, $this->request('GET', $path)->status());
        $this->removePermission('VIEWER', $permission);
        self::assertNotContains($path, $this->navLinks($this->request('GET', '/dashboard')->body()));
        self::assertSame(403, $this->request('GET', $path)->status());
    }

    public function testLiveScopeAndForeignScopeCannotLeakAnOffering(): void
    {
        $this->login('SUBJECT_TEACHER');
        // A second assignment/scope in another tenant cannot affect this session.
        $assignment = $this->row('user_role_assignments', $this->users['SUBJECT_TEACHER']['assignment']);
        $this->insert('school_memberships', ['user_id'=>$this->users['SUBJECT_TEACHER']['user'], 'school_id'=>$this->f['schoolB']]);
        $foreignAssignment = $this->insert('user_role_assignments', ['school_id'=>$this->f['schoolB'],
            'user_id'=>$this->users['SUBJECT_TEACHER']['user'], 'role_id'=>$assignment['role_id']]);
        $this->scope($foreignAssignment, 'B');
        self::assertSame(['/dashboard', $this->readPath()], $this->navLinks($this->request('GET', '/dashboard')->body()));
        self::assertSame(200, $this->request('GET', $this->readPath())->status());
        self::assertSame(404, $this->request('GET', $this->readPath('B'))->status());
        $this->revoke('scope');
        self::assertSame(['/dashboard'], $this->navLinks($this->request('GET', '/dashboard')->body()));
        self::assertSame(404, $this->request('GET', $this->readPath())->status());
    }

    public function testQueryAndPostFieldsNeverBecomeShellAuthority(): void
    {
        $this->login('VIEWER');
        $_SESSION['display_name'] = 'ครูผู้ชม';
        $forged = ['school_id' => $this->f['schoolB'], 'user_id' => $this->users['SCHOOL_ADMIN']['user'],
            'role' => 'SCHOOL_ADMIN', 'context' => 'SYSTEM', 'context_type' => 'SYSTEM', 'scope' => '*', 'display_name' => 'FORGED_NAME'];
        $normal = $this->request('GET', '/dashboard')->body();
        self::assertSame($normal, $this->request('GET', '/dashboard', $forged, $forged)->body());
        self::assertSame(['/dashboard'], $this->navLinks($normal));
        self::assertSame(405, $this->request('POST', '/dashboard', $forged, $forged)->status());
        self::assertSame($normal, $this->request('GET', '/dashboard')->body());
        $this->assertReadSafe($normal);
    }

    public function testSystemNavigationAndActionsUseIndependentLivePermissions(): void
    {
        $this->login('SYSTEM_ADMIN');
        self::assertSame(['/system/schools', '/system/schools/create'], $this->navLinks($this->request('GET', '/system/schools')->body()));
        $this->removePermission('SYSTEM_ADMIN', 'SYSTEM_SCHOOL_CREATE');
        $this->removePermission('SYSTEM_ADMIN', 'SYSTEM_SCHOOL_STATUS_MANAGE');
        $response = $this->request('GET', '/system/schools');
        self::assertSame(200, $response->status());
        self::assertSame(['/system/schools'], $this->navLinks($response->body()));
        self::assertSame(0, $this->xpath($response->body())->query('//form[contains(@action,"/status")]')->length);
        self::assertSame(403, $this->request('GET', '/system/schools/create')->status());
        self::assertSame(403, $this->request('POST', '/system/schools/'.$this->f['schoolA'].'/status', ['_token'=>$this->token(),'status'=>'ACTIVE'])->status());
        foreach (['/dashboard', '/students', '/academic/', '/admin/users', '/gradebook/'] as $path) { self::assertStringNotContainsString('href="'.$path, $response->body()); }
        self::assertSame(403, $this->request('GET', '/dashboard')->status());
    }

    public function testAcademicListDoesNotExposeManageActionsToViewOnlyUser(): void
    {
        $this->login('ACADEMIC_ADMIN');
        $this->removePermission('ACADEMIC_ADMIN', 'ACADEMIC_YEAR_MANAGE');
        $response = $this->request('GET', '/academic/years');
        self::assertSame(200, $response->status());
        self::assertContains('/academic/years', $this->navLinks($response->body()));
        self::assertSame(0, $this->xpath($response->body())->query('//a[contains(@href,"/edit") or @href="/academic/years/create"]')->length);
        self::assertSame(403, $this->request('GET', '/academic/years/create')->status());
        self::assertSame(403, $this->request('GET', '/academic/years/'.$this->f['yearA'].'/edit')->status());
    }

    public static function pages(): iterable
    {
        yield ['SCHOOL_ADMIN', '/dashboard'];
        yield ['ACADEMIC_ADMIN', '/academic/years'];
        yield ['SYSTEM_ADMIN', '/system/schools'];
    }

    #[DataProvider('pages')]
    public function testShellSemanticsIdentityEscapingAndLogout(string $role, string $path): void
    {
        $this->login($role);
        $name = str_repeat('ชื่อภาษาไทยยาว', 4).'<script>alert("identity")</script>';
        $_SESSION['display_name'] = $name;
        $this->pdo->prepare('UPDATE schools SET name_th=? WHERE id=?')->execute([$name, $this->f['schoolA']]);
        $response = $this->request('GET', $path);
        self::assertSame(200, $response->status());
        $x = $this->xpath($response->body());
        foreach (['//html', '//body', '//main[@id="main-content"]', '//h1', '//nav[@aria-label="เมนูหลัก"]',
            '//a[@href="#main-content"]', '//header[contains(@class,"pp5-topbar")]', '//*[@class="pp5-shell"]',
            '//aside[@id="app-navigation" and @data-nav-panel]', '//button[@data-nav-toggle and @aria-controls="app-navigation" and @aria-expanded="true"]',
            '//nav//a[@aria-current="page" and @href="'.$path.'"]', '//form[@method="post" and @action="/logout"]'] as $selector) {
            self::assertSame(1, $x->query($selector)->length, $selector);
        }
        self::assertSame(0, $x->query('//nav//section[not(.//a)]|//script[not(@src)]|//*[@hidden]')->length);
        self::assertStringContainsString(htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $response->body());
        self::assertStringNotContainsString($name, $response->body());
        self::assertSame($name, $x->query('//*[@class="pp5-user-name"]')->item(0)->textContent);
        if ($role !== 'SYSTEM_ADMIN') { self::assertSame($name, $x->query('//*[@class="pp5-school-name"]')->item(0)->textContent); }
        $token = $x->query('//form[@action="/logout"]//input[@name="_token"]/@value')->item(0)->nodeValue;
        self::assertTrue((new Csrf())->verify(new Session(), $token));
        self::assertSame(419, $this->request('POST', '/logout', ['_token'=>'wrong'])->status());
        self::assertSame(302, $this->request('POST', '/logout', ['_token'=>$token])->status());
        self::assertSame([], $_SESSION);
    }

    public function testContextBuilderIsReadOnlyAndDoesNotCachePermissions(): void
    {
        $this->login('SUBJECT_TEACHER');
        $service = new AppUiContextService(new Session(), new SchoolRepository($this->pdo),
            new AuthorizationService(new AuthorizationRepository($this->pdo)), $this->readService(), new Csrf());
        $before = $this->readSnapshot();
        $this->pdo->queries = [];
        $context = $service->build('dashboard');
        self::assertSame('SCHOOL', $context['contextType']);
        self::assertSame('dashboard', $context['currentKey']);
        self::assertNotEmpty($context['sections']);
        foreach ($this->pdo->queries as $sql) { self::assertMatchesRegularExpression('/^\s*SELECT\b/i', $sql); }
        self::assertSame($before, $this->readSnapshot());
        $this->revoke('scope');
        $next = $service->build('dashboard');
        self::assertCount(1, $next['sections']);
        self::assertNotSame($context['sections'], $next['sections']);
    }

    private function navLinks(string $html): array
    {
        $x = $this->xpath($html);
        self::assertSame(1, $x->query('//nav[@aria-label="เมนูหลัก"]')->length, 'Shared navigation landmark');
        return array_map(fn ($node) => $node->nodeValue, iterator_to_array($x->query('//nav[@aria-label="เมนูหลัก"]//a/@href')));
    }

    private function removePermission(string $role, string $permission): void
    {
        $this->pdo->prepare('DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code=? AND p.code=?')->execute([$role, $permission]);
    }
}
