<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/TeachingAssignmentHttpFixtures.php';

final class TeachingAssignmentIsolationTest extends TestCase
{
    use TeachingAssignmentHttpFixtures;

    private function injection(): array
    {
        return ['school_id' => $this->f['schoolB'], 'user_id' => $this->f['foreignUser'], 'actor_user_id' => $this->f['foreignUser'],
            'context_type' => 'SYSTEM', 'role' => 'SYSTEM_ADMIN', 'permission' => 'TEACHING_ASSIGNMENT_MANAGE', 'scope' => '*',
            'academic_year_id' => $this->f['yearB'], 'classroom_id' => $this->f['roomB'], 'subject_id' => $this->f['subjectB']];
    }

    public function testSchoolBrowserCannotReadOrChooseForeignData(): void
    {
        $this->login();
        foreach ([[], ['academic_year_id' => $this->f['yearA']]] as $query) {
            $r = $this->request('GET', self::PATH, [], array_replace($this->injection(), $query, ['academic_year_id' => $query['academic_year_id'] ?? null]));
            self::assertSame(200, $r->status()); $this->assertSafe($r);
            $x = $this->xpath($r->body());
            self::assertSame(0, $x->query('//tr[@data-assignment-id="' . $this->f['scopeB'] . '"]')->length);
            self::assertNotContains((string) $this->f['foreignAssignment'], $this->optionIds($x, 'user_role_assignment_id'));
            self::assertNotContains((string) $this->f['offeringB'], $this->optionIds($x, 'subject_offering_id'));
        }
        $foreign = $this->request('GET', self::PATH, [], ['academic_year_id' => $this->f['yearB']]);
        $missing = $this->request('GET', self::PATH, [], ['academic_year_id' => PHP_INT_MAX]);
        self::assertSame(404, $foreign->status()); self::assertSame($foreign->status(), $missing->status()); self::assertSame($foreign->body(), $missing->body());
    }

    public function testSessionOwnsTenantAndActorForCreateAndStatus(): void
    {
        $this->login(); $before = $this->snapshot();
        self::assertSame(302, $this->request('POST', self::PATH, $this->payload($this->injection()))->status());
        $after = $this->snapshot(); $scope = end($after['permission_scopes']); $audit = end($after['audit_logs']);
        self::assertSame($this->f['schoolA'], $scope['school_id']); self::assertSame($this->f['yearA'], $scope['academic_year_id']);
        self::assertSame($this->users['SCHOOL_ADMIN']['user'], $scope['assigned_by']);
        self::assertSame($this->f['schoolA'], $audit['school_id']); self::assertSame($this->users['SCHOOL_ADMIN']['user'], $audit['user_id']);
        self::assertSame(302, $this->request('POST', self::PATH . '/' . $scope['id'] . '/status', $this->payload($this->injection()))->status());
        $after = $this->snapshot(); $audit = end($after['audit_logs']);
        self::assertSame('INACTIVE', $this->row('permission_scopes', $scope['id'])['status']);
        self::assertSame($this->f['schoolA'], $audit['school_id']); self::assertSame($this->users['SCHOOL_ADMIN']['user'], $audit['user_id']);
        self::assertSame(array_values(array_filter($before['permission_scopes'], fn ($r) => $r['school_id'] === $this->f['schoolB'])),
            array_values(array_filter($after['permission_scopes'], fn ($r) => $r['school_id'] === $this->f['schoolB'])));
    }

    public function testForeignAndMissingCreateTargetsAreIndistinguishableAndDoNotWrite(): void
    {
        $this->login(); $before = $this->snapshot();
        foreach (['user_role_assignment_id' => $this->f['foreignAssignment'], 'subject_offering_id' => $this->f['offeringB']] as $field => $id) {
            $foreign = $this->request('POST', self::PATH, $this->payload(array_replace($this->injection(), [$field => $id])));
            $missing = $this->request('POST', self::PATH, $this->payload([$field => PHP_INT_MAX]));
            self::assertSame(422, $foreign->status()); self::assertSame($foreign->status(), $missing->status());
            self::assertSame($foreign->body(), $missing->body()); $this->assertSafe($foreign);
        }
        foreach ([$this->f['offeringInactive'], $this->f['offeringClosed']] as $id) {
            self::assertSame(422, $this->request('POST', self::PATH, $this->payload(['subject_offering_id' => $id]))->status());
        }
        foreach ($this->rows("SELECT ura.id FROM user_role_assignments ura JOIN users u ON u.id=ura.user_id WHERE u.display_name IN ('INACTIVE_MEMBERSHIP','INACTIVE_ASSIGNMENT','VIEWER')") as $row) {
            self::assertSame(422, $this->request('POST', self::PATH, $this->payload(['user_role_assignment_id' => $row['id']]))->status());
        }
        self::assertSame($before, $this->snapshot());
    }

    public function testForeignStatusCannotActivateOrDeactivateAndMatchesMissingTarget(): void
    {
        $this->login(); $before = $this->snapshot();
        foreach (['ACTIVE', 'INACTIVE'] as $status) {
            $foreign = $this->request('POST', self::PATH . '/' . $this->f['scopeB'] . '/status', $this->payload(array_replace($this->injection(), ['status' => $status])));
            $missing = $this->request('POST', self::PATH . '/' . PHP_INT_MAX . '/status', $this->payload(['status' => $status]));
            self::assertSame(422, $foreign->status()); self::assertSame($foreign->status(), $missing->status());
            self::assertSame($foreign->body(), $missing->body()); $this->assertSafe($foreign);
        }
        self::assertSame($before, $this->snapshot());
    }
}
