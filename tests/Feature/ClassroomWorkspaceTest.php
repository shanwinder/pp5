<?php
declare(strict_types=1);

use App\Repositories\ClassroomRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\AuthorizationRepository;
use App\Services\AuthorizationService;
use App\Services\ClassroomWorkspaceReadService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/GradebookReadFixtures.php';

final class ClassroomWorkspaceTest extends TestCase
{
    use GradebookReadFixtures;

    private function workspaceService(): ClassroomWorkspaceReadService
    {
        return new ClassroomWorkspaceReadService(new ClassroomRepository($this->pdo), new SchoolRepository($this->pdo),
            new AuthorizationService(new AuthorizationRepository($this->pdo)), $this->readService());
    }

    private function overview(string $role = 'SCHOOL_ADMIN', string $room = 'A'): ?array
    {
        return $this->workspaceService()->getOverview($this->users[$role]['user'], 'SCHOOL', $this->f['schoolA'], $this->f['room' . $room]);
    }

    private function workspacePath(string $room = 'A'): string
    {
        return '/workspaces/classrooms/' . $this->f['room' . $room];
    }

    public function testCanonicalRouteUsesSchoolContextAndReadCompositionAuthorization(): void
    {
        $route = FastRoute\simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php')
            ->dispatch('GET', $this->workspacePath());
        self::assertSame(FastRoute\Dispatcher::FOUND, $route[0]);
        self::assertSame(['action' => 'workspaces.classrooms.show', 'protected' => true, 'context' => 'SCHOOL'], $route[1]);
        self::assertSame(302, $this->request('GET', $this->workspacePath())->status());
        $this->login('SYSTEM_ADMIN');
        self::assertSame(404, $this->request('GET', $this->workspacePath())->status());
        $this->login();
        self::assertSame(405, $this->request('POST', $this->workspacePath())->status());
        foreach (['0', '-1', 'abc', '99999999999999999999999999999999'] as $id) {
            self::assertSame(404, $this->request('GET', '/workspaces/classrooms/' . $id)->status());
        }
    }

    public function testAuthoritativeContextAndAuthorizedLegacyLinksAreRenderedWithoutWrites(): void
    {
        $this->login();
        $room = (new ClassroomRepository($this->pdo))->findForSchool($this->f['schoolA'], $this->f['roomA']);
        $before = $this->readSnapshot();
        $model = $this->overview();
        self::assertSame(['id' => $room['id'], 'code' => $room['code'], 'name' => $room['name_th'], 'status' => $room['status']], $model['classroom']);
        self::assertSame(['id' => $room['academic_year_id'], 'year_be' => $room['year_be'], 'status' => $room['academic_year_status']], $model['academicYear']);
        self::assertSame(['id' => $room['grade_level_id'], 'name' => $room['grade_level_name']], $model['gradeLevel']);
        self::assertSame(['id' => $this->f['schoolA'], 'name' => 'โรงเรียนทดสอบ'], $model['school']);
        $normal = $this->request('GET', $this->workspacePath());
        self::assertSame(200, $normal->status());
        $forged = ['school_id' => $this->f['schoolB'], 'academic_year_id' => $this->f['yearB'], 'grade_level_id' => 999,
            'classroom_id' => $this->f['roomB'], 'user_id' => $this->users['FOREIGN']['user'], 'role' => 'SYSTEM_ADMIN', 'permission' => '*'];
        self::assertSame($normal->body(), $this->request('GET', $this->workspacePath(), $forged, $forged)->body());
        foreach (['ภาพรวม', 'ปีการศึกษา 2569', $room['name_th'], $room['grade_level_name'], 'โรงเรียนทดสอบ'] as $label) {
            self::assertStringContainsString($label, $normal->body());
        }
        $x = $this->xpath($normal->body());
        self::assertSame(1, $x->query('//main')->length);
        self::assertSame(1, $x->query('//h1')->length);
        self::assertSame(0, $x->query('//main//form')->length);
        self::assertSame(1, $x->query('//form[@action="/logout" and @method="post"]//input[@name="_token"]')->length);
        foreach ($model['links'] as $link) {
            $parts = parse_url($link['url']);
            parse_str($parts['query'] ?? '', $query);
            self::assertSame(200, $this->request('GET', $parts['path'], [], $query)->status(), $link['url']);
            if ($parts['path'] === '/academic/enrollments') {
                self::assertSame((string) $room['id'], $query['classroom_id']);
                self::assertSame((string) $room['academic_year_id'], $query['academic_year_id']);
                self::assertSame((string) $room['grade_level_id'], $query['grade_level_id']);
            }
        }
        self::assertSame($before, $this->readSnapshot());
    }

