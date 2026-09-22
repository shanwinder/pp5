<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/GradebookComponentFixtures.php';

final class GradebookComponentIsolationTest extends TestCase
{
    use GradebookComponentFixtures;

    private function injection(): array
    {
        return ['school_id' => $this->f['schoolB'], 'academic_year_id' => $this->f['yearB'], 'actor_user_id' => $this->users['FOREIGN']['user'],
            'user_id' => $this->users['FOREIGN']['user'], 'role' => 'SYSTEM_ADMIN', 'permission' => '*', 'context_type' => 'SYSTEM'];
    }
    public function testGetIsSchoolScopedAndForeignMissingAreIdentical(): void
    {
        $this->login(); $r = $this->request('GET', $this->path(), [], $this->injection()); self::assertSame(200, $r->status()); $this->assertSafe($r->body());
        $foreign = $this->request('GET', $this->path('setup', 'B')); $missing = $this->request('GET', '/gradebook/' . PHP_INT_MAX . '/setup');
        self::assertSame(404, $foreign->status()); self::assertSame($foreign->status(), $missing->status()); self::assertSame($foreign->body(), $missing->body());
    }
    public function testSessionOwnsTenantYearAndActorDespiteBrowserInjection(): void
    {
        $this->login(); $before = $this->snapshot();
        foreach (['create','update','status'] as $action) {
            self::assertSame(302, $this->request('POST', $this->path($action), $this->payload(array_replace($this->injection(), ['code' => $action])))->status());
            $after = $this->snapshot(); $audit = end($after['audit_logs']);
            self::assertSame($this->f['schoolA'], $audit['school_id']); self::assertSame($this->users['SCHOOL_ADMIN']['user'], $audit['user_id']);
            $component = $this->row('gradebook_components', $audit['entity_id']);
            self::assertSame($this->f['schoolA'], $component['school_id']); self::assertSame($this->f['yearA'], $component['academic_year_id']);
            self::assertSame($this->f['offeringA'], $component['subject_offering_id']);
        }
        self::assertSame(array_values(array_filter($before['gradebook_components'], fn ($r) => $r['school_id'] === $this->f['schoolB'])),
            array_values(array_filter($this->snapshot()['gradebook_components'], fn ($r) => $r['school_id'] === $this->f['schoolB'])));
        self::assertSame($before['gradebook_scores'], $this->snapshot()['gradebook_scores']);
    }
    public function testForeignCreateAndMissingOfferingAreNonEnumerating(): void
    {
        $this->login(); $before = $this->snapshot();
        $foreign = $this->request('POST', $this->path('create', 'B'), $this->payload($this->injection()));
        $missing = $this->request('POST', '/gradebook/' . PHP_INT_MAX . '/components', $this->payload());
        self::assertSame(422, $foreign->status()); self::assertSame($foreign->status(), $missing->status()); self::assertSame($foreign->body(), $missing->body());
        $this->assertSafe($foreign->body()); self::assertSame($before, $this->snapshot());
    }
    public function testOfferingComponentAndTenantMustMatchForHttpAndDomain(): void
    {
        $this->login(); $before = $this->snapshot();
        foreach ([['B','B'],['A','B'],['B','A'],['Other','A'],['A','Other'],['Next','A']] as [$offering, $component]) {
            $oid = $this->f['offering' . $offering]; $cid = $this->f['component' . $component];
            foreach (['update','status'] as $action) {
                $base = '/gradebook/' . $oid . '/components/'; $suffix = $action === 'status' ? '/status' : '';
                foreach (['ACTIVE','INACTIVE'] as $status) {
                    $r = $this->request('POST', $base . $cid . $suffix, $this->payload(array_replace($this->injection(), ['status' => $status])));
                    $missing = $this->request('POST', $base . PHP_INT_MAX . $suffix, $this->payload(['status' => $status]));
                    self::assertSame(422, $r->status()); self::assertSame($r->status(), $missing->status()); self::assertSame($r->body(), $missing->body()); $this->assertSafe($r->body());
                }
            }
            $this->assertRejected(fn () => $this->service()->updateComponent($this->f['schoolA'], $this->users['SCHOOL_ADMIN']['user'], $oid, $cid, 'CHANGED', 'แก้', '20', 1));
            $this->assertRejected(fn () => $this->service()->changeStatus($this->f['schoolA'], $this->users['SCHOOL_ADMIN']['user'], $oid, $cid, 'INACTIVE'));
        }
        self::assertSame($before, $this->snapshot());
    }
}
