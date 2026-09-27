<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/GradebookReadFixtures.php';

final class ClassroomSubjectsTest extends TestCase
{
    use GradebookReadFixtures;

    private function path(string $room = 'A'): string
    {
        return '/workspaces/classrooms/' . $this->f['room' . $room] . '/subjects';
    }

    private function offeringIds(string $body): array
    {
        return array_map('intval', array_map(static fn ($node): string => $node->nodeValue,
            iterator_to_array($this->xpath($body)->query('//table[@id="classroom-subjects"]//tr/@data-offering-id'))));
    }

    private function grant(string $permission): void
    {
        $this->pdo->prepare("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code=?")->execute([$permission]);
    }

    private function revokeViewer(string $permission): void
    {
        $this->pdo->prepare("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='VIEWER' AND p.code=?")
            ->execute([$permission]);
    }

    public function testClassroomOfferingsAreSeparateByTermWithTeacherAndContextualActions(): void
    {
        $this->login();
        $second = $this->insert('subject_offerings', ['school_id' => $this->f['schoolA'],
            'academic_year_id' => $this->f['yearA'], 'classroom_id' => $this->f['roomA'],
            'subject_id' => $this->f['subjectA'], 'term_no' => 2]);
        $before = $this->readSnapshot();
        $this->pdo->queries = [];
        $response = $this->request('GET', $this->path());
        self::assertSame(200, $response->status());
        self::assertSame([$this->f['offeringA'], $second, $this->f['offeringInactive']], $this->offeringIds($response->body()));
        self::assertStringContainsString('แต่ละภาคเรียนเป็นรายการแยกกัน', $response->body());
        $x = $this->xpath($response->body());
        self::assertSame('รายวิชาและครู', $x->query('//nav[@aria-label="งานในห้องเรียน"]//a[@aria-current="page"]')->item(0)->textContent);
        self::assertSame(1, $x->query('//tr[@data-offering-id="' . $this->f['offeringA'] . '"]//form[starts-with(@action,"/academic/teaching-assignments?")]//input[@name="subject_offering_id"]')->length);
        self::assertSame(1, $x->query('//tr[@data-offering-id="' . $this->f['offeringA'] . '"]//form[starts-with(@action,"/academic/teaching-assignments/' . $this->f['scopeA'] . '/status?")]')->length);
        self::assertSame(1, $x->query('//tr[@data-offering-id="' . $this->f['offeringA'] . '"]//a[@href="/gradebook/' . $this->f['offeringA'] . '/setup"]')->length);
        self::assertSame(1, $x->query('//tr[@data-offering-id="' . $this->f['offeringA'] . '"]//a[@href="/gradebook/' . $this->f['offeringA'] . '"]')->length);
        self::assertSame(1, $x->query('//main//a[@href="/academic/subjects/create"]')->length);
        self::assertSame(0, $x->query('//tr[@data-offering-id="' . $this->f['offeringInactive'] . '"]//form[@action="/academic/teaching-assignments"]')->length);
        self::assertSame($before, $this->readSnapshot());
        self::assertCount(1, array_filter($this->pdo->queries,
            static fn (string $sql): bool => str_contains($sql, 'FROM permission_scopes ps') && str_contains($sql, 'subject_offering_id IN')));
        $this->assertReadSafe($response->body());
        foreach (['ชั่วโมงต่อปี', 'ชั่วโมงต่อสัปดาห์', 'หน่วยกิต', 'ภาระงานครู', 'หมวดหลักสูตร'] as $invented) {
            self::assertStringNotContainsString($invented, $response->body());
        }
    }

    public function testScopedTeacherSeesOnlyLiveOfferingAndNoManagementActions(): void
    {
        $this->login('SUBJECT_TEACHER');
        $this->pdo->queries = [];
        $response = $this->request('GET', $this->path());
        self::assertSame(200, $response->status());
        self::assertSame([$this->f['offeringA']], $this->offeringIds($response->body()));
        $x = $this->xpath($response->body());
        self::assertSame(0, $x->query('//main//form')->length);
        self::assertSame(0, $x->query('//main//a[contains(@href,"/academic/") or contains(@href,"/setup")]')->length);
        self::assertSame(1, $x->query('//main//a[@href="/gradebook/' . $this->f['offeringA'] . '"]')->length);
        self::assertDoesNotMatchRegularExpression('/\b(students|student_enrollments|student_classroom_placements|gradebook_scores|national_id|birth_date)\b/i', implode("\n", $this->pdo->queries));
        $this->revoke('scope');
        self::assertSame(404, $this->request('GET', $this->path())->status());
        self::assertSame(403, $this->request('POST', '/academic/teaching-assignments',
            ['_token' => $this->token(), 'user_role_assignment_id' => $this->users['SUBJECT_TEACHER']['assignment'],
                'subject_offering_id' => $this->f['offeringA']])->status());
    }

