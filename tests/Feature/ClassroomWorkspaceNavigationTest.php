<?php
declare(strict_types=1);

use App\Repositories\{ClassroomRepository, SchoolRepository, AuthorizationRepository};
use App\Services\{AuthorizationService, ClassroomWorkspaceReadService};
use App\Support\ClassroomWorkspaceNavigation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/GradebookReadFixtures.php';

final class ClassroomWorkspaceNavigationTest extends TestCase
{
    use GradebookReadFixtures;

    private function workspaces(): ClassroomWorkspaceReadService
    {
        return new ClassroomWorkspaceReadService(new ClassroomRepository($this->pdo), new SchoolRepository($this->pdo),
            new AuthorizationService(new AuthorizationRepository($this->pdo)), $this->readService());
    }

    private function targets(string $role): array
    {
        return $this->workspaces()->listAccessibleClassrooms($this->users[$role]['user'], 'SCHOOL', $this->f['schoolA']);
    }

    private function model(string $role = 'SCHOOL_ADMIN'): array
    {
        return $this->workspaces()->getOverview($this->users[$role]['user'], 'SCHOOL', $this->f['schoolA'], $this->f['roomA']);
    }

    private function links(string $body, string $selector): array
    {
        return array_map(static fn ($node) => $node->nodeValue, iterator_to_array($this->xpath($body)->query($selector . '/@href')));
    }

    private function workspacePath(string $room = 'A'): string { return '/workspaces/classrooms/' . $this->f['room' . $room]; }

    public function testBroadSwitchingIncludesSchoolYearsAndDerivesDestinationContext(): void
    {
        $this->login();
        $targets = $this->targets('SCHOOL_ADMIN');
        self::assertEqualsCanonicalizing([$this->f['roomA'], $this->f['roomOther'], $this->f['roomNext'], $this->f['roomClosed']], array_column($targets, 'id'));
        self::assertSame([2570, 2569, 2569, 2568], array_column($targets, 'year_be'));
        foreach ($targets as $target) {
            $response = $this->request('GET', $target['url'], [], ['academic_year_id' => $this->f['yearB'], 'school_id' => $this->f['schoolB']]);
            self::assertSame(200, $response->status());
            $text = $this->xpath($response->body())->query('//section[@aria-label="บริบทงานชั้นเรียน"]')->item(0)->textContent;
            self::assertStringContainsString('ปีการศึกษา ' . $target['year_be'], $text);
            self::assertStringContainsString($target['name'], $text);
            self::assertStringNotContainsString('FOREIGN_SECRET', $text);
        }
        self::assertSame(404, $this->request('GET', $this->workspacePath('B'))->status());
    }

    public function testLimitedSwitcherAndEntryPointsUseOnlyLiveAccessibleOfferings(): void
    {
        $this->login('SUBJECT_TEACHER');
        self::assertSame([$this->f['roomA']], array_column($this->targets('SUBJECT_TEACHER'), 'id'));
        foreach (['/dashboard', '/gradebooks', $this->workspacePath(), $this->readPath()] as $path) {
            $response = $this->request('GET', $path);
            self::assertSame(200, $response->status());
            $links = $this->links($response->body(), '//main//a[starts-with(@href,"/workspaces/classrooms/")]');
            self::assertNotEmpty($links);
            foreach ($links as $link) { self::assertSame($this->workspacePath(), explode('#', $link)[0]); }
            self::assertSame([], $this->links($response->body(), '//nav[@aria-label="งานในห้องเรียน"]//a[starts-with(@href,"/academic/")]'));
            self::assertStringNotContainsString($this->readPath('Other') . '"', $response->body());
        }
        $body = $this->request('GET', $this->readPath())->body();
        self::assertSame($body, $this->request('GET', $this->readPath(), [], ['workspace_classroom_id' => $this->f['roomB']])->body());
        self::assertStringNotContainsString('2570', $body);
        self::assertSame([$this->workspacePath() . '#workspace-scores'], $this->links($body, '//nav[@aria-label="งานในห้องเรียน"]//a[@aria-current="page"]'));
        self::assertSame(['/gradebooks'], $this->links($body, '//aside//a[@aria-current="page"]'));
        $this->scope($this->users['SUBJECT_TEACHER']['assignment'], 'Closed');
        self::assertSame([$this->f['roomA'], $this->f['roomClosed']], array_column($this->targets('SUBJECT_TEACHER'), 'id'));
        self::assertSame(200, $this->request('GET', $this->workspacePath('Closed'))->status());
        self::assertSame(404, $this->request('GET', $this->workspacePath('Next'))->status());
        $this->revoke('scope');
        self::assertSame([$this->f['roomClosed']], array_column($this->targets('SUBJECT_TEACHER'), 'id'));
        self::assertSame(404, $this->request('GET', $this->workspacePath())->status());
        self::assertSame(200, $this->request('GET', $this->workspacePath('Closed'))->status());
        $remaining = $this->request('GET', '/dashboard')->body();
        self::assertNotContains($this->workspacePath(), $this->links($remaining, '//a[starts-with(@href,"/workspaces/classrooms/")]'));
        self::assertContains($this->workspacePath('Closed'), $this->links($remaining, '//a[starts-with(@href,"/workspaces/classrooms/")]'));
    }