    public static function readers(): iterable
    {
        yield ['SCHOOL_ADMIN', true, 2];
        yield ['ACADEMIC_ADMIN', true, 2];
        yield ['SUBJECT_TEACHER', true, 1];
        yield ['EXECUTIVE', true, 2];
        yield ['VIEWER', false, 0];
        yield ['HOMEROOM_TEACHER', false, 0];
    }

    #[DataProvider('readers')]
    public function testOverviewContainsExactlyAccessibleOfferingsInThisClassroom(string $role, bool $allowed, int $count): void
    {
        $this->login($role);
        $model = $this->overview($role);
        $response = $this->request('GET', $this->workspacePath());
        self::assertSame($allowed ? 200 : 404, $response->status());
        if (!$allowed) { self::assertNull($model); return; }
        $expected = array_values(array_filter($this->accessible($role), fn (array $o): bool => $o['classroom_id'] === $this->f['roomA']));
        self::assertCount($count, $model['gradebooks']);
        self::assertSame(array_column($expected, 'id'), array_column($model['gradebooks'], 'id'));
        $hrefs = array_map(fn ($n) => $n->nodeValue, iterator_to_array($this->xpath($response->body())
            ->query('//main//a[starts-with(@href,"/gradebook/")]/@href')));
        self::assertSame(array_map(fn ($o) => '/gradebook/' . $o['id'], $expected), $hrefs);
        foreach ($hrefs as $href) { self::assertSame(200, $this->request('GET', $href)->status()); }
        $this->assertReadSafe($response->body());
    }

    public function testLimitedUserGetsNoStudentsAdminLinksCountsOrUnrelatedResources(): void
    {
        $this->login('SUBJECT_TEACHER');
        $model = $this->overview('SUBJECT_TEACHER');
        self::assertSame(['overview' => true, 'students' => false, 'subjects' => false, 'teaching' => false, 'scores' => true], $model['capabilities']);
        self::assertSame([], $model['links']);
        $body = $this->request('GET', $this->workspacePath())->body();
        $x = $this->xpath($body);
        self::assertSame(0, $x->query('//a[starts-with(@href,"/students") or starts-with(@href,"/academic/") or starts-with(@href,"/admin/")]')->length);
        self::assertSame(0, $x->query('//main//input | //main//button')->length);
        foreach (['01-CURRENT', '02-ZERO', 'จำนวน', 'Enrollment ID', 'Placement', 'Subject Offering', 'permission_scopes',
            '/gradebook/' . $this->f['offeringInactive'] . '"', '/gradebook/' . $this->f['offeringOther'] . '"'] as $secret) {
            self::assertStringNotContainsString($secret, $body);
        }
        foreach (['Other', 'Next', 'B'] as $room) {
            self::assertNull($this->overview('SUBJECT_TEACHER', $room));
            self::assertSame(404, $this->request('GET', $this->workspacePath($room))->status());
        }
        self::assertSame(403, $this->request('GET', '/students')->status());
        self::assertSame(403, $this->request('GET', '/academic/enrollments')->status());
        $this->assertReadSafe(json_encode($model));
    }