    public function testIndividualSetupAndOfferingPermissionsControlRowsAndActions(): void
    {
        $this->login('VIEWER');
        self::assertSame(404, $this->request('GET', $this->path())->status());
        $this->grant('ACADEMIC_SETUP_VIEW');
        $read = $this->request('GET', $this->path());
        self::assertSame(200, $read->status());
        self::assertSame([$this->f['offeringA'], $this->f['offeringInactive']], $this->offeringIds($read->body()));
        self::assertSame(0, $this->xpath($read->body())->query('//main//form | //main//a[contains(@href,"/edit") or contains(@href,"/create") or contains(@href,"/setup")]')->length);
        self::assertSame(403, $this->request('POST', '/academic/offerings/' . $this->f['offeringA'] . '/status',
            ['_token' => $this->token(), 'status' => 'INACTIVE'])->status());
        $this->grant('SUBJECT_OFFERING_MANAGE');
        $manage = $this->request('GET', $this->path());
        self::assertSame(1, $this->xpath($manage->body())->query('//main//a[contains(@href,"/academic/offerings/create")]')->length);
        self::assertSame(2, $this->xpath($manage->body())->query('//table//a[contains(@href,"/edit")]')->length);
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='VIEWER' AND p.code='ACADEMIC_SETUP_VIEW'");
        self::assertSame(200, $this->request('GET', $this->path())->status());
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='VIEWER' AND p.code='SUBJECT_OFFERING_MANAGE'");
        self::assertSame(404, $this->request('GET', $this->path())->status());
    }

    public function testForeignInvalidSystemAndUnrelatedClassroomsFailSafely(): void
    {
        self::assertSame(302, $this->request('GET', $this->path())->status());
        $this->login();
        $missing = $this->request('GET', '/workspaces/classrooms/' . PHP_INT_MAX . '/subjects');
        self::assertSame(404, $missing->status());
        self::assertSame($missing->body(), $this->request('GET', $this->path('B'))->body());
        self::assertSame(405, $this->request('POST', $this->path())->status());
        $this->login('SUBJECT_TEACHER');
        self::assertSame($missing->body(), $this->request('GET', $this->path('Other'))->body());
        $this->login('SYSTEM_ADMIN');
        self::assertSame(404, $this->request('GET', $this->path())->status());
    }

    public function testClosedYearIsReadableWithoutMutationControls(): void
    {
        $this->login();
        $response = $this->request('GET', $this->path('Closed'));
        self::assertSame(200, $response->status());
        self::assertSame([$this->f['offeringClosed']], $this->offeringIds($response->body()));
        self::assertSame(0, $this->xpath($response->body())->query('//main//form | //main//a[contains(@href,"/edit") or contains(@href,"/create")]')->length);
        self::assertStringContainsString('ปิดแล้ว', $response->body());
    }

    public function testOpenOfferingEntryPrefillsAuthoritativeClassroomAndYear(): void
    {
        $this->login();
        $response = $this->request('GET', '/academic/offerings/create', [], [
            'workspace_classroom_id' => $this->f['roomA'], 'academic_year_id' => $this->f['yearB'],
            'school_id' => $this->f['schoolB'], 'classroom_id' => $this->f['roomB'],
        ]);
        self::assertSame(200, $response->status());
        $x = $this->xpath($response->body());
        self::assertSame((string) $this->f['yearA'], $x->query('//form[@method="post"]//input[@name="academic_year_id"]/@value')->item(0)->nodeValue);
        self::assertSame((string) $this->f['roomA'], $x->query('//select[@name="classroom_id"]/option[@selected]/@value')->item(0)->nodeValue);
        self::assertSame('/academic/offerings?workspace_classroom_id=' . $this->f['roomA'],
            $x->query('//main//form[@method="post"]/@action')->item(0)->nodeValue);
        self::assertSame(404, $this->request('GET', '/academic/offerings/create', [], ['workspace_classroom_id' => $this->f['roomB']])->status());
    }

