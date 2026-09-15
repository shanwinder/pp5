<?php
declare(strict_types=1);

use App\Repositories\AcademicYearRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\AuthorizationRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\SubjectOfferingRepository;
use App\Repositories\TeachingAssignmentRepository;
use App\Services\AuthorizationService;
use App\Services\TeachingAssignmentService;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TeachingAssignmentServiceTest extends TestCase
{
    private TeachingAssignmentTestPDO $pdo;
    private array $f;

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new TeachingAssignmentTestPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->f = [];
        $this->f['user'] = $this->insert('users', ['username' => 'teaching-domain-user', 'password_hash' => 'unused', 'display_name' => 'Teacher']);
        $grade = $this->pdo->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        foreach (['A', 'B'] as $key) {
            $school = $this->f['school' . $key] = $this->insert('schools', ['school_code' => 'teaching-domain-' . $key, 'name_th' => 'โรงเรียนทดสอบ']);
            $year = $this->f['year' . $key] = $this->insert('academic_years', ['school_id' => $school, 'year_be' => 2569]);
            $room = $this->insert('classrooms', ['school_id' => $school, 'academic_year_id' => $year, 'grade_level_id' => $grade, 'code' => 'P1', 'name_th' => 'ห้องทดสอบ']);
            $subject = $this->insert('subjects', ['school_id' => $school, 'code' => 'ท11101', 'name_th' => 'ภาษาไทย']);
            $offering = ['school_id' => $school, 'academic_year_id' => $year, 'classroom_id' => $room, 'subject_id' => $subject, 'term_no' => 1];
            $this->f['offering' . $key] = $this->insert('subject_offerings', $offering);
            if ($key === 'A') {
                $this->f['otherOffering'] = $this->insert('subject_offerings', array_replace($offering, ['term_no' => 2]));
            }
        }
        $this->f['actor'] = $this->insert('users', ['username' => 'teaching-domain-actor', 'password_hash' => 'unused', 'display_name' => 'Actor']);
        $this->f['membership'] = $this->insert('school_memberships', ['school_id' => $this->f['schoolA'], 'user_id' => $this->f['user']]);
        $this->f['role'] = $this->role('SUBJECT_TEACHER');
        $this->f['assignment'] = $this->insert('user_role_assignments', ['school_id' => $this->f['schoolA'], 'user_id' => $this->f['user'], 'role_id' => $this->f['role']]);
        $foreignUser = $this->insert('users', ['username' => 'teaching-domain-foreign', 'password_hash' => 'unused', 'display_name' => 'Foreign teacher']);
        $this->insert('school_memberships', ['school_id' => $this->f['schoolB'], 'user_id' => $foreignUser]);
        $this->f['foreignAssignment'] = $this->insert('user_role_assignments', ['school_id' => $this->f['schoolB'], 'user_id' => $foreignUser, 'role_id' => $this->f['role']]);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            while ($this->pdo->depth > 0) { $this->pdo->rollBack(); }
        }
    }

    public function test_lists_concrete_eligible_assignments_from_one_school_only(): void
    {
        $this->insert('user_role_assignments', ['school_id' => $this->f['schoolA'], 'user_id' => $this->f['user'], 'role_id' => $this->role('VIEWER')]);
        $rows = $this->service()->listSubjectTeachers($this->f['schoolA']);
        self::assertCount(1, $rows);
        self::assertSame($this->f['assignment'], $rows[0]['user_role_assignment_id']);
        self::assertSame($this->f['user'], $rows[0]['user_id']);
        self::assertSame('Teacher', $rows[0]['display_name']);
        self::assertSame([], $this->service()->listSubjectTeachers(0));
    }

    #[DataProvider('teacherRevocations')]
    public function test_ineligible_teacher_is_never_listed_and_cannot_create_or_reactivate(string $table, string $column, mixed $value, string $target): void
    {
        $id = $this->fixtureScope('INACTIVE');
        $this->revoke($table, $column, $value, $target);
        $service = $this->service();
        self::assertSame([], $service->listSubjectTeachers($this->f['schoolA']));
        $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        $this->deny(fn () => $this->create());
        $this->deny(fn () => $service->changeStatus($this->f['schoolA'], $this->f['actor'], $id, 'ACTIVE'));
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }

    public static function teacherRevocations(): array
    {
        return [
            'inactive assignment' => ['user_role_assignments', 'status', 'INACTIVE', 'assignment'],
            'year-bound assignment' => ['user_role_assignments', 'academic_year_id', 'year', 'assignment'],
            'inactive membership' => ['school_memberships', 'status', 'INACTIVE', 'membership'],
            'suspended membership' => ['school_memberships', 'status', 'SUSPENDED', 'membership'],
            'inactive role' => ['roles', 'status', 'INACTIVE', 'role'],
            'wrong role code' => ['roles', 'code', 'FORMER_SUBJECT_TEACHER', 'role'],
            'wrong role scope' => ['roles', 'scope_type', 'SYSTEM', 'role'],
        ];
    }

    #[DataProvider('yearStates')]
    public function test_create_stores_exact_identity_and_audit_and_duplicate_active_is_noop(string $yearStatus): void
    {
        $this->revoke('academic_years', 'status', $yearStatus, 'yearA');
        $id = $this->create();
        $row = $this->row('permission_scopes', $id);
        foreach (['school_id' => 'schoolA', 'academic_year_id' => 'yearA', 'user_role_assignment_id' => 'assignment',
            'subject_offering_id' => 'offeringA', 'assigned_by' => 'actor'] as $column => $fixture) { self::assertSame($this->f[$fixture], $row[$column]); }
        self::assertSame('ACTIVE', $row['status']);
        self::assertSame($row['created_at'], $row['updated_at']);
        $this->assertAudit($id, 'TEACHING_ASSIGNMENT_CREATED', null, [
            'user_role_assignment_id' => $this->f['assignment'], 'subject_offering_id' => $this->f['offeringA'],
            'academic_year_id' => $this->f['yearA'], 'status' => 'ACTIVE',
        ]);
        $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        self::assertSame($id, $this->create());
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }
    public static function yearStates(): array { return [['DRAFT'], ['ACTIVE']]; }

    #[DataProvider('invalidTargets')]
    public function test_foreign_missing_and_wrong_role_create_targets_fail_before_any_write(string $target): void
    {
        $assignment = $this->f['assignment']; $offering = $this->f['offeringA']; $school = $this->f['schoolA'];
        if ($target === 'foreign offering') { $offering = $this->f['offeringB']; }
        if ($target === 'missing offering') { $offering = 0; }
        if ($target === 'foreign assignment') { $assignment = $this->f['foreignAssignment']; }
        if ($target === 'missing assignment') { $assignment = 0; }
        if ($target === 'missing school') { $school = 0; }
        if (in_array($target, ['system assignment', 'wrong role'], true)) {
            $assignment = $this->insert('user_role_assignments', ['school_id' => $target === 'system assignment' ? null : $school,
                'user_id' => $this->f['user'], 'role_id' => $this->role($target === 'system assignment' ? 'SYSTEM_ADMIN' : 'VIEWER')]);
        }
        $service = $this->service(); $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        $this->deny(fn () => $service->createAssignment($school, $this->f['actor'], $assignment, $offering));
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }
    public static function invalidTargets(): array
    {
        return array_map(static fn (string $v): array => [$v], ['foreign offering', 'missing offering', 'foreign assignment', 'missing assignment', 'missing school', 'system assignment', 'wrong role']);
    }

    #[DataProvider('statusFlows')]
    public function test_status_lifecycle_retains_scores_and_history_audits_once_and_changes_authorization(string $year, string $from, string $to): void
    {
        $id = $this->fixtureScope($from);
        $this->fixtureScore();
        $this->revoke('academic_years', 'status', $year, 'yearA');
        $old = $this->row('permission_scopes', $id);
        $scores = $this->rows('SELECT * FROM gradebook_scores ORDER BY id');
        $authorization = new AuthorizationService(new AuthorizationRepository($this->pdo));
        self::assertSame($from === 'ACTIVE', $authorization->hasSubjectOfferingPermission($this->f['user'], 'SCHOOL', $this->f['schoolA'], $this->f['offeringA'], 'GRADEBOOK_VIEW'));
        $this->service()->changeStatus($this->f['schoolA'], $this->f['actor'], $id, $to, '192.0.2.70');
        $row = $this->row('permission_scopes', $id);
        self::assertSame($to, $row['status']);
        foreach (['id', 'school_id', 'academic_year_id', 'subject_offering_id', 'user_role_assignment_id', 'assigned_by', 'created_at'] as $column) {
            self::assertSame($old[$column], $row[$column]);
        }
        self::assertSame($scores, $this->rows('SELECT * FROM gradebook_scores ORDER BY id'));
        $this->assertAudit($id, 'TEACHING_ASSIGNMENT_STATUS_CHANGED', ['status' => $from], ['status' => $to]);
        self::assertSame($to === 'ACTIVE', $authorization->hasSubjectOfferingPermission($this->f['user'], 'SCHOOL', $this->f['schoolA'], $this->f['offeringA'], 'GRADEBOOK_VIEW'));
        $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        $this->service()->changeStatus($this->f['schoolA'], $this->f['actor'], $id, $to);
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }
    public static function statusFlows(): array
    {
        return [['DRAFT', 'ACTIVE', 'INACTIVE'], ['ACTIVE', 'ACTIVE', 'INACTIVE'], ['DRAFT', 'INACTIVE', 'ACTIVE'], ['ACTIVE', 'INACTIVE', 'ACTIVE']];
    }

    public function test_create_of_inactive_pair_reactivates_the_existing_row_with_status_audit(): void
    {
        $id = $this->fixtureScope('INACTIVE');
        self::assertSame($id, $this->create());
        self::assertCount(1, $this->rows('SELECT * FROM permission_scopes'));
        self::assertSame('ACTIVE', $this->row('permission_scopes', $id)['status']);
        $this->assertAudit($id, 'TEACHING_ASSIGNMENT_STATUS_CHANGED', ['status' => 'INACTIVE'], ['status' => 'ACTIVE']);
    }

    #[DataProvider('inactiveParents')]
    public function test_deactivation_allows_revoked_parents_but_reactivation_does_not(string $table, string $column, mixed $value, string $target): void
    {
        $id = $this->fixtureScope();
        $this->revoke($table, $column, $value, $target);
        $service = $this->service();
        $service->changeStatus($this->f['schoolA'], $this->f['actor'], $id, 'INACTIVE', '192.0.2.70');
        self::assertSame('INACTIVE', $this->row('permission_scopes', $id)['status']);
        $this->assertAudit($id, 'TEACHING_ASSIGNMENT_STATUS_CHANGED', ['status' => 'ACTIVE'], ['status' => 'INACTIVE']);
        $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        $service->changeStatus($this->f['schoolA'], $this->f['actor'], $id, 'INACTIVE');
        $this->deny(fn () => $service->changeStatus($this->f['schoolA'], $this->f['actor'], $id, 'ACTIVE'));
        $this->deny(fn () => $this->create());
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }
    public static function inactiveParents(): array
    {
        return [...self::teacherRevocations(), 'inactive offering' => ['subject_offerings', 'status', 'INACTIVE', 'offeringA']];
    }

    #[DataProvider('closedTransitions')]
    public function test_closed_year_denies_even_noop_mutations_and_preserves_readable_history(string $from, string $to): void
    {
        $id = $this->fixtureScope($from);
        $this->revoke('academic_years', 'status', 'CLOSED', 'yearA');
        $service = $this->service(); $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        $this->deny(fn () => $this->create());
        $this->deny(fn () => $service->changeStatus($this->f['schoolA'], $this->f['actor'], $id, $to));
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
        self::assertSame($id, $this->repo()->findForSchool($this->f['schoolA'], $id)['id']);
        self::assertCount(1, $this->repo()->listForSchool($this->f['schoolA']));
    }
    public static function closedTransitions(): array { return [['ACTIVE', 'INACTIVE'], ['INACTIVE', 'ACTIVE'], ['ACTIVE', 'ACTIVE'], ['INACTIVE', 'INACTIVE']]; }

    #[DataProvider('schoolStates')]
    public function test_unavailable_school_denies_every_mutation(string $state): void
    {
        $id = $this->fixtureScope(); $this->revoke('schools', 'status', $state, 'schoolA');
        $service = $this->service(); $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        $this->deny(fn () => $this->create());
        foreach (['ACTIVE', 'INACTIVE'] as $status) {
            $this->deny(fn () => $service->changeStatus($this->f['schoolA'], $this->f['actor'], $id, $status));
        }
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }
    public static function schoolStates(): array { return [['INACTIVE'], ['SUSPENDED']]; }

    public function test_foreign_and_missing_identifiers_are_non_enumerating_and_status_is_strict(): void
    {
        $id = $this->fixtureScope();
        $foreign = $this->insert('permission_scopes', ['school_id' => $this->f['schoolB'], 'academic_year_id' => $this->f['yearB'],
            'subject_offering_id' => $this->f['offeringB'], 'user_role_assignment_id' => $this->f['foreignAssignment'], 'assigned_by' => $this->f['actor']]);
        $service = $this->service(); $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        foreach (['', 'active', 'inactive', 'CLOSED', 'UNKNOWN', 'ACTIVE '] as $status) {
            $this->deny(fn () => $service->changeStatus($this->f['schoolA'], $this->f['actor'], $id, $status));
        }
        $messages = [];
        foreach ([$foreign, 0, -1, PHP_INT_MAX] as $target) {
            $messages[] = $this->deny(fn () => $service->changeStatus($this->f['schoolA'], $this->f['actor'], $target, 'INACTIVE'));
            self::assertNull($this->repo()->findForSchool($this->f['schoolA'], $target));
        }
        self::assertCount(1, array_unique($messages));
        $foreignMessage = $this->deny(fn () => $this->create(['subjectOfferingId' => $this->f['offeringB']]));
        self::assertSame($foreignMessage, $this->deny(fn () => $this->create(['subjectOfferingId' => 0])));
        $foreignMessage = $this->deny(fn () => $this->create(['userRoleAssignmentId' => $this->f['foreignAssignment']]));
        self::assertSame($foreignMessage, $this->deny(fn () => $this->create(['userRoleAssignmentId' => 0])));
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }

    public function test_reactivation_cannot_switch_to_a_new_role_assignment_for_the_same_teacher(): void
    {
        $id = $this->fixtureScope('INACTIVE');
        $this->revoke('user_role_assignments', 'status', 'INACTIVE', 'assignment');
        $this->insert('user_role_assignments', ['school_id' => $this->f['schoolA'], 'user_id' => $this->f['user'], 'role_id' => $this->f['role']]);
        $before = $this->snapshot();
        $this->deny(fn () => $this->service()->changeStatus($this->f['schoolA'], $this->f['actor'], $id, 'ACTIVE'));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('failureCases')]
    public function test_begin_write_audit_and_commit_failures_roll_back_without_internal_details(string $action, string $failure): void
    {
        $id = $this->fixtureScope($action === 'deactivate' ? 'ACTIVE' : 'INACTIVE');
        $service = $this->service(); $before = $this->snapshot();
        if ($failure === 'begin') { $this->pdo->failBegin = true; }
        elseif ($failure === 'commit') { $this->pdo->failCommit = true; }
        else { $this->pdo->failPrepare = $failure === 'audit' ? 'INSERT INTO audit_logs' : ($action === 'create' ? 'INSERT INTO permission_scopes' : 'UPDATE permission_scopes'); }
        $this->deny(fn () => $this->operate($service, $action, $id));
        self::assertTrue($this->pdo->failureTriggered);
        self::assertSame(1, $this->pdo->depth);
        self::assertTrue($this->pdo->inTransaction());
        self::assertSame($before, $this->snapshot());
    }
    public static function failureCases(): array
    {
        $cases = [];
        foreach (['create', 'deactivate', 'reactivate'] as $action) {
            foreach (['begin', 'write', 'audit', 'commit'] as $failure) { $cases[$action . ' ' . $failure] = [$action, $failure]; }
        }
        return $cases;
    }

    #[DataProvider('lockRaces')]
    public function test_changes_before_authoritative_lock_are_revalidated(string $action, string $table, string $column, mixed $value, string $target, string $beforeLock): void
    {
        $id = $this->fixtureScope('INACTIVE'); $service = $this->service(); $before = $this->snapshot();
        $this->pdo->beforeLock = function (string $query) use ($table, $column, $value, $target, $beforeLock): void {
            if (str_contains($query, $beforeLock)) {
                $this->pdo->beforeLock = null;
                $this->pdo->hookTriggered = true;
                $this->revoke($table, $column, $value, $target);
            }
        };
        $this->deny(fn () => $this->operate($service, $action, $id));
        self::assertTrue($this->pdo->hookTriggered);
        self::assertSame($before, $this->snapshot());
    }
    public static function lockRaces(): array
    {
        $cases = [];
        foreach (['create', 'reactivate'] as $action) {
            foreach ([
                ['academic_years', 'status', 'CLOSED', 'yearA', 'FROM academic_years'],
                ['subject_offerings', 'status', 'INACTIVE', 'offeringA', 'FROM subject_offerings'],
                ['school_memberships', 'status', 'SUSPENDED', 'membership', 'FROM user_role_assignments'],
                ['user_role_assignments', 'status', 'INACTIVE', 'assignment', 'FROM user_role_assignments'],
                ['roles', 'status', 'INACTIVE', 'role', 'FROM user_role_assignments'],
            ] as $change) { if ($action === 'create' && $change[0] === 'subject_offerings') { $change[3] = 'otherOffering'; } $cases[$action . ' ' . $change[0]] = [$action, ...$change]; }
        }
        return $cases;
    }

    public function test_mutations_lock_school_year_offering_teacher_then_scope_in_one_transaction(): void
    {
        $id = $this->fixtureScope('INACTIVE');
        foreach (['create', 'reactivate'] as $action) {
            $this->pdo->beginTransaction(); $this->pdo->queries = [];
            $this->operate($this->service(), $action, $id);
            $locks = array_values(array_filter($this->pdo->queries, static fn (string $q): bool => str_contains($q, 'FOR UPDATE')));
            self::assertCount(5, $locks);
            foreach (['schools', 'academic_years', 'subject_offerings', 'user_role_assignments', 'permission_scopes'] as $index => $table) {
                self::assertStringContainsString('FROM ' . $table, $locks[$index]);
                if ($table !== 'schools') { self::assertStringContainsString('school_id = ?', $locks[$index]); }
            }
            self::assertStringContainsString('school_memberships', $locks[3]);
            self::assertStringContainsString('roles', $locks[3]);
            self::assertSame(2, $this->pdo->depth);
            $this->pdo->rollBack();
        }
    }

    public function test_scope_status_is_reread_under_lock_before_deciding_noop_and_audit(): void
    {
        $id = $this->fixtureScope('INACTIVE');
        $service = $this->service();
        $this->pdo->beforeLock = function (string $query) use ($id): void {
            if (str_contains($query, 'FROM permission_scopes')) {
                $this->pdo->beforeLock = null;
                $this->pdo->hookTriggered = true;
                $this->pdo->prepare("UPDATE permission_scopes SET status='ACTIVE' WHERE id=?")->execute([$id]);
                $this->pdo->writeAttempts = 0;
            }
        };
        $service->changeStatus($this->f['schoolA'], $this->f['actor'], $id, 'ACTIVE');
        self::assertTrue($this->pdo->hookTriggered);
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame('ACTIVE', $this->row('permission_scopes', $id)['status']);
        self::assertSame([], $this->rows('SELECT * FROM audit_logs'));
    }

    public function test_scope_identity_changed_before_lock_cannot_reuse_previously_validated_teacher(): void
    {
        $id = $this->fixtureScope('INACTIVE');
        $wrongAssignment = $this->insert('user_role_assignments', ['school_id' => $this->f['schoolA'],
            'user_id' => $this->f['user'], 'role_id' => $this->role('VIEWER')]);
        $service = $this->service(); $before = $this->snapshot();
        $this->pdo->beforeLock = function (string $query) use ($id, $wrongAssignment): void {
            if (str_contains($query, 'FROM permission_scopes')) {
                $this->pdo->beforeLock = null;
                $this->pdo->hookTriggered = true;
                $this->pdo->prepare('UPDATE permission_scopes SET user_role_assignment_id=? WHERE id=?')->execute([$wrongAssignment, $id]);
            }
        };
        $this->deny(fn () => $service->changeStatus($this->f['schoolA'], $this->f['actor'], $id, 'ACTIVE'));
        self::assertTrue($this->pdo->hookTriggered);
        self::assertSame($before, $this->snapshot());
    }

    public function test_teacher_list_database_failure_uses_safe_domain_error(): void
    {
        $service = $this->service();
        $this->pdo->failPrepare = 'FROM user_role_assignments';
        $this->deny(fn () => $service->listSubjectTeachers($this->f['schoolA']));
        self::assertTrue($this->pdo->failureTriggered);
    }

    private function service(): TeachingAssignmentService
    {
        self::assertTrue(class_exists(TeachingAssignmentService::class), 'Missing TeachingAssignmentService');
        return new TeachingAssignmentService($this->pdo, new SchoolRepository($this->pdo), new AcademicYearRepository($this->pdo),
            new SubjectOfferingRepository($this->pdo), $this->repo(), new AuditLogRepository($this->pdo));
    }
    private function repo(): TeachingAssignmentRepository
    {
        self::assertTrue(class_exists(TeachingAssignmentRepository::class), 'Missing TeachingAssignmentRepository');
        return new TeachingAssignmentRepository($this->pdo);
    }
    private function create(array $overrides = []): int
    {
        return $this->service()->createAssignment(...array_replace(['schoolId' => $this->f['schoolA'], 'actorUserId' => $this->f['actor'],
            'userRoleAssignmentId' => $this->f['assignment'], 'subjectOfferingId' => $this->f['offeringA'], 'ipAddress' => '192.0.2.70'], $overrides));
    }
    private function operate(TeachingAssignmentService $service, string $action, int $id): void
    {
        if ($action === 'create') { $service->createAssignment($this->f['schoolA'], $this->f['actor'], $this->f['assignment'], $this->f['otherOffering']); }
        else { $service->changeStatus($this->f['schoolA'], $this->f['actor'], $id, $action === 'deactivate' ? 'INACTIVE' : 'ACTIVE'); }
    }
    private function assertAudit(int $id, string $action, ?array $old, array $new): void
    {
        $rows = $this->rows('SELECT * FROM audit_logs ORDER BY id'); self::assertCount(1, $rows); $row = $rows[0];
        self::assertSame([$this->f['schoolA'], $this->f['actor'], 'permission_scopes', $id, $action, '192.0.2.70', null],
            array_map(static fn (string $k): mixed => $row[$k], ['school_id', 'user_id', 'entity_type', 'entity_id', 'action', 'ip_address', 'reason']));
        self::assertSame($old, $row['old_value'] === null ? null : json_decode($row['old_value'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame($new, json_decode($row['new_value'], true, 512, JSON_THROW_ON_ERROR));
        self::assertNotEmpty($row['created_at']);
    }
    private function deny(callable $operation): string
    {
        try { $operation(); self::fail('Expected safe DomainException'); }
        catch (DomainException $e) {
            self::assertNotSame('', $e->getMessage()); self::assertNull($e->getPrevious());
            foreach (['SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', 'uq_', 'fk_', 'private-db-details', '/Applications/MAMP/', 'htdocs/app/', 'password', 'cookie', 'national_id'] as $secret) {
                self::assertStringNotContainsString($secret, $e->getMessage());
            }
            return $e->getMessage();
        }
    }
    private function fixtureScope(string $status = 'ACTIVE'): int
    {
        return $this->insert('permission_scopes', ['school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'],
            'user_role_assignment_id' => $this->f['assignment'], 'subject_offering_id' => $this->f['offeringA'], 'assigned_by' => $this->f['actor'], 'status' => $status]);
    }
    private function fixtureScore(): void
    {
        $grade = (int) $this->pdo->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        $student = $this->insert('students', ['school_id' => $this->f['schoolA'], 'student_code' => 'history', 'prefix_th' => 'ด.ช.', 'first_name_th' => 'ทดสอบ', 'last_name_th' => 'สมมติ']);
        $enrollment = $this->insert('student_enrollments', ['school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'], 'student_id' => $student, 'grade_level_id' => $grade]);
        $component = $this->insert('gradebook_components', ['school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'], 'subject_offering_id' => $this->f['offeringA'],
            'code' => 'HISTORY', 'name_th' => 'คะแนนเดิม', 'max_score' => '20.00', 'sort_order' => 1]);
        $this->insert('gradebook_scores', ['school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'], 'subject_offering_id' => $this->f['offeringA'],
            'enrollment_id' => $enrollment, 'component_id' => $component, 'score' => '0.00', 'updated_by' => $this->f['actor']]);
    }
    private function revoke(string $table, string $column, mixed $value, string $target): void
    {
        $this->pdo->prepare("UPDATE {$table} SET {$column}=? WHERE id=?")->execute([$value === 'year' ? $this->f['yearA'] : $value, $this->f[$target]]);
    }
    private function role(string $code): int
    {
        $q = $this->pdo->prepare('SELECT id FROM roles WHERE code=?'); $q->execute([$code]); return (int) $q->fetchColumn();
    }
    private function row(string $table, int $id): array { return $this->rows("SELECT * FROM {$table} WHERE id=?", [$id])[0]; }
    private function rows(string $sql, array $params = []): array { $q = $this->pdo->prepare($sql); $q->execute($params); return $q->fetchAll(); }
    private function snapshot(): array
    {
        $result = [];
        foreach (['permission_scopes', 'gradebook_components', 'gradebook_scores', 'audit_logs', 'user_role_assignments', 'school_memberships', 'roles', 'schools', 'academic_years', 'subject_offerings'] as $table) {
            $result[$table] = $this->rows("SELECT * FROM {$table} ORDER BY id");
        }
        return $result;
    }
    private function insert(string $table, array $values): int
    {
        $q = $this->pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($values)) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')');
        $q->execute(array_values($values)); return (int) $this->pdo->lastInsertId();
    }
}

