<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/GradebookReadFixtures.php';

final class ClassroomSubjectWorkflowTest extends TestCase
{
    use GradebookReadFixtures;

    private function base(int $offeringId, ?int $roomId = null): string
    {
        return '/hx/workspaces/classrooms/' . ($roomId ?? $this->f['roomA']) . '/subjects/' . $offeringId;
    }

    private function hx(string $method, string $path, array $post = []): App\Http\Response
    {
        return (new Application($this->pdo))->handle(new Request($method, $path, [], $post,
            ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HX_REQUEST' => 'true']));
    }

    private function grantViewer(string $permission): void
    {
        $this->pdo->prepare("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code=?")
            ->execute([$permission]);
    }

    public function testPanelIsOfferingScopedAndReadOnlyAccessDoesNotRevealTeacherManagement(): void
    {
        $this->login('SUBJECT_TEACHER');
        $path = $this->base($this->f['offeringA']);
        $panel = $this->request('GET', $path);
        self::assertSame(200, $panel->status());
        self::assertStringContainsString('วิทยาศาสตร์', $panel->body());
        self::assertStringContainsString('SUBJECT_TEACHER', $panel->body());
        self::assertStringNotContainsString('FOREIGN_SECRET', $panel->body());
        self::assertSame(0, $this->xpath($panel->body())->query('//form')->length);
        self::assertStringNotContainsString('รายการปิดใช้งาน / ประวัติ', $panel->body());
        self::assertSame(404, $this->request('GET', $this->base($this->f['offeringOther']))->status());
        self::assertSame(404, $this->request('GET', $this->base($this->f['offeringB']))->status());
        self::assertSame(404, $this->request('GET', $this->base($this->f['offeringA'], $this->f['roomOther']))->status());
        self::assertSame(200, $this->request('GET', '/workspaces/classrooms/' . $this->f['roomA'] . '/subjects', [],
            ['offering_id' => $this->f['offeringA']])->status());
        $this->revoke('scope');
        self::assertSame(404, $this->request('GET', $path)->status());
    }

    public function testAssignmentStopReactivationCsrfAndAuditStayInDomainService(): void
    {
        $this->login();
        $base = $this->base($this->f['offeringA']);
        $before = $this->readSnapshot();
        self::assertSame(419, $this->hx('POST', $base . '/assignments', [
            '_token' => 'bad', 'user_role_assignment_id' => $this->users['SUBJECT_TEACHER']['assignment']])->status());
        self::assertSame($before, $this->readSnapshot());
        $duplicate = $this->hx('POST', $base . '/assignments', [
            '_token' => $this->token(), 'user_role_assignment_id' => $this->users['SUBJECT_TEACHER']['assignment']]);
        self::assertSame(200, $duplicate->status());
        self::assertSame($before, $this->readSnapshot());
        self::assertStringContainsString('SUBJECT_TEACHER', $duplicate->body());
        $stop = $this->hx('POST', $base . '/assignments/' . $this->f['scopeA'] . '/status',
            ['_token' => $this->token(), 'status' => 'INACTIVE']);
        self::assertSame(200, $stop->status());
        self::assertSame('INACTIVE', $this->row('permission_scopes', $this->f['scopeA'])['status']);
        self::assertCount(count($before['audit_logs']) + 1, $this->readSnapshot()['audit_logs']);
        self::assertStringContainsString('ยังไม่มีครูที่กำลังสอน', $stop->body());
        $reactivate = $this->hx('POST', $base . '/assignments', [
            '_token' => $this->token(), 'user_role_assignment_id' => $this->users['SUBJECT_TEACHER']['assignment']]);
        self::assertSame(200, $reactivate->status());
        self::assertSame('ACTIVE', $this->row('permission_scopes', $this->f['scopeA'])['status']);
        self::assertCount(count($before['audit_logs']) + 2, $this->readSnapshot()['audit_logs']);
        $unchanged = $this->readSnapshot();
        self::assertSame(404, $this->hx('POST', $base . '/assignments/999999999/status',
            ['_token' => $this->token(), 'status' => 'INACTIVE'])->status());
        $otherOfferingAssignment = $this->insert('permission_scopes', [
            'school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'],
            'subject_offering_id' => $this->f['offeringInactive'],
            'user_role_assignment_id' => $this->users['SUBJECT_TEACHER']['assignment'],
            'assigned_by' => $this->users['SCHOOL_ADMIN']['user'],
        ]);
        self::assertSame(404, $this->hx('POST', $base . '/assignments/' . $otherOfferingAssignment . '/status',
            ['_token' => $this->token(), 'status' => 'INACTIVE'])->status());
        self::assertSame('ACTIVE', $this->row('permission_scopes', $otherOfferingAssignment)['status']);
        self::assertCount(count($unchanged['audit_logs']), $this->readSnapshot()['audit_logs']);
    }

    public function testEligibleSecondTeacherCanBeAssignedWithoutReplacingFirst(): void
    {
        $this->login();
        $user = $this->insert('users', ['username' => 'workspace-second-teacher', 'display_name' => 'ครูคนที่สอง',
            'password_hash' => 'private-fixture-hash']);
        $this->insert('school_memberships', ['school_id' => $this->f['schoolA'], 'user_id' => $user]);
        $role = $this->rows("SELECT id FROM roles WHERE code='SUBJECT_TEACHER'")[0]['id'];
        $assignment = $this->insert('user_role_assignments', ['school_id' => $this->f['schoolA'], 'user_id' => $user, 'role_id' => $role]);
        $before = $this->readSnapshot();
        $base = $this->base($this->f['offeringA']);
        $response = $this->hx('POST', $base . '/assignments',
            ['_token' => $this->token(), 'user_role_assignment_id' => $assignment]);
        self::assertSame(200, $response->status());
        self::assertStringContainsString('ครูคนที่สอง', $response->body());
        self::assertStringContainsString('SUBJECT_TEACHER', $response->body());
        self::assertSame('ACTIVE', $this->row('permission_scopes', $this->f['scopeA'])['status']);
        $new = $this->rows('SELECT * FROM permission_scopes WHERE school_id=? AND subject_offering_id=? AND user_role_assignment_id=?',
            [$this->f['schoolA'], $this->f['offeringA'], $assignment]);
        self::assertCount(1, $new);
        self::assertSame('ACTIVE', $new[0]['status']);
        self::assertCount(count($before['audit_logs']) + 1, $this->readSnapshot()['audit_logs']);
        $stop = $this->hx('POST', $base . '/assignments/' . $new[0]['id'] . '/status',
            ['_token' => $this->token(), 'status' => 'INACTIVE']);
        self::assertSame(200, $stop->status());
        self::assertSame('ACTIVE', $this->row('permission_scopes', $this->f['scopeA'])['status']);
        self::assertStringContainsString('SUBJECT_TEACHER', $stop->body());
    }

    public function testScoreItemLifecycleHistoryAndAuthoritativeSummary(): void
    {
        $this->login();
        $base = $this->base($this->f['offeringA']);
        $before = $this->readSnapshot();
        $invalid = $this->hx('POST', $base . '/components', ['_token' => $this->token(), 'name_th' => 'สอบย่อย', 'max_score' => '0']);
        self::assertSame(422, $invalid->status());
        self::assertStringNotContainsString('SQLSTATE', $invalid->body());
        self::assertSame('สอบย่อย', $this->xpath($invalid->body())->evaluate('string(//input[@id="subject-new-score-name"]/@value)'));
        self::assertSame('0', $this->xpath($invalid->body())->evaluate('string(//input[@id="subject-new-score-max"]/@value)'));
        self::assertSame($before, $this->readSnapshot());
        $created = $this->hx('POST', $base . '/components', ['_token' => $this->token(), 'name_th' => 'สอบย่อย', 'max_score' => '7.25']);
        self::assertSame(200, $created->status());
        self::assertStringContainsString('3 รายการ · คะแนนเต็มรวม 42.75', $created->body());
        $item = $this->rows("SELECT * FROM gradebook_components WHERE subject_offering_id=? AND name_th='สอบย่อย'", [$this->f['offeringA']])[0];
        self::assertMatchesRegularExpression('/^SCORE[0-9]+$/', $item['code']);
        self::assertCount(count($before['audit_logs']) + 1, $this->readSnapshot()['audit_logs']);
        $update = $this->hx('POST', $base . '/components/' . $item['id'],
            ['_token' => $this->token(), 'name_th' => 'สอบย่อยแก้ไข', 'max_score' => '8']);
        self::assertSame(200, $update->status());
        self::assertSame('8.00', $this->row('gradebook_components', $item['id'])['max_score']);
        self::assertStringContainsString('43.50', $update->body());
        $deactivate = $this->hx('POST', $base . '/components/' . $item['id'] . '/status',
            ['_token' => $this->token(), 'status' => 'INACTIVE']);
        self::assertSame(200, $deactivate->status());
        self::assertStringContainsString('2 รายการ · คะแนนเต็มรวม 35.50', $deactivate->body());
        self::assertSame('INACTIVE', $this->row('gradebook_components', $item['id'])['status']);
        self::assertSame(200, $this->hx('POST', $base . '/components/' . $item['id'] . '/status',
            ['_token' => $this->token(), 'status' => 'ACTIVE'])->status());
        $history = $this->readSnapshot();
        self::assertSame(422, $this->hx('POST', $base . '/components/' . $this->f['componentA'],
            ['_token' => $this->token(), 'name_th' => 'สอบ', 'max_score' => '25'])->status());
        self::assertSame($history, $this->readSnapshot());
        self::assertSame(200, $this->hx('POST', $base . '/components/' . $this->f['componentA'],
            ['_token' => $this->token(), 'name_th' => 'สอบแก้ไข', 'max_score' => '20.00'])->status());
    }

    public function testPlainPostReturnsToSelectedOfferingWithoutHtmx(): void
    {
        $this->login();
        $response = $this->request('POST', $this->base($this->f['offeringA']) . '/components',
            ['_token' => $this->token(), 'name_th' => 'งานในห้อง', 'max_score' => '3.50']);
        self::assertSame(302, $response->status());
        $location = (new ReflectionProperty($response, 'headers'))->getValue($response)['Location'];
        self::assertSame('/workspaces/classrooms/' . $this->f['roomA'] . '/subjects?offering_id=' . $this->f['offeringA'], $location);
        $page = $this->request('GET', '/workspaces/classrooms/' . $this->f['roomA'] . '/subjects', [],
            ['offering_id' => $this->f['offeringA']]);
        self::assertSame(200, $page->status());
        self::assertStringContainsString('งานในห้อง', $page->body());
        self::assertSame(1, $this->xpath($page->body())->query('//*[@id="subject-context-heading"]')->length);
    }

    public function testWrongResourcesStaleClassroomAndPermissionRevocationDoNotWrite(): void
    {
        $this->login();
        $base = $this->base($this->f['offeringA']);
        $before = $this->readSnapshot();
        self::assertSame(404, $this->hx('POST', $base . '/components/' . $this->f['componentOther'],
            ['_token' => $this->token(), 'name_th' => 'x', 'max_score' => '1'])->status());
        self::assertSame(404, $this->hx('POST', $this->base($this->f['offeringB']) . '/components',
            ['_token' => $this->token(), 'name_th' => 'x', 'max_score' => '1'])->status());
        self::assertSame(422, $this->hx('POST', $this->base($this->f['offeringInactive']) . '/components',
            ['_token' => $this->token(), 'name_th' => 'x', 'max_score' => '1'])->status());
        self::assertSame(422, $this->hx('POST', $this->base($this->f['offeringClosed'], $this->f['roomClosed']) . '/components',
            ['_token' => $this->token(), 'name_th' => 'x', 'max_score' => '1'])->status());
        self::assertSame($before, $this->readSnapshot());
        $this->pdo->prepare('UPDATE subject_offerings SET classroom_id=? WHERE id=?')->execute([$this->f['roomOther'], $this->f['offeringA']]);
        $stale = $this->readSnapshot();
        self::assertSame(404, $this->hx('POST', $base . '/assignments',
            ['_token' => $this->token(), 'user_role_assignment_id' => $this->users['SUBJECT_TEACHER']['assignment']])->status());
        self::assertSame($stale, $this->readSnapshot());
    }

    public function testMoveBetweenPreflightAndOfferingLockIsRejectedAtomically(): void
    {
        $this->login();
        $base = $this->base($this->f['offeringA']);
        $fired = false;
        $this->pdo->beforeLock = function (string $sql) use (&$fired): void {
            if (!$fired && str_contains($sql, 'FROM subject_offerings o') && str_contains($sql, 'FOR UPDATE')) {
                $fired = true;
                $this->pdo->beforeLock = null;
                $this->pdo->prepare('UPDATE subject_offerings SET classroom_id=? WHERE id=?')
                    ->execute([$this->f['roomOther'], $this->f['offeringA']]);
            }
        };
        $before = $this->readSnapshot();
        self::assertSame(422, $this->hx('POST', $base . '/components',
            ['_token' => $this->token(), 'name_th' => 'แข่ง', 'max_score' => '5'])->status());
        self::assertTrue($fired);
        self::assertSame($before, $this->readSnapshot());
    }

    public function testReadPermissionAndWritePermissionsRemainIndependent(): void
    {
        $this->grantViewer('ACADEMIC_SETUP_VIEW');
        $this->login('VIEWER');
        $base = $this->base($this->f['offeringA']);
        $read = $this->request('GET', $base);
        self::assertSame(200, $read->status());
        self::assertStringNotContainsString('SUBJECT_TEACHER', $read->body());
        self::assertSame(0, $this->xpath($read->body())->query('//form')->length);
        $before = $this->readSnapshot();
        self::assertSame(403, $this->hx('POST', $base . '/assignments',
            ['_token' => $this->token(), 'user_role_assignment_id' => $this->users['SUBJECT_TEACHER']['assignment']])->status());
        self::assertSame(403, $this->hx('POST', $base . '/components',
            ['_token' => $this->token(), 'name_th' => 'x', 'max_score' => '1'])->status());
        self::assertSame($before, $this->readSnapshot());
        $this->grantViewer('GRADEBOOK_COMPONENT_MANAGE');
        self::assertGreaterThan(0, $this->xpath($this->request('GET', $base)->body())->query('//form[contains(@action,"/components")]')->length);
        $this->pdo->prepare("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='VIEWER' AND p.code='GRADEBOOK_COMPONENT_MANAGE'")->execute();
        self::assertSame(403, $this->hx('POST', $base . '/components',
            ['_token' => $this->token(), 'name_th' => 'x', 'max_score' => '1'])->status());
        self::assertSame($before, $this->readSnapshot());
    }

    public function testConcurrentStatusChangeRollsBackWithoutFalseSuccess(): void
    {
        $this->login();
        $base = $this->base($this->f['offeringA']);
        $before = $this->readSnapshot();
        $this->pdo->beforeLock = function (string $sql): void {
            if (str_contains($sql, 'FROM permission_scopes') && str_contains($sql, 'FOR UPDATE')) {
                $this->pdo->beforeLock = null;
                $this->pdo->prepare("UPDATE permission_scopes SET status='INACTIVE' WHERE id=?")->execute([$this->f['scopeA']]);
            }
        };
        self::assertSame(422, $this->hx('POST', $base . '/assignments/' . $this->f['scopeA'] . '/status',
            ['_token' => $this->token(), 'status' => 'INACTIVE'])->status());
        self::assertSame($before, $this->readSnapshot());
        $this->pdo->beforeLock = function (string $sql): void {
            if (str_contains($sql, 'FROM gradebook_components') && str_contains($sql, 'FOR UPDATE')) {
                $this->pdo->beforeLock = null;
                $this->pdo->prepare("UPDATE gradebook_components SET status='INACTIVE' WHERE id=?")->execute([$this->f['componentSecond']]);
            }
        };
        self::assertSame(422, $this->hx('POST', $base . '/components/' . $this->f['componentSecond'] . '/status',
            ['_token' => $this->token(), 'status' => 'INACTIVE'])->status());
        self::assertSame($before, $this->readSnapshot());
    }
}
