<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__) . '/Support/GradebookReadFixtures.php';

final class ClassroomRosterTest extends TestCase
{
    use GradebookReadFixtures;

    private function rosterPath(string $room = 'A'): string
    {
        return '/workspaces/classrooms/' . $this->f['room' . $room] . '/students';
    }

    private function grant(string $permission): void
    {
        $this->pdo->prepare("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code=?")->execute([$permission]);
    }

    private function codes(string $body): array
    {
        return array_map(static fn ($node) => trim($node->textContent), iterator_to_array(
            $this->xpath($body)->query('//table[@id="classroom-roster"]/tbody/tr/td[1]')));
    }

    public function testRosterShowsOnlyCurrentRowsWithAuthoritativeContextAndActiveNavigation(): void
    {
        $this->login();
        $before = $this->readSnapshot();
        $response = $this->request('GET', $this->rosterPath());
        self::assertSame(200, $response->status());
        self::assertSame(['01-CURRENT', '02-ZERO', '03-NULL', '04-COMPLETE'], $this->codes($response->body()));
        $x = $this->xpath($response->body());
        self::assertSame(1, $x->query('//main')->length);
        self::assertSame(1, $x->query('//h1')->length);
        self::assertSame('นักเรียน', $x->query('//nav[@aria-label="งานในห้องเรียน"]//a[@aria-current="page"]')->item(0)->textContent);
        self::assertSame(4, $x->query('//table[@id="classroom-roster"]//th[@scope="row"]')->length);
        foreach (['โรงเรียนทดสอบ', 'ปีการศึกษา 2569', 'ห้องทดสอบ', 'รายชื่อนักเรียน <span>4 คน</span>'] as $label) {
            self::assertStringContainsString($label, $response->body());
        }
        self::assertSame(4, $x->query('//table[@id="classroom-roster"]//details[@data-student-detail]/summary')->length);
        self::assertSame(4, $x->query('//table[@id="classroom-roster"]//details[@data-student-detail]//a[starts-with(@href,"/students/")]')->length);
        $forged = ['school_id' => $this->f['schoolB'], 'academic_year_id' => $this->f['yearB'],
            'classroom_id' => $this->f['roomB'], 'workspace_classroom_id' => $this->f['roomB'], 'grade_level_id' => 999];
        self::assertSame($response->body(), $this->request('GET', $this->rosterPath(), $forged, $forged)->body());
        self::assertSame($before, $this->readSnapshot());
    }

    public function testForeignMissingMalformedAndSystemLocatorsFailSafely(): void
    {
        self::assertSame(302, $this->request('GET', $this->rosterPath())->status());
        $this->login();
        $missing = $this->request('GET', '/workspaces/classrooms/0/students');
        self::assertSame(404, $missing->status());
        foreach ([$this->f['roomB'], PHP_INT_MAX, 'abc', '-1', '999999999999999999999999999'] as $id) {
            $response = $this->request('GET', '/workspaces/classrooms/' . $id . '/students');
            self::assertSame(404, $response->status());
            self::assertSame($missing->body(), $response->body());
        }
        self::assertSame(405, $this->request('POST', $this->rosterPath())->status());
        $this->login('SYSTEM_ADMIN');
        self::assertSame(404, $this->request('GET', $this->rosterPath())->status());
    }

    public function testReadOnlyAndLiveRevocationDoNotGrantMutationOrImportAuthority(): void
    {
        $this->grant('STUDENT_VIEW');
        $this->login('VIEWER');
        $response = $this->request('GET', $this->rosterPath());
        self::assertSame(200, $response->status());
        self::assertCount(4, $this->codes($response->body()));
        $x = $this->xpath($response->body());
        self::assertSame(4, $x->query('//table[@id="classroom-roster"]//a[starts-with(@href,"/students/")]')->length);
        self::assertSame(0, $x->query('//main//a[contains(@href,"/edit") or contains(@href,"/create") or contains(@href,"student-import")]')->length);
        self::assertSame(403, $this->request('GET', '/academic/enrollments/' . $this->f['enrollment_current'] . '/edit')->status());
        self::assertSame(403, $this->request('GET', '/academic/student-import')->status());
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='VIEWER' AND p.code='STUDENT_VIEW'");
        self::assertSame(404, $this->request('GET', $this->rosterPath())->status());
    }