    public static function capabilities(): iterable
    {
        yield ['STUDENT_VIEW', 'students', '/academic/enrollments'];
        yield ['ACADEMIC_SETUP_VIEW', 'subjects', '/academic/offerings'];
        yield ['TEACHING_ASSIGNMENT_MANAGE', 'teaching', '/academic/teaching-assignments'];
    }

    #[DataProvider('capabilities')]
    public function testIndividualPermissionControlsNavigationAndSwitchingLive(string $permission, string $section, string $path): void
    {
        $this->login('VIEWER');
        self::assertSame([], $this->targets('VIEWER'));
        self::assertStringNotContainsString('id="classroom-workspaces"', $this->request('GET', '/dashboard')->body());
        $this->pdo->prepare("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code=?")->execute([$permission]);
        $model = $this->model('VIEWER');
        if ($section === 'students') { $path = $this->workspacePath() . '/students'; }
        self::assertSame(['overview', $section], array_column(ClassroomWorkspaceNavigation::items($model, 'workspaces.classrooms'), 'key'));
        self::assertCount(4, $this->targets('VIEWER'));
        $body = $this->request('GET', $this->workspacePath())->body();
        self::assertCount(1, $this->links($body, '//nav[@aria-label="งานในห้องเรียน"]//a[starts-with(@href,"' . $path . '")]'));
        self::assertStringContainsString('id="classroom-workspaces"', $this->request('GET', '/dashboard')->body());
        $this->pdo->prepare("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='VIEWER' AND p.code=?")->execute([$permission]);
        self::assertSame([], $this->targets('VIEWER'));
        self::assertStringNotContainsString('id="classroom-workspaces"', $this->request('GET', '/dashboard')->body());
        self::assertSame(404, $this->request('GET', $this->workspacePath())->status());
    }

    public static function revocations(): iterable
    {
        foreach (['scope', 'assignment', 'membership', 'school', 'role', 'permission'] as $kind) { yield [$kind]; }
    }

    #[DataProvider('revocations')]
    public function testRevokedAuthorityRemovesTargetsAndNavigationInSameSession(string $kind): void
    {
        $this->login('SUBJECT_TEACHER');
        $service = $this->workspaces();
        $args = [$this->users['SUBJECT_TEACHER']['user'], 'SCHOOL', $this->f['schoolA']];
        self::assertNotEmpty($service->listAccessibleClassrooms(...$args));
        $this->revoke($kind);
        self::assertSame([], $service->listAccessibleClassrooms(...$args));
        foreach (['/dashboard', '/gradebooks'] as $path) {
            self::assertSame([], $this->links($this->request('GET', $path)->body(), '//a[starts-with(@href,"/workspaces/classrooms/")]'));
        }
        self::assertSame(404, $this->request('GET', $this->workspacePath())->status());
        self::assertSame(404, $this->request('GET', $this->readPath())->status());
    }

    public function testLegacyDestinationsKeepAuthoritativeContextAndNavigation(): void
    {
        $this->login();
        $model = $this->model();
        foreach ($model['links'] as $link) {
            $parts = parse_url($link['url']); parse_str($parts['query'] ?? '', $query);
            $normal = $this->request('GET', $parts['path'], [], $query);
            self::assertSame(200, $normal->status());
            $forged = array_replace($query, ['school_id' => $this->f['schoolB'], 'academic_year_id' => $this->f['yearB'],
                'grade_level_id' => 999, 'classroom_id' => $this->f['roomB'], 'user_id' => $this->users['FOREIGN']['user']]);
            self::assertSame($normal->body(), $this->request('GET', $parts['path'], [], $forged)->body());
            $x = $this->xpath($normal->body());
            self::assertSame(1, $x->query('//section[@aria-label="บริบทงานชั้นเรียน"]')->length);
            self::assertSame([$link['url']], $this->links($normal->body(), '//nav[@aria-label="งานในห้องเรียน"]//a[@aria-current="page"]'));
            self::assertSame($link['key'] === 'students' ? [] : [$parts['path']], $this->links($normal->body(), '//aside//a[@aria-current="page"]'));
            self::assertSame(1, $x->query('//form[@method="post" and @action="/logout"]//input[@name="_token"]')->length);
            if ($link['key'] !== 'students') { self::assertStringContainsString('หน้านี้แสดงทุกห้องในปีการศึกษา 2569', $normal->body()); }
        }
        $response = $this->request('GET', '/academic/enrollments', [], ['workspace_classroom_id' => $this->f['roomA'], 'q' => '01-CURRENT']);
        self::assertSame(200, $response->status());
        $x = $this->xpath($response->body());
        self::assertSame((string) $this->f['roomA'], $x->query('//form[@method="get"]//input[@name="workspace_classroom_id"]/@value')->item(0)->nodeValue);
        self::assertSame(0, $x->query('//form[@method="get"]//*[@name="academic_year_id" or @name="grade_level_id" or @name="classroom_id"]')->length);
    }