    public function testForeignMissingAndUnauthorizedClassroomsHaveIdenticalSafeErrors(): void
    {
        foreach (['SCHOOL_ADMIN', 'SUBJECT_TEACHER', 'VIEWER'] as $role) {
            $this->login($role);
            $missing = $this->request('GET', '/workspaces/classrooms/' . PHP_INT_MAX);
            $foreign = $this->request('GET', $this->workspacePath('B'));
            self::assertSame(404, $missing->status());
            self::assertSame(404, $foreign->status());
            self::assertSame($missing->body(), $foreign->body());
            self::assertNull($this->overview($role, 'B'));
            $this->assertReadSafe($foreign->body());
            foreach (['ROOM_B', 'ห้องทดสอบ', '2569', 'จำนวน'] as $secret) { self::assertStringNotContainsString($secret, $foreign->body()); }
            if ($role !== 'SCHOOL_ADMIN') {
                self::assertSame($missing->body(), $this->request('GET', $this->workspacePath('Other'))->body());
            }
        }
    }

    public static function revocations(): iterable
    {
        foreach (['scope', 'assignment', 'membership', 'school', 'role', 'permission'] as $kind) { yield [$kind]; }
    }

    #[DataProvider('revocations')]
    public function testRevocationIsLiveInTheSameServiceAndSession(string $kind): void
    {
        $this->login('SUBJECT_TEACHER');
        $service = $this->workspaceService();
        $args = [$this->users['SUBJECT_TEACHER']['user'], 'SCHOOL', $this->f['schoolA'], $this->f['roomA']];
        self::assertNotNull($service->getOverview(...$args));
        self::assertSame(200, $this->request('GET', $this->workspacePath())->status());
        $this->revoke($kind);
        self::assertNull($service->getOverview(...$args));
        self::assertSame(404, $this->request('GET', $this->workspacePath())->status());
        self::assertSame(404, $this->request('GET', $this->readPath())->status());
    }

    public static function permissions(): iterable
    {
        yield ['STUDENT_VIEW', 'students', '/academic/enrollments'];
        yield ['ACADEMIC_SETUP_VIEW', 'subjects', '/academic/offerings'];
        yield ['TEACHING_ASSIGNMENT_MANAGE', 'teaching', '/academic/teaching-assignments'];
    }

    #[DataProvider('permissions')]
    public function testCapabilitiesFollowIndividualLivePermissionsWithoutGrantingScoreAccess(string $permission, string $capability, string $path): void
    {
        $this->login('VIEWER');
        self::assertNull($this->overview('VIEWER'));
        $this->pdo->prepare("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code=?")->execute([$permission]);
        $model = $this->overview('VIEWER');
        self::assertTrue($model['capabilities'][$capability]);
        foreach (['students', 'subjects', 'teaching', 'scores'] as $other) {
            if ($other !== $capability) { self::assertFalse($model['capabilities'][$other]); }
        }
        self::assertSame([], $model['gradebooks']);
        self::assertCount(1, $model['links']);
        self::assertSame($path, parse_url($model['links'][0]['url'], PHP_URL_PATH));
        self::assertSame(200, $this->request('GET', $this->workspacePath())->status());
        self::assertSame(404, $this->request('GET', $this->readPath())->status());
        $this->pdo->prepare("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='VIEWER' AND p.code=?")->execute([$permission]);
        self::assertNull($this->overview('VIEWER'));
        self::assertSame(404, $this->request('GET', $this->workspacePath())->status());
    }