    public function testGradebookAuthorityAloneNeverLoadsRosterOrShowsItsLink(): void
    {
        $this->login('SUBJECT_TEACHER');
        $this->pdo->queries = [];
        self::assertSame(404, $this->request('GET', $this->rosterPath())->status());
        $body = $this->request('GET', '/workspaces/classrooms/' . $this->f['roomA'])->body();
        self::assertStringNotContainsString($this->rosterPath(), $body);
        self::assertStringNotContainsString('01-CURRENT', $body);
        self::assertDoesNotMatchRegularExpression('/\b(students|student_enrollments|student_classroom_placements)\b/i', implode("\n", $this->pdo->queries));
    }

    public function testPrivacyEscapingAndConstantQueryCount(): void
    {
        $this->login();
        $student = $this->row('student_enrollments', $this->f['enrollment_current'])['student_id'];
        $hostile = '<img src=x onerror=alert(1)>';
        $this->pdo->prepare('UPDATE students SET first_name_th=?,birth_date=? WHERE id=?')->execute([$hostile, '2015-01-02', $student]);
        $this->pdo->queries = [];
        $response = $this->request('GET', $this->rosterPath());
        self::assertSame(200, $response->status());
        $baseline = count($this->pdo->queries);
        self::assertDoesNotMatchRegularExpression('/\b(national_id|birth_date|gradebook_scores|gradebook_components)\b/i', implode("\n", $this->pdo->queries));
        foreach ([self::NATIONAL_MARKER, '2015-01-02', $hostile] as $secret) { self::assertStringNotContainsString($secret, $response->body()); }
        self::assertStringContainsString(htmlspecialchars($hostile, ENT_QUOTES, 'UTF-8'), $response->body());
        for ($i = 0; $i < 12; ++$i) { $this->student('EXTRA-' . $i); }
        $this->pdo->queries = [];
        self::assertCount(16, $this->codes($this->request('GET', $this->rosterPath())->body()));
        self::assertCount($baseline, $this->pdo->queries);
    }

    public function testClosedYearInactiveStudentAndInactiveClassroomPreserveReadableHistory(): void
    {
        $this->login();
        $id = $this->student('CLOSED-HISTORY', 'Closed', 'Closed');
        $student = $this->row('student_enrollments', $id)['student_id'];
        $this->pdo->prepare("UPDATE students SET status='INACTIVE' WHERE id=?")->execute([$student]);
        $this->pdo->prepare("UPDATE classrooms SET status='INACTIVE' WHERE id=?")->execute([$this->f['roomClosed']]);
        $before = $this->readSnapshot();
        $response = $this->request('GET', $this->rosterPath('Closed'));
        self::assertSame(200, $response->status());
        self::assertSame(['CLOSED-HISTORY'], $this->codes($response->body()));
        self::assertStringContainsString('ปิดปีแล้ว', $response->body());
        self::assertSame(0, $this->xpath($response->body())->query('//main//a[contains(@href,"/edit") or contains(@href,"/create") or contains(@href,"student-import")]')->length);
        self::assertSame($before, $this->readSnapshot());
    }

    public function testEmptyRoomIsAReadableState(): void
    {
        $this->login();
        $this->pdo->prepare("UPDATE student_classroom_placements SET status='ENDED', ended_at=CURRENT_TIMESTAMP WHERE classroom_id=?")->execute([$this->f['roomA']]);
        $response = $this->request('GET', $this->rosterPath());
        self::assertSame(200, $response->status());
        self::assertSame([], $this->codes($response->body()));
        self::assertStringContainsString('ยังไม่มีนักเรียนที่กำลังเรียนและจัดอยู่ในห้องนี้', $response->body());
    }