/** Keep service transactions inside fixture rollback using real savepoints. */
final class TeachingAssignmentTestPDO extends PDO
{
    public int $depth = 0;
    public int $writeAttempts = 0;
    public bool $failBegin = false;
    public bool $failCommit = false;
    public bool $failureTriggered = false;
    public ?string $failPrepare = null;
    public array $queries = [];
    public ?Closure $beforeLock = null;
    public bool $hookTriggered = false;

    public function beginTransaction(): bool
    {
        if ($this->failBegin) { $this->failBegin = false; $this->failureTriggered = true; throw new PDOException('SQLSTATE private-db-details'); }
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT teaching_assignment_' . $this->depth) !== false;
        ++$this->depth;
        return $ok;
    }
    public function commit(): bool
    {
        if ($this->failCommit) { $this->failCommit = false; $this->failureTriggered = true; throw new PDOException('SQLSTATE private-db-details'); }
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT teaching_assignment_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else { $ok = $this->exec('ROLLBACK TO SAVEPOINT teaching_assignment_' . ($this->depth - 1)) !== false; $this->exec('RELEASE SAVEPOINT teaching_assignment_' . ($this->depth - 1)); }
        --$this->depth;
        return $ok;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        if ($this->beforeLock !== null && str_contains($query, 'FOR UPDATE')) { ($this->beforeLock)($query); }
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $query)) { ++$this->writeAttempts; }
        if ($this->failPrepare !== null && str_contains($query, $this->failPrepare)) {
            $this->failPrepare = null; $this->failureTriggered = true;
            throw new PDOException('SQLSTATE private-db-details');
        }
        return parent::prepare($query, $options);
    }
}
