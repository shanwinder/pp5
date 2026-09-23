<?php
declare(strict_types=1);

use App\Repositories\TeachingAssignmentRepository;
use FastRoute\Dispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/TeachingAssignmentHttpFixtures.php';

final class TeachingAssignmentHttpTest extends TestCase
{
    use TeachingAssignmentHttpFixtures;

    public static function routes(): iterable
    {
        yield ['GET', self::PATH, 'index'];
        yield ['POST', self::PATH, 'store'];
        yield ['POST', self::PATH . '/{id}/status', 'changeStatus'];
    }

    #[DataProvider('routes')]
    public function testRoutesRequireSchoolManagementPermission(string $method, string $path, string $action): void
    {
        $dispatcher = FastRoute\simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php');
        $route = $dispatcher->dispatch($method, $this->path($path));
        self::assertSame(Dispatcher::FOUND, $route[0]);
        self::assertSame(['action' => 'academic.teachingAssignments.' . $action, 'protected' => true,
            'context' => 'SCHOOL', 'permission' => 'TEACHING_ASSIGNMENT_MANAGE'], $route[1]);
    }

    #[DataProvider('routes')]
    public function testAuthenticationContextAndPermissionProtectEveryRoute(string $method, string $path): void
    {
        $path = $this->path($path); $before = $this->snapshot();
        self::assertSame(302, $this->request($method, $path)->status());
        foreach (['SYSTEM_ADMIN', 'SUBJECT_TEACHER', 'HOMEROOM_TEACHER', 'VIEWER', 'EXECUTIVE'] as $role) {
            $this->login($role);
            self::assertSame(403, $this->request($method, $path, $this->payload())->status(), $role);
        }
        foreach ([null, 'bad', [], $this->f['schoolB']] as $school) {
            $this->login(); $_SESSION['school_id'] = $school;
            self::assertContains($this->request($method, $path, $this->payload())->status(), [302, 403]);
        }
        self::assertSame($before, $this->snapshot());
    }

    public function testAdministratorsSeeSchoolHistoryAndSelectedYearChoices(): void
    {
        foreach (['SCHOOL_ADMIN', 'ACADEMIC_ADMIN'] as $role) {
            $this->login($role);
            $all = $this->request('GET'); self::assertSame(200, $all->status()); $this->assertSafe($all);
            $x = $this->xpath($all->body());
            self::assertSame(3, $x->query('//tbody/tr[@data-assignment-id]')->length);
            self::assertSame(0, $x->query('//main//form[@method="post"][@action="' . self::PATH . '"]')->length);
            self::assertEqualsCanonicalizing(array_map('strval', [$this->f['yearA'], $this->f['yearNext'], $this->f['yearClosed']]), $this->optionIds($x, 'academic_year_id'));
            $selected = $this->request('GET', self::PATH, [], ['academic_year_id' => (string) $this->f['yearA']]);
            self::assertSame(200, $selected->status()); $this->assertSafe($selected); $this->assertForms($selected);
            $x = $this->xpath($selected->body());
            self::assertSame(1, $x->query('//tbody/tr[@data-assignment-id]')->length);
            self::assertEqualsCanonicalizing(array_map('strval', [$this->f['teacherAssignment'], $this->f['secondAssignment']]), $this->optionIds($x, 'user_role_assignment_id'));
            self::assertSame([(string) $this->f['offeringA']], $this->optionIds($x, 'subject_offering_id'));
        }
    }

    public static function malformed(): iterable
    {
        foreach ([null, [], ['1'], new stdClass(), true, false, 1.5, '1.5', '1e3', '1abc', '', '0', '-1', '999999999999999999999999'] as $value) { yield [$value]; }
    }

    #[DataProvider('malformed')]
    public function testMalformedTargetsAreSafeAndDoNotWrite(mixed $value): void
    {
        $this->login(); $before = $this->snapshot();
        foreach (['user_role_assignment_id', 'subject_offering_id'] as $field) {
            $r = $this->request('POST', self::PATH, $this->payload([$field => $value]));
            self::assertSame(422, $r->status()); $this->assertSafe($r);
        }
        if ($value !== null) {
            $r = $this->request('GET', self::PATH, [], ['academic_year_id' => $value]);
            self::assertSame(404, $r->status()); $this->assertSafe($r);
        }
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('malformed')]
    public function testInvalidStatusDoesNotWrite(mixed $value): void
    {
        $this->login(); $before = $this->snapshot();
        $r = $this->request('POST', self::PATH . '/' . $this->f['scopeA'] . '/status', $this->payload(['status' => $value]));
        self::assertSame(422, $r->status()); $this->assertSafe($r); self::assertSame($before, $this->snapshot());
    }