    public function testManageAndImportEntryPointsUseExistingAuthorizedForms(): void
    {
        $this->login();
        $body = $this->request('GET', $this->rosterPath())->body();
        $x = $this->xpath($body);
        self::assertSame(4, $x->query('//table//a[contains(@href,"#move-classroom")]')->length);
        self::assertSame(4, $x->query('//table//a[contains(@href,"#student-status")]')->length);
        self::assertSame(0, $x->query('//main//form')->length);
        $id = $this->f['enrollment_current'];
        $form = $this->request('GET', '/academic/enrollments/' . $id . '/edit');
        self::assertSame(200, $form->status());
        $f = $this->xpath($form->body());
        foreach (['move-classroom' => 'placement', 'student-status' => 'status'] as $anchor => $action) {
            self::assertSame(1, $f->query('//section[@id="' . $anchor . '"]//form[@method="post" and @action="/academic/enrollments/' . $id . '/' . $action . '"]//input[@name="_token"]')->length);
        }
        self::assertStringContainsString('01-CURRENT', $form->body());
        self::assertSame(200, $this->request('GET', '/academic/student-import')->status());
        self::assertSame(1, $x->query('//main//a[@href="/academic/student-import"]')->length);
        $this->grant('STUDENT_VIEW'); $this->grant('ENROLLMENT_MANAGE');
        $this->login('VIEWER');
        $body = $this->request('GET', $this->rosterPath())->body();
        self::assertStringContainsString('#move-classroom', $body);
        self::assertStringNotContainsString('href="/academic/student-import"', $body);
        $this->grant('STUDENT_IMPORT');
        self::assertStringContainsString('href="/academic/student-import"', $this->request('GET', $this->rosterPath())->body());
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='VIEWER' AND p.code IN ('ENROLLMENT_MANAGE','STUDENT_IMPORT')");
        $body = $this->request('GET', $this->rosterPath())->body();
        self::assertStringNotContainsString('#move-classroom', $body);
        self::assertStringNotContainsString('href="/academic/student-import"', $body);
        self::assertSame(403, $this->request('POST', '/academic/enrollments/' . $id . '/placement', ['_token' => $this->token(), 'classroom_id' => $this->f['roomOther']])->status());
    }

    private function postEnrollment(string $action, array $values = [], string $key = 'current'): App\Http\Response
    {
        return $this->request('POST', '/academic/enrollments/' . $this->f['enrollment_' . $key] . '/' . $action,
            array_replace(['_token' => $this->token()], $values));
    }

    private function studentState(): array
    {
        return $this->readSnapshot() + ['students' => $this->rows('SELECT * FROM students ORDER BY id')];
    }

    private function assertAudit(array $audit, string $action, array $old, array $new): void
    {
        self::assertSame($action, $audit['action']);
        self::assertSame($this->users['SCHOOL_ADMIN']['user'], $audit['user_id']);
        self::assertSame($this->f['schoolA'], $audit['school_id']);
        self::assertSame('student_enrollments', $audit['entity_type']);
        self::assertSame($this->f['enrollment_current'], $audit['entity_id']);
        self::assertSame($old, json_decode($audit['old_value'], true));
        self::assertSame($new, json_decode($audit['new_value'], true));
        self::assertSame('127.0.0.1', $audit['ip_address']);
        self::assertNotEmpty($audit['created_at']);
    }

    public function testLegacyMoveFromRosterPreservesMasterHistoryScoresAndAudit(): void
    {
        $this->login();
        $before = $this->studentState();
        self::assertStringContainsString('#move-classroom', $this->request('GET', $this->rosterPath())->body());
        $response = $this->postEnrollment('placement', ['classroom_id' => $this->f['roomOther']]);
        self::assertSame(302, $response->status());
        self::assertNotContains('01-CURRENT', $this->codes($this->request('GET', $this->rosterPath())->body()));
        self::assertContains('01-CURRENT', $this->codes($this->request('GET', $this->rosterPath('Other'))->body()));
        $history = $this->rows('SELECT * FROM student_classroom_placements WHERE enrollment_id=? ORDER BY id', [$this->f['enrollment_current']]);
        self::assertSame(['ENDED', 'ACTIVE'], array_column($history, 'status'));
        self::assertSame([$this->f['roomA'], $this->f['roomOther']], array_column($history, 'classroom_id'));
        self::assertNotNull($history[0]['ended_at']);
        self::assertNull($history[1]['ended_at']);
        $after = $this->studentState();
        foreach (['students', 'enrollments', 'gradebook_scores'] as $table) { self::assertSame($before[$table], $after[$table]); }
        $new = array_slice($after['audit_logs'], count($before['audit_logs']));
        self::assertCount(1, $new);
        $this->assertAudit($new[0], 'STUDENT_CLASSROOM_PLACEMENT_CHANGED', ['classroom_id' => $this->f['roomA']], ['classroom_id' => $this->f['roomOther']]);
        $this->postEnrollment('placement', ['classroom_id' => $this->f['roomA']]);
        self::assertSame(['ENDED', 'ENDED', 'ACTIVE'], array_column($this->rows('SELECT * FROM student_classroom_placements WHERE enrollment_id=? ORDER BY id', [$this->f['enrollment_current']]), 'status'));
    }

    public static function terminalStatuses(): array { return [['TRANSFERRED_OUT'], ['WITHDRAWN']]; }