    public function testOverviewDoesNotQueryStudentsScoresOrTeachingIdentitiesEvenForAdmin(): void
    {
        foreach (['SCHOOL_ADMIN', 'SUBJECT_TEACHER'] as $role) {
            $this->login($role);
            $this->pdo->queries = [];
            $model = $this->overview($role);
            $response = $this->request('GET', $this->workspacePath());
            self::assertSame(200, $response->status());
            $sql = implode("\n", $this->pdo->queries);
            self::assertDoesNotMatchRegularExpression('/\b(students|student_enrollments|student_classroom_placements|gradebook_scores|gradebook_components|national_id|birth_date)\b/i', $sql);
            self::assertArrayNotHasKey('counts', $model);
            self::assertArrayNotHasKey('students', $model);
            $this->assertReadSafe(json_encode($model));
            $this->assertReadSafe($response->body());
            foreach (['เวลาเรียน', 'การประเมิน', 'สมรรถนะ', 'กิจกรรมพัฒนาผู้เรียน', 'สรุปผล', 'เอกสาร', '/reports', '/workspaces/classrooms/' . $this->f['roomA'] . '/students'] as $future) {
                self::assertStringNotContainsString($future, $response->body());
            }
        }
    }

    public function testHistoricalReadAndEmptyOverviewDoNotInventWriteCapabilities(): void
    {
        $this->login('SUBJECT_TEACHER');
        $this->scope($this->users['SUBJECT_TEACHER']['assignment'], 'Closed');
        $this->pdo->prepare("UPDATE classrooms SET status='INACTIVE' WHERE id=?")->execute([$this->f['roomClosed']]);
        $model = $this->overview('SUBJECT_TEACHER', 'Closed');
        self::assertSame('CLOSED', $model['academicYear']['status']);
        self::assertTrue($model['capabilities']['scores']);
        $response = $this->request('GET', $this->workspacePath('Closed'));
        self::assertSame(200, $response->status());
        self::assertStringContainsString('ปิดปีแล้ว', $response->body());
        self::assertStringContainsString('ปิดใช้งาน', $response->body());
        self::assertStringNotContainsString('hx-post', $response->body());
        self::assertStringNotContainsString('hx-post', $this->request('GET', $this->readPath('Closed'))->body());
        $room = $this->row('classrooms', $this->f['roomA']);
        unset($room['id'], $room['created_at'], $room['updated_at']);
        $room['code'] = 'EMPTY';
        $id = $this->insert('classrooms', $room);
        $this->login();
        $response = $this->request('GET', '/workspaces/classrooms/' . $id);
        self::assertSame(200, $response->status());
        self::assertStringContainsString('ยังไม่มีสมุดคะแนนที่คุณเข้าถึงได้ในห้องนี้', $response->body());
    }

    public function testHostileLabelsAreEscapedAndStoreFailureDoesNotLeak(): void
    {
        $this->login();
        $hostile = '<script>alert("workspace")</script>';
        foreach (['classrooms' => $this->f['roomA'], 'subjects' => $this->f['subjectA'], 'schools' => $this->f['schoolA']] as $table => $id) {
            $this->pdo->prepare('UPDATE ' . $table . ' SET name_th=? WHERE id=?')->execute([$hostile, $id]);
        }
        $response = $this->request('GET', $this->workspacePath());
        self::assertSame(200, $response->status());
        self::assertStringContainsString(htmlspecialchars($hostile, ENT_QUOTES, 'UTF-8'), $response->body());
        self::assertStringNotContainsString($hostile, $response->body());
        $this->pdo->failPrepare = 'FROM classrooms c';
        $response = $this->request('GET', $this->workspacePath());
        self::assertSame(500, $response->status());
        self::assertSame('Internal Server Error', $response->body());
        $this->assertReadSafe($response->body());
    }

    public function testInvalidTrustedContextFailsClosedBeforeQueries(): void
    {
        $service = $this->workspaceService();
        $this->pdo->queries = [];
        foreach (['SYSTEM', 'INVALID'] as $context) {
            self::assertNull($service->getOverview($this->users['SCHOOL_ADMIN']['user'], $context, $this->f['schoolA'], $this->f['roomA']));
        }
        self::assertNull($service->getOverview(0, 'SCHOOL', $this->f['schoolA'], $this->f['roomA']));
        self::assertNull($service->getOverview($this->users['SCHOOL_ADMIN']['user'], 'SCHOOL', 0, $this->f['roomA']));
        self::assertSame([], $this->pdo->queries);
    }
}