    public function testCreateAndStatusTransitionsKeepAuditAndNoOpSemantics(): void
    {
        $this->login(); $before = $this->snapshot();
        self::assertSame(302, $this->request('POST', self::PATH, $this->payload())->status());
        $after = $this->snapshot();
        self::assertCount(count($before['permission_scopes']) + 1, $after['permission_scopes']);
        self::assertCount(count($before['audit_logs']) + 1, $after['audit_logs']);
        $scope = end($after['permission_scopes']);
        self::assertSame($this->f['schoolA'], $scope['school_id']);
        self::assertSame($this->f['yearA'], $scope['academic_year_id']);
        self::assertSame($this->f['secondAssignment'], $scope['user_role_assignment_id']);
        self::assertSame($this->f['offeringA'], $scope['subject_offering_id']);
        self::assertSame(302, $this->request('POST', self::PATH, $this->payload())->status());
        self::assertSame($after, $this->snapshot());
        $path = self::PATH . '/' . $scope['id'] . '/status';
        foreach (['ACTIVE', 'INACTIVE', 'INACTIVE', 'ACTIVE'] as $status) {
            $old = $this->snapshot(); $current = $this->row('permission_scopes', $scope['id'])['status'];
            self::assertSame(302, $this->request('POST', $path, $this->payload(['status' => $status]))->status());
            self::assertSame($status, $this->row('permission_scopes', $scope['id'])['status']);
            self::assertCount(count($old['audit_logs']) + ($current === $status ? 0 : 1), $this->snapshot()['audit_logs']);
        }
        self::assertSame($before['gradebook_components'], $this->snapshot()['gradebook_components']);
        self::assertSame($before['gradebook_scores'], $this->snapshot()['gradebook_scores']);
    }

    public function testCsrfPrecedesParsingServiceAndAllWrites(): void
    {
        $this->login(); $before = $this->snapshot();
        foreach ([self::PATH, self::PATH . '/' . $this->f['scopeA'] . '/status'] as $path) {
            foreach ([null, '', 'invalid', []] as $token) {
                $writes = $this->pdo->writeAttempts;
                $r = $this->request('POST', $path, ['_token' => $token, 'user_role_assignment_id' => [], 'subject_offering_id' => [], 'status' => []]);
                self::assertSame(419, $r->status()); self::assertSame($writes, $this->pdo->writeAttempts);
                self::assertSame($before, $this->snapshot());
            }
        }
    }

    public function testClosedHistoryAndInactiveParentsRemainReadableAndCannotBeReactivated(): void
    {
        $this->login();
        foreach (['permission_scopes' => $this->f['scopeClosed'], 'user_role_assignments' => $this->f['teacherAssignment'],
            'subject_offerings' => $this->f['offeringClosed']] as $table => $id) {
            $this->pdo->prepare("UPDATE {$table} SET status='INACTIVE' WHERE id=?")->execute([$id]);
        }
        $this->pdo->prepare("UPDATE school_memberships SET status='SUSPENDED' WHERE id=?")->execute([$this->users['SUBJECT_TEACHER']['membership']]);
        $this->pdo->exec("UPDATE roles SET status='INACTIVE' WHERE code='SUBJECT_TEACHER'");
        $r = $this->request('GET', self::PATH, [], ['academic_year_id' => $this->f['yearClosed']]);
        self::assertSame(200, $r->status()); $x = $this->xpath($r->body());
        self::assertSame(1, $x->query('//tbody/tr[@data-assignment-id]')->length);
        self::assertStringContainsString('SUBJECT_TEACHER', $r->body());
        self::assertStringContainsString('CLOSED', $r->body());
        self::assertSame(0, $x->query('//main//form[@method="post"]')->length);
        $before = $this->snapshot();
        self::assertSame(422, $this->request('POST', self::PATH, $this->payload(['subject_offering_id' => $this->f['offeringClosed']]))->status());
        foreach (['ACTIVE', 'INACTIVE'] as $status) {
            self::assertSame(422, $this->request('POST', self::PATH . '/' . $this->f['scopeClosed'] . '/status', $this->payload(['status' => $status]))->status());
        }
        self::assertSame($before, $this->snapshot());
    }