    public function testContextualWritesReuseExistingTransactionsAndReturnToClassroom(): void
    {
        $this->login();
        $context = ['workspace_classroom_id' => $this->f['roomA']];
        $before = $this->readSnapshot();
        $duplicate = $this->request('POST', '/academic/offerings', [
            '_token' => $this->token(), 'academic_year_id' => $this->f['yearA'],
            'classroom_id' => $this->f['roomA'], 'subject_id' => $this->f['subjectA'], 'term_no' => 1,
        ], $context);
        self::assertSame(422, $duplicate->status());
        self::assertSame($before, $this->readSnapshot());

        $assignment = $this->request('POST', '/academic/teaching-assignments', [
            '_token' => $this->token(), 'user_role_assignment_id' => $this->users['SUBJECT_TEACHER']['assignment'],
            'subject_offering_id' => $this->f['offeringA'],
        ], $context);
        self::assertSame(302, $assignment->status());
        self::assertSame($this->path(), (new ReflectionProperty($assignment, 'headers'))->getValue($assignment)['Location']);
        self::assertSame($before, $this->readSnapshot()); // Reactivating an active pair is a no-op.

        $status = $this->request('POST', '/academic/teaching-assignments/' . $this->f['scopeA'] . '/status',
            ['_token' => $this->token(), 'status' => 'INACTIVE'], $context);
        self::assertSame(302, $status->status());
        self::assertSame($this->path(), (new ReflectionProperty($status, 'headers'))->getValue($status)['Location']);
        self::assertSame('INACTIVE', $this->row('permission_scopes', $this->f['scopeA'])['status']);
        self::assertCount(count($before['audit_logs']) + 1, $this->readSnapshot()['audit_logs']);
        self::assertSame($before['gradebook_scores'], $this->readSnapshot()['gradebook_scores']);
        self::assertStringContainsString('ยังไม่มีครูที่กำลังสอน', $this->request('GET', $this->path())->body());

        $opening = $this->request('POST', '/academic/offerings', [
            '_token' => $this->token(), 'academic_year_id' => $this->f['yearA'],
            'classroom_id' => $this->f['roomA'], 'subject_id' => $this->f['subjectA'], 'term_no' => 2,
        ], $context);
        self::assertSame(302, $opening->status());
        self::assertSame($this->path(), (new ReflectionProperty($opening, 'headers'))->getValue($opening)['Location']);
        self::assertCount(3, $this->offeringIds($this->request('GET', $this->path())->body()));
        self::assertCount(count($before['audit_logs']) + 2, $this->readSnapshot()['audit_logs']);
    }

    public function testAssignmentManagerHasOnlyAssignmentActionsAndRevocationLeavesAcademicRead(): void
    {
        $this->grant('ACADEMIC_SETUP_VIEW');
        $this->grant('TEACHING_ASSIGNMENT_MANAGE');
        $this->login('VIEWER');
        $response = $this->request('GET', $this->path());
        self::assertSame(200, $response->status());
        $x = $this->xpath($response->body());
        self::assertSame(1, $x->query('//tr[@data-offering-id="' . $this->f['offeringA'] . '"]//form[starts-with(@action,"/academic/teaching-assignments?")]')->length);
        self::assertSame(1, $x->query('//tr[@data-offering-id="' . $this->f['offeringA'] . '"]//form[contains(@action,"/status?")]')->length);
        self::assertSame(0, $x->query('//main//a[contains(@href,"/academic/offerings/create") or contains(@href,"/setup") or @href="/academic/subjects/create"]')->length);
        self::assertSame(0, $x->query('//main//a[@href="/gradebook/' . $this->f['offeringA'] . '"]')->length);
        $this->revokeViewer('TEACHING_ASSIGNMENT_MANAGE');
        $after = $this->request('GET', $this->path());
        self::assertSame(200, $after->status());
        self::assertSame([$this->f['offeringA'], $this->f['offeringInactive']], $this->offeringIds($after->body()));
        self::assertSame(0, $this->xpath($after->body())->query('//main//form')->length);
        self::assertSame(403, $this->request('POST', '/academic/teaching-assignments', [
            '_token' => $this->token(), 'user_role_assignment_id' => $this->users['SUBJECT_TEACHER']['assignment'],
            'subject_offering_id' => $this->f['offeringA'],
        ])->status());
    }

    public function testOfferingAndScoreCapabilitiesRevokeIndependently(): void
    {
        $this->grant('ACADEMIC_SETUP_VIEW');
        $this->grant('SUBJECT_OFFERING_MANAGE');
        $this->grant('GRADEBOOK_COMPONENT_MANAGE');
        $this->login('VIEWER');
        $initial = $this->xpath($this->request('GET', $this->path())->body());
        self::assertSame(1, $initial->query('//main//a[contains(@href,"/academic/offerings/create")]')->length);
        self::assertSame(1, $initial->query('//tr[@data-offering-id="' . $this->f['offeringA'] . '"]//a[@href="/gradebook/' . $this->f['offeringA'] . '/setup"]')->length);
        self::assertSame(0, $initial->query('//tr[@data-offering-id="' . $this->f['offeringA'] . '"]//a[@href="/gradebook/' . $this->f['offeringA'] . '"]')->length);
        $this->revokeViewer('SUBJECT_OFFERING_MANAGE');
        $after = $this->xpath($this->request('GET', $this->path())->body());
        self::assertSame(0, $after->query('//main//a[contains(@href,"/academic/offerings/create") or contains(@href,"/edit")]')->length);
        self::assertSame(1, $after->query('//tr[@data-offering-id="' . $this->f['offeringA'] . '"]//a[@href="/gradebook/' . $this->f['offeringA'] . '/setup"]')->length);
        $this->revokeViewer('GRADEBOOK_COMPONENT_MANAGE');
        $last = $this->request('GET', $this->path());
        self::assertSame(200, $last->status());
        self::assertSame(0, $this->xpath($last->body())->query('//main//a[contains(@href,"/setup")]')->length);
    }

