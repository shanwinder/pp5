<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;
use App\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookScoreFixtures.php';

final class GradebookScoreHttpTest extends TestCase
{
    use GradebookScoreFixtures;

    private function scorePath(string $enrollment = 'current', string $component = 'componentA', string $offering = 'A'): string
    {
        return '/hx/gradebook/' . $this->f['offering' . $offering] . '/components/' . $this->f[$component]
            . '/enrollments/' . $this->f['enrollment_' . $enrollment] . '/score';
    }

    private function postScore(mixed $score = '5', string $enrollment = 'current', string $component = 'componentA', string $offering = 'A', array $extra = [], array $server = []): Response
    {
        return (new Application($this->pdo))->handle(new Request('POST', $this->scorePath($enrollment, $component, $offering), [],
            array_replace(['_token' => $this->token(), 'score' => $score], $extra),
            array_replace(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HX_REQUEST' => 'true'], $server)));
    }

    private function assertFailed(Response $response, array $before, int $status = 422): void
    {
        self::assertSame($status, $response->status());
        self::assertStringNotContainsString('data-save-state="saved"', $response->body());
        self::assertStringNotContainsString('บันทึกแล้ว', $response->body());
        $this->assertReadSafe($response->body());
        self::assertSame($before, $this->readSnapshot());
    }

    public function testRouteUsesAuthenticationAndSchoolContextWithoutGenericPermissionGate(): void
    {
        $route = FastRoute\simpleDispatcher(require dirname(__DIR__, 2) . '/htdocs/routes/web.php')->dispatch('POST', $this->scorePath());
        self::assertSame(FastRoute\Dispatcher::FOUND, $route[0]);
        self::assertSame(['action' => 'gradebook.scores.store', 'protected' => true, 'context' => 'SCHOOL'], $route[1]);
        self::assertSame(302, $this->postScore()->status());
        $this->login('SUBJECT_TEACHER');
        $auth = new App\Services\AuthorizationService(new App\Repositories\AuthorizationRepository($this->pdo));
        self::assertFalse($auth->hasPermission($this->users['SUBJECT_TEACHER']['user'], 'SCHOOL', $this->f['schoolA'], 'GRADEBOOK_SCORE_ENTER'));
        self::assertSame(200, $this->postScore()->status());
        self::assertSame(405, $this->request('GET', $this->scorePath())->status());
        self::assertSame(404, $this->request('POST', '/hx/gradebook/no/components/1/enrollments/1/score')->status());
    }

    public static function decimals(): array { return [['0','0.00'], ['0.00','0.00'], ['5','5.00'], ['05.00','5.00'], ['01.50','1.50']]; }
    #[DataProvider('decimals')]
    public function testNormalizedScoreAndAuthoritativeSummaryFragment(string $input, string $normalized): void
    {
        $this->login('SUBJECT_TEACHER'); $r = $this->postScore($input);
        self::assertSame(200, $r->status()); $this->assertReadSafe($r->body());
        self::assertSame($normalized, $this->cellRows()[0]['score']);
        $x = $this->xpath($r->body());
        self::assertSame($normalized, $x->evaluate('string(//input[@name="score"]/@value)'));
        self::assertSame(1, $x->query('//*[@data-save-state="saved"]')->length);
        self::assertStringContainsString('บันทึกแล้ว', $r->body());
        self::assertSame(4, $x->query('//*[@hx-swap-oob]')->length);
        self::assertSame(0, $x->query('//table|//script')->length);
        $this->assertSummary($r, $normalized, '1 / 2', false);
    }

    private function assertSummary(Response $response, string $total, string $count, bool $complete, string $enrollment = 'current'): void
    {
        $x = $this->xpath($response->body());
        $prefix = 'row-' . $this->f['offeringA'] . '-' . $this->f['enrollment_' . $enrollment] . '-';
        foreach (['total' => $total, 'max' => '35.50', 'count' => $count, 'complete' => $complete ? 'ครบ' : 'ยังไม่ครบ'] as $suffix => $expected) {
            self::assertSame($expected, $x->evaluate('string(//*[@id="' . $prefix . $suffix . '"])'));
        }
    }

    public function testClearZeroCompletenessAndNoopSemanticsArePreserved(): void
    {
        $this->login(); $before = $this->readSnapshot();
        $r = $this->postScore(''); self::assertSame(200, $r->status());
        self::assertSame([], $this->cellRows()); self::assertSame($before, $this->readSnapshot());
        $this->assertSummary($r, '0.00', '0 / 2', false);
        self::assertSame(200, $this->postScore('5')->status());
        $id = $this->cellRows()[0]['id']; $before = $this->readSnapshot();
        self::assertSame(200, $this->postScore('05.00')->status()); self::assertSame($before, $this->readSnapshot());
        $r = $this->postScore('0', component: 'componentSecond'); self::assertSame(200, $r->status());
        $this->assertSummary($r, '5.00', '2 / 2', true);
        $r = $this->postScore(''); self::assertSame(200, $r->status());
        self::assertSame('', $this->xpath($r->body())->evaluate('string(//input[@name="score"]/@value)'));
        self::assertCount(1, $this->cellRows()); self::assertSame($id, $this->cellRows()[0]['id']); self::assertNull($this->cellRows()[0]['score']);
        $this->assertSummary($r, '0.00', '1 / 2', false);
        $before = $this->readSnapshot(); self::assertSame(200, $this->postScore('')->status()); self::assertSame($before, $this->readSnapshot());
        self::assertCount(3, $this->scoreAudits());
    }

    public static function csrfCases(): array { return [[null], ['invalid'], [['bad']], ['other-session']]; }
    #[DataProvider('csrfCases')]
    public function testCsrfPrecedesAnyMutation(mixed $token): void
    {
        $this->login();
        if ($token === 'other-session') { $token = $this->token(); unset($_SESSION['csrf_token']); $this->token(); }
        $before = $this->readSnapshot(); $this->pdo->queries = [];
        $r = $this->postScore(extra: ['_token' => $token]); $this->assertFailed($r, $before, 419);
        self::assertSame('CSRF token mismatch', $r->body());
        self::assertSame([], array_values(array_filter($this->pdo->queries, fn ($sql) => str_contains($sql, 'FOR UPDATE'))));
    }

    public static function invalidScores(): array
    {
        return array_map(fn ($v) => [$v], ['-1', '+1', '1e2', '1,5', '1.234', 'NaN', 'Infinity', '20.01', ' ', [], ['5'], 5, false, null,
            '<script>alert(1)</script>', '"><img src=x onerror=alert(1)>']);
    }
    #[DataProvider('invalidScores')]
    public function testInvalidScoreIsSafeAndDoesNotWrite(mixed $score): void
    {
        $this->login(); $before = $this->readSnapshot(); $r = $this->postScore($score);
        $this->assertFailed($r, $before);
        self::assertStringContainsString('ผิดพลาด', $r->body());
        self::assertSame(0, $this->xpath($r->body())->query('//script|//img')->length);
    }

    public function testMissingScoreIsNotClear(): void
    {
        $this->login(); $before = $this->readSnapshot();
        $r = $this->request('POST', $this->scorePath('complete'), ['_token' => $this->token()]);
        $this->assertFailed($r, $before);
    }

    public static function roles(): array { return [['SCHOOL_ADMIN',200], ['ACADEMIC_ADMIN',200], ['SUBJECT_TEACHER',200], ['EXECUTIVE',422], ['VIEWER',422], ['HOMEROOM_TEACHER',422], ['SYSTEM_ADMIN',403], ['FOREIGN',403]]; }
    #[DataProvider('roles')]
    public function testSeededMutationAccess(string $role, int $status): void
    {
        $this->login($role); $before = $this->readSnapshot(); $r = $this->postScore();
        if ($status === 200) { self::assertSame(200, $r->status()); self::assertSame('5.00', $this->cellRows()[0]['score']); }
        else { $this->assertFailed($r, $before, $status); }
    }

    public static function targets(): array
    {
        return [['current','componentA','B'], ['current','componentB','A'], ['foreign','componentA','A'],
            ['current','componentOther','A'], ['wrongRoom','componentA','A'], ['wrongYear','componentA','A'],
            ['moved','componentA','A'], ['inactive','componentA','A'], ['current','inactiveComponent','A'],
            ['current','componentInactive','Inactive'], ['current','componentClosed','Closed']];
    }
    #[DataProvider('targets')]
    public function testIsolationAndHistoricalTargetsUseSameFailureAsMissing(string $enrollment, string $component, string $offering): void
    {
        $this->login(); $before = $this->readSnapshot();
        $r = $this->postScore(enrollment: $enrollment, component: $component, offering: $offering); $this->assertFailed($r, $before);
        $missing = $this->request('POST', '/hx/gradebook/' . PHP_INT_MAX . '/components/1/enrollments/1/score', ['_token' => $this->token(), 'score' => '5']);
        self::assertSame($missing->body(), $r->body()); self::assertSame($missing->status(), $r->status());
    }

    public function testOffScopeTeacherCannotWriteAndCannotBorrowAnotherRoleScope(): void
    {
        $this->login('SUBJECT_TEACHER'); $before = $this->readSnapshot();
        self::assertSame(404, $this->request('GET', $this->readPath('Other'))->status());
        $this->assertFailed($this->postScore(enrollment: 'wrongRoom', component: 'componentOther', offering: 'Other'), $before);
        $this->changePrerequisite('scope');
        $role = $this->rows("SELECT id FROM roles WHERE code='VIEWER'")[0]['id'];
        $assignment = $this->insert('user_role_assignments', ['school_id' => $this->f['schoolA'], 'user_id' => $this->users['SUBJECT_TEACHER']['user'], 'role_id' => $role]);
        $this->scope($assignment, 'A'); $before = $this->readSnapshot();
        $this->assertFailed($this->postScore(), $before);
    }

    public static function staleStates(): iterable
    {
        foreach (['year','offering','component','enrollment','placement','scope','permission','membership','assignment','school','role'] as $state) {
            foreach (['6', ''] as $score) { yield [$state, $score]; }
        }
    }
    #[DataProvider('staleStates')]
    public function testStaleEditablePageCannotAuthorizeMutation(string $state, string $score): void
    {
        $this->login('SUBJECT_TEACHER'); $this->setCell('5');
        $page = $this->request('GET', $this->readPath()); self::assertSame(200, $page->status());
        self::assertGreaterThan(0, $this->xpath($page->body())->query('//input[@hx-post]')->length);
        $this->changePrerequisite($state); $before = $this->readSnapshot();
        $this->assertFailed($this->postScore($score), $before, in_array($state, ['membership','school'], true) ? 403 : 422);
    }

    public function testForgedAuthorityNeverChangesSchoolOrAuditActor(): void
    {
        $this->login('SUBJECT_TEACHER');
        $forged = ['school_id' => $this->f['schoolB'], 'user_id' => $this->users['FOREIGN']['user'], 'actor_user_id' => $this->users['FOREIGN']['user'],
            'role' => 'SYSTEM_ADMIN', 'permission' => 'ALL', 'scope' => '*', 'context_type' => 'SYSTEM', 'updated_by' => $this->users['FOREIGN']['user'],
            'classroom_id' => $this->f['roomB'], 'subject_id' => $this->f['subjectB'], 'academic_year_id' => $this->f['yearB']];
        self::assertSame(200, $this->postScore(extra: $forged)->status());
        $audit = $this->scoreAudits()[0];
        self::assertSame($this->f['schoolA'], $audit['school_id']); self::assertSame($this->users['SUBJECT_TEACHER']['user'], $audit['user_id']);
        self::assertSame($this->users['SUBJECT_TEACHER']['user'], $this->cellRows()[0]['updated_by']);
        $before = $this->readSnapshot();
        $this->assertFailed($this->postScore(enrollment: 'foreign', component: 'componentB', offering: 'B', extra: $forged), $before);
    }

    public static function ips(): array { return [['::1','::1'], ['invalid',null], [['127.0.0.1'],null]]; }
    #[DataProvider('ips')]
    public function testOnlyValidatedRemoteAddressIsAudited(mixed $ip, ?string $expected): void
    {
        $this->login(); self::assertSame(200, $this->postScore(server: ['REMOTE_ADDR' => $ip, 'HTTP_X_FORWARDED_FOR' => '8.8.8.8'])->status());
        self::assertSame($expected, $this->scoreAudits()[0]['ip_address']);
    }

    public function testAuditFailureRollsBackAndDoesNotExposeSql(): void
    {
        $this->login(); $before = $this->readSnapshot(); $this->pdo->failPrepare = 'INSERT INTO audit_logs';
        $this->assertFailed($this->postScore(), $before); self::assertTrue($this->pdo->failureTriggered);
    }

    public function testRefreshFailureDoesNotClaimSavedOrRollbackAfterCommittedWrite(): void
    {
        $this->login(); $this->pdo->failPrepare = 'FROM gradebook_scores gs';
        $r = $this->postScore(); self::assertSame(409, $r->status());
        self::assertSame('5.00', $this->cellRows()[0]['score']); self::assertCount(1, $this->scoreAudits());
        self::assertTrue($this->pdo->failureTriggered); $this->assertReadSafe($r->body());
        self::assertStringNotContainsString('data-save-state="saved"', $r->body());
        self::assertStringContainsString('โหลดหน้าใหม่เพื่อตรวจสอบคะแนน', $r->body());
        // Retrying the same value obtains the summary without a duplicate audit.
        self::assertSame(200, $this->postScore()->status()); self::assertCount(1, $this->scoreAudits());
    }

    public function testRefreshUsesBatchedQueriesAsRosterGrows(): void
    {
        $this->login(); $this->pdo->queries = []; self::assertSame(200, $this->postScore()->status());
        $count = count($this->pdo->queries);
        foreach (range(1, 12) as $n) { $this->student('MORE-' . $n); }
        $this->pdo->queries = []; self::assertSame(200, $this->postScore('6')->status());
        self::assertSame($count, count($this->pdo->queries));
    }
}