    #[DataProvider('terminalStatuses')]
    public function testLegacyStatusEndsCurrentRosterButRetainsHistoryAndAudit(string $status): void
    {
        $this->login();
        $before = $this->studentState();
        self::assertSame(302, $this->postEnrollment('status', ['status' => $status, 'exit_date' => '2026-06-01'])->status());
        self::assertNotContains('01-CURRENT', $this->codes($this->request('GET', $this->rosterPath())->body()));
        $after = $this->studentState();
        self::assertSame($before['students'], $after['students']);
        self::assertSame($before['gradebook_scores'], $after['gradebook_scores']);
        $history = $this->rows('SELECT * FROM student_classroom_placements WHERE enrollment_id=?', [$this->f['enrollment_current']]);
        self::assertCount(1, $history); self::assertSame('ENDED', $history[0]['status']); self::assertNotNull($history[0]['ended_at']);
        $audits = array_slice($after['audit_logs'], count($before['audit_logs']));
        self::assertCount(2, $audits);
        $this->assertAudit($audits[0], 'STUDENT_ENROLLMENT_STATUS_CHANGED', ['status' => 'ACTIVE', 'exit_date' => null], ['status' => $status, 'exit_date' => '2026-06-01']);
        $this->assertAudit($audits[1], 'STUDENT_CLASSROOM_PLACEMENT_CHANGED', ['classroom_id' => $this->f['roomA']], ['classroom_id' => null]);
        $form = $this->request('GET', '/academic/enrollments/' . $this->f['enrollment_current'] . '/edit');
        self::assertSame(200, $form->status());
        self::assertSame(0, $this->xpath($form->body())->query('//main//form')->length);
        self::assertSame(422, $this->postEnrollment('status', ['status' => 'ACTIVE'])->status());
        self::assertSame(422, $this->postEnrollment('placement', ['classroom_id' => $this->f['roomOther']])->status());
        self::assertSame($after, $this->studentState());
    }

    public static function rejectedMoves(): array
    {
        return [['foreign'], ['year'], ['grade'], ['inactive'], ['csrf'], ['permission'], ['closed'], ['foreignEnrollment']];
    }