    public function testSchoolSubjectManagementDoesNotGrantClassroomRead(): void
    {
        $this->grant('SUBJECT_MANAGE');
        $this->login('VIEWER');
        self::assertSame(404, $this->request('GET', $this->path())->status());
        $this->grant('ACADEMIC_SETUP_VIEW');
        $x = $this->xpath($this->request('GET', $this->path())->body());
        self::assertSame(1, $x->query('//main//a[@href="/academic/subjects/create"]')->length);
        self::assertSame(0, $x->query('//main//a[contains(@href,"/academic/offerings/create")]')->length);
        $this->revokeViewer('SUBJECT_MANAGE');
        self::assertSame(0, $this->xpath($this->request('GET', $this->path())->body())
            ->query('//main//a[@href="/academic/subjects/create"]')->length);
    }

    public function testContextualPostsRejectCsrfAndForeignResourcesWithoutWrites(): void
    {
        $this->login();
        $before = $this->readSnapshot();
        $context = ['workspace_classroom_id' => $this->f['roomA']];
        self::assertSame(419, $this->request('POST', '/academic/offerings', [
            '_token' => 'bad', 'academic_year_id' => $this->f['yearA'], 'classroom_id' => $this->f['roomA'],
            'subject_id' => $this->f['subjectA'], 'term_no' => 2,
        ], $context)->status());
        self::assertSame(419, $this->request('POST', '/academic/teaching-assignments', [
            '_token' => 'bad', 'user_role_assignment_id' => $this->users['SUBJECT_TEACHER']['assignment'],
            'subject_offering_id' => $this->f['offeringA'],
        ], $context)->status());
        self::assertSame(422, $this->request('POST', '/academic/offerings', [
            '_token' => $this->token(), 'academic_year_id' => $this->f['yearA'], 'classroom_id' => $this->f['roomA'],
            'subject_id' => $this->f['subjectB'], 'term_no' => 2,
        ], $context)->status());
        self::assertSame(422, $this->request('POST', '/academic/teaching-assignments', [
            '_token' => $this->token(), 'user_role_assignment_id' => $this->users['SUBJECT_TEACHER']['assignment'],
            'subject_offering_id' => $this->f['offeringB'],
        ], $context)->status());
        self::assertSame($before, $this->readSnapshot());
    }

    public function testTermTwoAssignmentAndGradebookRemainIndependent(): void
    {
        $this->login();
        $termTwo = $this->insert('subject_offerings', ['school_id' => $this->f['schoolA'],
            'academic_year_id' => $this->f['yearA'], 'classroom_id' => $this->f['roomA'],
            'subject_id' => $this->f['subjectA'], 'term_no' => 2]);
        $twoScope = $this->insert('permission_scopes', ['school_id' => $this->f['schoolA'],
            'academic_year_id' => $this->f['yearA'], 'subject_offering_id' => $termTwo,
            'user_role_assignment_id' => $this->users['SUBJECT_TEACHER']['assignment'],
            'assigned_by' => $this->users['SCHOOL_ADMIN']['user']]);
        $x = $this->xpath($this->request('GET', $this->path())->body());
        self::assertSame(1, $x->query('//tr[@data-offering-id="' . $termTwo . '"]//a[@href="/gradebook/' . $termTwo . '"]')->length);
        self::assertSame(1, $x->query('//tr[@data-offering-id="' . $termTwo . '"]//a[@href="/gradebook/' . $termTwo . '/setup"]')->length);
        self::assertSame(1, $x->query('//tr[@data-offering-id="' . $termTwo . '"]//form[contains(@action,"/teaching-assignments/' . $twoScope . '/status")]')->length);
        self::assertSame(0, $x->query('//tr[@data-offering-id="' . $this->f['offeringA'] . '"]//form[contains(@action,"/teaching-assignments/' . $twoScope . '/status")]')->length);
        $this->pdo->prepare("UPDATE subject_offerings SET status='INACTIVE' WHERE id=?")->execute([$termTwo]);
        self::assertSame('ACTIVE', $this->row('subject_offerings', $this->f['offeringA'])['status']);
        self::assertSame('INACTIVE', $this->row('subject_offerings', $termTwo)['status']);
    }
}