    public function testOpenYearInactiveParentsAllowDeactivationButReactivationIsRevalidated(): void
    {
        $this->login();
        $this->pdo->prepare("UPDATE subject_offerings SET status='INACTIVE' WHERE id=?")->execute([$this->f['offeringA']]);
        $this->pdo->prepare("UPDATE user_role_assignments SET status='INACTIVE' WHERE id=?")->execute([$this->f['teacherAssignment']]);
        $path = self::PATH . '/' . $this->f['scopeA'] . '/status';
        $x = $this->xpath($this->request('GET')->body());
        self::assertSame('INACTIVE', $x->evaluate('string(//form[@action="' . $path . '"]//input[@name="status"]/@value)'));
        self::assertSame(302, $this->request('POST', $path, $this->payload())->status());
        $x = $this->xpath($this->request('GET')->body());
        self::assertSame('ACTIVE', $x->evaluate('string(//form[@action="' . $path . '"]//input[@name="status"]/@value)'));
        $before = $this->snapshot();
        self::assertSame(422, $this->request('POST', $path, $this->payload(['status' => 'ACTIVE']))->status());
        self::assertSame($before, $this->snapshot());
    }

    public function testNavigationAndAllRoutesFollowPermissionChanges(): void
    {
        $this->pdo->exec("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code='TEACHING_ASSIGNMENT_MANAGE'");
        $this->login('VIEWER');
        self::assertStringContainsString('href="' . self::PATH . '"', $this->request('GET', '/dashboard')->body());
        self::assertSame(200, $this->request('GET')->status());
        self::assertSame(302, $this->request('POST', self::PATH, $this->payload())->status());
        self::assertSame(302, $this->request('POST', self::PATH . '/' . $this->f['scopeA'] . '/status', $this->payload())->status());
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='SCHOOL_ADMIN' AND p.code='TEACHING_ASSIGNMENT_MANAGE'");
        $this->login(); $before = $this->snapshot();
        self::assertStringNotContainsString(self::PATH, $this->request('GET', '/dashboard')->body());
        foreach (self::routes() as [$method, $path]) { self::assertSame(403, $this->request($method, $this->path($path), $this->payload())->status()); }
        self::assertSame($before, $this->snapshot());
    }

    public function testAllDisplayedLabelsAreEscaped(): void
    {
        $this->login();
        $this->pdo->prepare('UPDATE users SET display_name=? WHERE id=?')->execute([self::HOSTILE, $this->f['teacher']]);
        foreach (['subjects' => 'subjectA', 'classrooms' => 'roomA'] as $table => $key) {
            $this->pdo->prepare("UPDATE {$table} SET code=?,name_th=? WHERE id=?")->execute(['<b>"x"</b>', self::HOSTILE, $this->f[$key]]);
        }
        $r = $this->request('GET', self::PATH, [], ['academic_year_id' => $this->f['yearA']]);
        self::assertSame(200, $r->status()); self::assertStringContainsString(htmlspecialchars(self::HOSTILE, ENT_QUOTES, 'UTF-8'), $r->body());
        self::assertStringContainsString('&lt;b&gt;&quot;x&quot;&lt;/b&gt;', $r->body());
        self::assertStringNotContainsString(self::HOSTILE, $r->body()); self::assertSame(0, $this->xpath($r->body())->query('//script[not(@src)]')->length);
        $this->assertForms($r);
    }

    public function testReadFailureIsGenericAndAuditFailureRollsBack(): void
    {
        $this->login(); $before = $this->snapshot();
        // Target the history read, not the shell's earlier fail-closed authorization query.
        $this->pdo->failPrepare = 'SELECT ps.id, ps.school_id, ps.academic_year_id, ps.user_role_assignment_id';
        $r = $this->request('GET'); self::assertSame(500, $r->status()); self::assertSame('Internal Server Error', $r->body());
        self::assertTrue($this->pdo->failureTriggered);
        $this->pdo->failureTriggered = false; $this->pdo->failPrepare = 'INSERT INTO audit_logs';
        $r = $this->request('POST', self::PATH, $this->payload());
        self::assertSame(422, $r->status()); self::assertTrue($this->pdo->failureTriggered); $this->assertSafe($r);
        self::assertSame($before, $this->snapshot());
    }