    #[DataProvider('rejectedMoves')]
    public function testLegacyMoveRejectsUnsafeTargetsWithoutPartialState(string $case): void
    {
        $this->login();
        $room = $this->f['roomOther']; $expected = 422; $key = 'current';
        if ($case === 'foreign') { $room = $this->f['roomB']; }
        if ($case === 'year') { $room = $this->f['roomNext']; }
        if ($case === 'grade') {
            $grade = $this->rows("SELECT id FROM grade_levels WHERE code='P2'")[0]['id'];
            $room = $this->insert('classrooms', ['school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'], 'grade_level_id' => $grade, 'code' => 'OTHER-GRADE', 'name_th' => 'อื่น']);
        }
        if ($case === 'inactive') { $this->pdo->prepare("UPDATE classrooms SET status='INACTIVE' WHERE id=?")->execute([$room]); }
        if ($case === 'closed') { $this->pdo->prepare("UPDATE academic_years SET status='CLOSED' WHERE id=?")->execute([$this->f['yearA']]); }
        if ($case === 'permission') { $this->grant('STUDENT_VIEW'); $this->login('VIEWER'); $expected = 403; }
        if ($case === 'csrf') { $expected = 419; }
        if ($case === 'foreignEnrollment') { $key = 'foreign'; }
        $before = $this->studentState();
        $values = ['classroom_id' => $room];
        if ($case === 'csrf') { $values['_token'] = 'invalid'; }
        self::assertSame($expected, $this->postEnrollment('placement', $values, $key)->status());
        self::assertSame($before, $this->studentState());
    }

    public static function rejectedStatuses(): array { return [['csrf'], ['permission'], ['closed'], ['invalid'], ['date']]; }

    #[DataProvider('rejectedStatuses')]
    public function testLegacyStatusDenialsAreAtomic(string $case): void
    {
        $this->login(); $expected = 422;
        $values = ['status' => 'WITHDRAWN', 'exit_date' => '2026-06-01'];
        if ($case === 'csrf') { $values['_token'] = 'invalid'; $expected = 419; }
        if ($case === 'permission') { $this->grant('STUDENT_VIEW'); $this->login('VIEWER'); $expected = 403; }
        if ($case === 'closed') { $this->pdo->prepare("UPDATE academic_years SET status='CLOSED' WHERE id=?")->execute([$this->f['yearA']]); }
        if ($case === 'invalid') { $values['status'] = 'INACTIVE'; }
        if ($case === 'date') { $values['exit_date'] = ''; }
        $before = $this->studentState();
        self::assertSame($expected, $this->postEnrollment('status', $values)->status());
        self::assertSame($before, $this->studentState());
    }

    public static function writeFailures(): array
    {
        return [['placement', 'INSERT INTO student_classroom_placements'], ['placement', 'INSERT INTO audit_logs'],
            ['status', 'UPDATE student_enrollments'], ['status', 'INSERT INTO audit_logs']];
    }

    #[DataProvider('writeFailures')]
    public function testExistingTransactionRollsBackEarlierWritesOnInducedFailure(string $action, string $failure): void
    {
        $this->login();
        $before = $this->studentState();
        $this->pdo->failPrepare = $failure;
        $response = $this->postEnrollment($action, ['classroom_id' => $this->f['roomOther'], 'status' => 'WITHDRAWN', 'exit_date' => '2026-06-01']);
        self::assertSame(422, $response->status());
        self::assertNull($this->pdo->failPrepare, 'Failure injection must fire');
        self::assertSame($before, $this->studentState());
        $this->assertSafe($response->body());
    }

    public function testInactiveSourceRemainsReadableAndOffersOnlyExistingSafeExitActions(): void
    {
        $this->login();
        $this->pdo->prepare("UPDATE classrooms SET status='INACTIVE' WHERE id=?")->execute([$this->f['roomA']]);
        $body = $this->request('GET', $this->rosterPath())->body();
        self::assertCount(4, $this->codes($body));
        self::assertStringContainsString('#move-classroom', $body);
        self::assertSame(0, $this->xpath($body)->query('//main//a[contains(@href,"/create") or contains(@href,"student-import")]')->length);
        self::assertSame(302, $this->postEnrollment('placement', ['classroom_id' => $this->f['roomOther']])->status());
    }

    public function testReadFailureIsGenericAndDoesNotChangeState(): void
    {
        $this->login();
        $before = $this->studentState();
        $this->pdo->failPrepare = 'FROM student_enrollments e';
        $response = $this->request('GET', $this->rosterPath());
        self::assertSame(500, $response->status());
        self::assertSame('Internal Server Error', $response->body());
        self::assertSame($before, $this->studentState());
    }

    public function testReadModelRechecksAuthorityAndOnlyProjectsRosterFields(): void
    {
        $authorization = new App\Services\AuthorizationService(new App\Repositories\AuthorizationRepository($this->pdo));
        $workspaces = new App\Services\ClassroomWorkspaceReadService(new App\Repositories\ClassroomRepository($this->pdo),
            new App\Repositories\SchoolRepository($this->pdo), $authorization, $this->readService());
        $service = new App\Services\ClassroomRosterReadService($workspaces, new App\Repositories\StudentEnrollmentRepository($this->pdo), $authorization);
        $args = [$this->users['VIEWER']['user'], 'SCHOOL', $this->f['schoolA'], $this->f['roomA']];
        self::assertNull($service->getRoster(...$args));
        $this->grant('STUDENT_VIEW');
        $model = $service->getRoster(...$args);
        self::assertCount(4, $model['students']);
        self::assertSame(['studentId', 'enrollmentId', 'code', 'name', 'status'], array_keys($model['students'][0]));
        self::assertFalse($model['canManage']); self::assertFalse($model['canImport']);
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='VIEWER' AND p.code='STUDENT_VIEW'");
        self::assertNull($service->getRoster(...$args));
        $this->pdo->queries = [];
        foreach ([[0, 'SCHOOL', 1, 1], [1, 'SYSTEM', 1, 1], [1, 'SCHOOL', 0, 1], [1, 'SCHOOL', 1, 0]] as $invalid) {
            self::assertNull($service->getRoster(...$invalid));
        }
        self::assertSame([], $this->pdo->queries);
    }

    public function testTerminalEnrollmentsCannotAppearEvenWithLingeringActivePlacement(): void
    {
        $this->login();
        foreach (['TRANSFERRED_OUT', 'WITHDRAWN'] as $status) { $this->student('TERMINAL-' . $status, 'A', 'A', $status); }
        self::assertSame(['01-CURRENT', '02-ZERO', '03-NULL', '04-COMPLETE'], $this->codes($this->request('GET', $this->rosterPath())->body()));
    }
}