    public static function invalidLocators(): iterable
    {
        foreach (['B', 'missing', 'zero', 'array', 'overflow', 'text'] as $kind) { yield [$kind]; }
    }

    #[DataProvider('invalidLocators')]
    public function testLegacyContextLocatorFailsClosed(string $kind): void
    {
        $this->login();
        $locator = match ($kind) { 'B' => $this->f['roomB'], 'missing' => PHP_INT_MAX, 'zero' => 0,
            'array' => ['1'], 'overflow' => '999999999999999999999999', default => 'admin' };
        foreach (['/academic/enrollments', '/academic/offerings', '/academic/teaching-assignments'] as $path) {
            $response = $this->request('GET', $path, [], ['workspace_classroom_id' => $locator]);
            self::assertSame(404, $response->status());
            self::assertStringNotContainsString('FOREIGN_SECRET', $response->body());
        }
    }

    public static function routeKeys(): iterable
    {
        yield ['workspaces.classrooms', 'overview'];
        yield ['workspaces.classrooms.details', 'overview'];
        yield ['workspaces.classrooms.students.details', 'students'];
        yield ['workspaces.classrooms.scores.details', 'scores'];
        yield ['enrollments', 'students'];
        yield ['enrollments.details', 'students'];
        yield ['academic.offerings.details', 'subjects'];
        yield ['teaching-assignments.details', 'teaching'];
        yield ['gradebooks.details', 'scores'];
    }

    #[DataProvider('routeKeys')]
    public function testSectionActiveStateUsesControllerKeysIncludingDescendants(string $key, string $section): void
    {
        $items = ClassroomWorkspaceNavigation::items($this->model(), $key);
        self::assertSame([$section], array_column(array_filter($items, static fn (array $i): bool => $i['active']), 'key'));
        $dispatcher = FastRoute\simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php');
        self::assertSame(FastRoute\Dispatcher::FOUND, $dispatcher->dispatch('GET', $this->workspacePath() . '/students')[0]);
        self::assertSame(FastRoute\Dispatcher::NOT_FOUND, $dispatcher->dispatch('GET', $this->workspacePath() . '/subjects')[0]);
    }

    public function testSwitchingDoesNotAddQueriesPerClassroomOrReadSensitiveTables(): void
    {
        $this->login();
        $this->pdo->queries = []; $this->targets('SCHOOL_ADMIN');
        $baseline = count($this->pdo->queries);
        $room = $this->row('classrooms', $this->f['roomA']); unset($room['id'], $room['created_at'], $room['updated_at']);
        for ($i = 0; $i < 10; ++$i) { $this->insert('classrooms', array_replace($room, ['code' => 'NAV' . $i])); }
        $this->pdo->queries = []; self::assertCount(14, $this->targets('SCHOOL_ADMIN'));
        self::assertCount($baseline, $this->pdo->queries);
        self::assertDoesNotMatchRegularExpression('/\\b(students|student_enrollments|student_classroom_placements|gradebook_scores|gradebook_components|national_id|birth_date)\\b/i', implode("\n", $this->pdo->queries));
    }

    public function testEscapedSwitchLabelsAndSystemIsolation(): void
    {
        $this->login();
        $hostile = '<img src=x onerror=alert(1)>';
        $this->pdo->prepare('UPDATE classrooms SET name_th=? WHERE id=?')->execute([$hostile, $this->f['roomOther']]);
        foreach (['/dashboard', $this->workspacePath()] as $path) {
            $body = $this->request('GET', $path)->body();
            self::assertStringContainsString(htmlspecialchars($hostile, ENT_QUOTES, 'UTF-8'), $body);
            self::assertStringNotContainsString($hostile, $body);
        }
        $this->login('SYSTEM_ADMIN');
        self::assertSame([], $this->workspaces()->listAccessibleClassrooms($this->users['SYSTEM_ADMIN']['user'], 'SYSTEM', $this->f['schoolA']));
        $body = $this->request('GET', '/system/schools')->body();
        self::assertSame([], $this->links($body, '//a[starts-with(@href,"/workspaces/")]'));
        self::assertSame(['/system/schools', '/system/schools/create'], $this->links($body, '//aside//a'));
    }
}