    public function testDetailedReadModelHasSafeFieldsAndDeterministicOrder(): void
    {
        $repo = new TeachingAssignmentRepository($this->pdo);
        self::assertTrue(method_exists($repo, 'listDetailedForSchool'));
        $rows = $repo->listDetailedForSchool($this->f['schoolA']);
        self::assertSame([$this->f['scopeNext'], $this->f['scopeA'], $this->f['scopeClosed']], array_column($rows, 'id'));
        $row = $rows[1];
        foreach (['id', 'status', 'academic_year_id', 'year_be', 'academic_year_status', 'subject_offering_id', 'term_no', 'offering_status',
            'classroom_code', 'classroom_name', 'subject_code', 'subject_name', 'user_role_assignment_id', 'user_id', 'teacher_display_name', 'created_at', 'updated_at'] as $field) {
            self::assertArrayHasKey($field, $row);
        }
        self::assertSame('ROOM_A', $row['classroom_code']); self::assertSame('SCI', $row['subject_code']);
        self::assertSame('SUBJECT_TEACHER', $row['teacher_display_name']);
        foreach (['national_id', 'password_hash', 'email'] as $field) { self::assertArrayNotHasKey($field, $row); }
        self::assertSame([$row], $repo->listDetailedForSchool($this->f['schoolA'], $this->f['yearA']));
        self::assertSame([], $repo->listDetailedForSchool($this->f['schoolA'], $this->f['yearB']));
        $scope = $this->row('permission_scopes', $this->f['scopeA']); unset($scope['id'], $scope['created_at'], $scope['updated_at']);
        $second = $this->insert('permission_scopes', array_replace($scope, ['user_role_assignment_id' => $this->f['secondAssignment']]));
        $term2 = $this->insert('permission_scopes', array_replace($scope, ['subject_offering_id' => $this->f['offeringInactive']]));
        self::assertSame([$second, $this->f['scopeA'], $term2], array_column($repo->listDetailedForSchool($this->f['schoolA'], $this->f['yearA']), 'id'));
        $this->pdo->prepare('UPDATE users SET display_name=? WHERE id=?')->execute(['SUBJECT_TEACHER', $this->f['secondTeacher']]);
        self::assertSame([$this->f['scopeA'], $second, $term2], array_column($repo->listDetailedForSchool($this->f['schoolA'], $this->f['yearA']), 'id'));
        // Deliberately insert subject/classroom sort keys out of ID order.
        $subject = $this->insert('subjects', ['school_id' => $this->f['schoolA'], 'code' => 'AAA', 'name_th' => 'Earlier subject']);
        $offering = $this->row('subject_offerings', $this->f['offeringA']);
        unset($offering['id'], $offering['created_at'], $offering['updated_at']);
        $earlySubjectOffering = $this->insert('subject_offerings', array_replace($offering, ['subject_id' => $subject]));
        $earlySubject = $this->insert('permission_scopes', array_replace($scope, ['subject_offering_id' => $earlySubjectOffering]));
        $room = $this->row('classrooms', $this->f['roomA']); unset($room['id'], $room['created_at'], $room['updated_at']);
        $room['code'] = 'AAA';
        $earlyRoomOffering = $this->insert('subject_offerings', array_replace($offering, ['classroom_id' => $this->insert('classrooms', $room)]));
        $earlyRoom = $this->insert('permission_scopes', array_replace($scope, ['subject_offering_id' => $earlyRoomOffering]));
        self::assertSame([$earlyRoom, $earlySubject, $this->f['scopeA'], $second, $term2],
            array_column($repo->listDetailedForSchool($this->f['schoolA'], $this->f['yearA']), 'id'));

    }

    public function testUnsupportedRoutesDoNotMutate(): void
    {
        $this->login(); $before = $this->snapshot();
        self::assertSame(405, $this->request('DELETE')->status());
        self::assertSame(404, $this->request('POST', self::PATH . '/abc/status', $this->payload())->status());
        self::assertSame(422, $this->request('POST', self::PATH . '/999999999999999999999999/status', $this->payload())->status());
        self::assertSame($before, $this->snapshot());
    }
}
