<?php
declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\AuthorizationRepository;
use App\Repositories\RoleAssignmentRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SchoolMembershipRepository;
use App\Repositories\UserRepository;
use App\Services\SchoolUserAdministrationService;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchoolUserAdministrationTest extends TestCase
{
    private SchoolUserTestPDO $pdo;
    private int $schoolId;
    private int $foreignSchoolId;
    private int $academicYearId;
    private int $actorId;
    private int $targetId;
    private int $foreignUserId;
    private int $membershipId;
    private static ?string $originalHash = null;
    private const PASSWORD = 'school-user-secret-password';
    private const ORIGINAL_PASSWORD = 'original-user-password';

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new SchoolUserTestPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        self::$originalHash ??= password_hash(self::ORIGINAL_PASSWORD, PASSWORD_DEFAULT);
        $this->schoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['school-user-a', 'School A']);
        $this->foreignSchoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['school-user-b', 'School B']);
        $this->academicYearId = $this->insert('INSERT INTO academic_years (school_id, year_be) VALUES (?, ?)', [$this->schoolId, 2569]);
        $this->actorId = $this->fixtureUser('school-user-actor', $this->schoolId, 'SCHOOL_ADMIN');
        $this->targetId = $this->fixtureUser('school-user-target', $this->schoolId, 'VIEWER');
        $this->foreignUserId = $this->fixtureUser('school-user-foreign', $this->foreignSchoolId, 'VIEWER');
        $this->membershipId = $this->row('SELECT id FROM school_memberships WHERE user_id = ?', [$this->targetId])['id'];
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->serviceAborted = false;
            while ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    public function test_create_user_is_atomic_and_scoped_with_requested_roles_and_safe_audit(): void
    {
        $before = $this->snapshot();
        $userId = $this->create(['roleCodes' => [' VIEWER ', 'HOMEROOM_TEACHER', 'VIEWER', '', '  ']]);
        self::assertIsInt($userId);
        $user = $this->row('SELECT * FROM users WHERE id = ?', [$userId]);
        self::assertSame('school-user-new', $user['username']);
        self::assertSame('ผู้ใช้ใหม่', $user['display_name']);
        self::assertSame('new-user@example.test', $user['email']);
        self::assertSame('ACTIVE', $user['status']);
        self::assertTrue(password_verify(self::PASSWORD, $user['password_hash']));
        $membership = $this->row('SELECT * FROM school_memberships WHERE user_id = ?', [$userId]);
        self::assertSame($this->schoolId, $membership['school_id']);
        self::assertSame('ACTIVE', $membership['status']);
        self::assertSame($this->actorId, $membership['created_by']);
        $assignments = $this->rows('SELECT ura.*, r.code FROM user_role_assignments ura JOIN roles r ON r.id = ura.role_id WHERE ura.user_id = ? ORDER BY r.code', [$userId]);
        self::assertSame(['HOMEROOM_TEACHER', 'VIEWER'], array_column($assignments, 'code'));
        foreach ($assignments as $assignment) {
            self::assertSame($this->schoolId, $assignment['school_id']);
            self::assertSame('ACTIVE', $assignment['status']);
            self::assertNull($assignment['academic_year_id']);
            self::assertSame($this->actorId, $assignment['assigned_by']);
        }
        self::assertSame(1, $this->pdo->depth);
        self::assertCount(count($before['users']) + 1, $this->snapshot()['users']);
        self::assertCount(count($before['school_memberships']) + 1, $this->snapshot()['school_memberships']);
        $audits = $this->rows('SELECT * FROM audit_logs ORDER BY id');
        self::assertSame(['SCHOOL_USER_CREATED', 'SCHOOL_ROLES_CHANGED'], array_column($audits, 'action'));
        self::assertNull($audits[0]['old_value']);
        self::assertSame('school-user-new', json_decode($audits[0]['new_value'], true)['username']);
        self::assertSame(['role_codes' => []], json_decode($audits[1]['old_value'], true));
        self::assertSame(['role_codes' => ['HOMEROOM_TEACHER', 'VIEWER']], json_decode($audits[1]['new_value'], true));
        foreach ($audits as $audit) {
            $this->assertAudit($audit, $userId, 'users');
            $this->assertNoSecrets($audit, $user['password_hash']);
        }
        $this->assertForeignUnchanged($before);
    }

    public function test_create_normalizes_profile_and_preserves_password_whitespace(): void
    {
        $password = ' 1234567890 ';
        $userId = $this->create(['username' => ' abc ', 'displayName' => ' ก ', 'email' => '  ', 'password' => $password]);
        $user = $this->row('SELECT * FROM users WHERE id = ?', [$userId]);
        self::assertSame('abc', $user['username']);
        self::assertSame('ก', $user['display_name']);
        self::assertNull($user['email']);
        self::assertTrue(password_verify($password, $user['password_hash']));
    }

    public function test_create_accepts_maximum_username_and_unicode_name_lengths(): void
    {
        $userId = $this->create(['username' => str_repeat('a', 97) . '._-', 'displayName' => str_repeat('ก', 190)]);
        self::assertSame(str_repeat('ก', 190), $this->row('SELECT display_name FROM users WHERE id = ?', [$userId])['display_name']);
    }

    #[DataProvider('invalidCreateFields')]
    public function test_invalid_create_fields_leave_no_rows(string $field, mixed $value): void
    {
        $before = $this->snapshot();
        $this->deny(fn () => $this->create([$field => $value]));
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidCreateFields(): array
    {
        return ['blank username' => ['username', ' '], 'short username' => ['username', 'ab'],
            'long username' => ['username', str_repeat('a', 101)], 'invalid username' => ['username', 'bad/name'],
            'blank display name' => ['displayName', ' '], 'long display name' => ['displayName', str_repeat('ก', 191)],
            'invalid email' => ['email', 'invalid-email'], 'short password' => ['password', 'shortsecret']];
    }

    #[DataProvider('invalidRoleInputs')]
    public function test_invalid_roles_are_rejected_before_create_writes_and_during_replacement(array $roles, ?string $roleChange): void
    {
        if ($roleChange !== null) {
            $this->pdo->prepare($roleChange)->execute();
        }
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->deny(fn () => $this->create(['roleCodes' => $roles]));
        self::assertSame(0, $this->pdo->writeAttempts, 'Resolve all roles before creating any rows.');
        self::assertSame($before, $this->snapshot());
        $this->deny(fn () => $this->service()->replaceRoles($this->schoolId, $this->actorId, $this->targetId, $roles));
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidRoleInputs(): array
    {
        return ['no roles' => [[], null], 'blank roles' => [[' ', ''], null], 'non string' => [['VIEWER', 1], null],
            'nested array' => [[['VIEWER']], null], 'unknown' => [['UNKNOWN'], null],
            'system admin' => [['SYSTEM_ADMIN'], null],
            'inactive role' => [['VIEWER'], "UPDATE roles SET status = 'INACTIVE' WHERE code = 'VIEWER'"],
            'system scope' => [['VIEWER'], "UPDATE roles SET scope_type = 'SYSTEM' WHERE code = 'VIEWER'"],
            'misclassified system admin' => [['SYSTEM_ADMIN'], "UPDATE roles SET scope_type = 'SCHOOL' WHERE code = 'SYSTEM_ADMIN'"],
            'valid then invalid' => [['VIEWER', 'UNKNOWN'], null]];
    }

    #[DataProvider('duplicateIdentities')]
    public function test_duplicate_create_identity_rolls_back_without_linking_existing_user(string $field, string $value): void
    {
        $before = $this->snapshot();
        $this->deny(fn () => $this->create([$field => $value]));
        self::assertSame($before, $this->snapshot());
        self::assertSame(1, $this->pdo->depth);
    }

    public static function duplicateIdentities(): array
    {
        return ['username' => ['username', 'school-user-foreign'], 'email' => ['email', 'school-user-foreign@example.test']];
    }

    #[DataProvider('createFailures')]
    public function test_create_failure_at_each_step_rolls_back_every_row(string $query, int $skip): void
    {
        $before = $this->snapshot();
        $this->pdo->failPrepare = $query;
        $this->pdo->skipMatches = $skip;
        $this->deny(fn () => $this->create(['roleCodes' => ['HOMEROOM_TEACHER', 'VIEWER']]));
        self::assertSame($before, $this->snapshot());
        self::assertSame(1, $this->pdo->depth);
    }

    public static function createFailures(): array
    {
        return ['user' => ['INSERT INTO users', 0], 'membership' => ['INSERT INTO school_memberships', 0],
            'second role' => ['INSERT INTO user_role_assignments', 1], 'second audit' => ['INSERT INTO audit_logs', 1]];
    }

    #[DataProvider('targetOperations')]
    public function test_foreign_missing_or_inactive_membership_targets_are_denied(string $operation): void
    {
        $before = $this->snapshot();
        $this->deny(fn () => $this->operate($operation, $this->foreignUserId));
        $this->deny(fn () => $this->operate($operation, 0));
        self::assertSame($before, $this->snapshot());
        $this->pdo->prepare("UPDATE school_memberships SET status = 'INACTIVE' WHERE id = ?")->execute([$this->membershipId]);
        $before = $this->snapshot();
        $this->deny(fn () => $this->operate($operation, $this->targetId));
        self::assertSame($before, $this->snapshot());
    }

    public static function targetOperations(): array
    {
        return ['profile' => ['profile'], 'membership' => ['membership'], 'roles' => ['roles'], 'password' => ['password']];
    }

    #[DataProvider('targetOperations')]
    public function test_target_write_and_audit_are_atomic(string $operation): void
    {
        $before = $this->snapshot();
        $this->pdo->failPrepare = 'INSERT INTO audit_logs';
        $this->deny(fn () => $this->operate($operation, $this->targetId));
        self::assertSame($before, $this->snapshot());
        self::assertSame(1, $this->pdo->depth);
    }

    #[DataProvider('editableMemberships')]
    public function test_profile_updates_only_name_and_email_for_active_or_suspended_membership(string $status): void
    {
        $this->pdo->prepare('UPDATE school_memberships SET status = ? WHERE id = ?')->execute([$status, $this->membershipId]);
        $before = $this->snapshot();
        $old = $this->row('SELECT * FROM users WHERE id = ?', [$this->targetId]);
        $this->service()->updateProfile($this->schoolId, $this->actorId, $this->targetId, ' ชื่อใหม่ ', ' updated@example.test ', '192.0.2.6');
        $user = $this->row('SELECT * FROM users WHERE id = ?', [$this->targetId]);
        self::assertSame('ชื่อใหม่', $user['display_name']);
        self::assertSame('updated@example.test', $user['email']);
        foreach (['username', 'password_hash', 'status', 'created_at'] as $key) {
            self::assertSame($old[$key], $user[$key]);
        }
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame('SCHOOL_USER_UPDATED', $audit['action']);
        self::assertSame(['display_name' => $old['display_name'], 'email' => $old['email']], json_decode($audit['old_value'], true));
        self::assertSame(['display_name' => 'ชื่อใหม่', 'email' => 'updated@example.test'], json_decode($audit['new_value'], true));
        $this->assertAudit($audit, $this->targetId, 'users');
        $this->assertNoSecrets($audit, $old['password_hash']);
        $this->assertForeignUnchanged($before);
    }

    public static function editableMemberships(): array
    {
        return ['active' => ['ACTIVE'], 'suspended' => ['SUSPENDED']];
    }

    public function test_profile_blank_email_becomes_null(): void
    {
        $this->service()->updateProfile($this->schoolId, $this->actorId, $this->targetId, 'Updated', '  ');
        self::assertNull($this->row('SELECT email FROM users WHERE id = ?', [$this->targetId])['email']);
    }

    #[DataProvider('invalidProfiles')]
    public function test_invalid_or_duplicate_profile_is_rejected_atomically(string $name, ?string $email): void
    {
        $before = $this->snapshot();
        $this->deny(fn () => $this->service()->updateProfile($this->schoolId, $this->actorId, $this->targetId, $name, $email));
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidProfiles(): array
    {
        return ['blank name' => [' ', null], 'long name' => [str_repeat('ก', 191), null],
            'invalid email' => ['Name', 'invalid-email'], 'duplicate email' => ['Name', 'school-user-foreign@example.test']];
    }

    #[DataProvider('membershipTransitions')]
    public function test_membership_status_changes_are_audited_and_tenant_scoped(string $old, string $new): void
    {
        $this->pdo->prepare('UPDATE school_memberships SET status = ? WHERE id = ?')->execute([$old, $this->membershipId]);
        $before = $this->snapshot();
        $this->service()->changeMembershipStatus($this->schoolId, $this->actorId, $this->targetId, $new, '192.0.2.6');
        self::assertSame($new, $this->row('SELECT status FROM school_memberships WHERE id = ?', [$this->membershipId])['status']);
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame('MEMBERSHIP_STATUS_CHANGED', $audit['action']);
        self::assertSame(['status' => $old], json_decode($audit['old_value'], true));
        self::assertSame(['status' => $new], json_decode($audit['new_value'], true));
        $this->assertAudit($audit, $this->membershipId, 'school_memberships');
        $this->assertForeignUnchanged($before);
    }

    public static function membershipTransitions(): array
    {
        return ['suspend' => ['ACTIVE', 'SUSPENDED'], 'reactivate' => ['SUSPENDED', 'ACTIVE']];
    }

    #[DataProvider('invalidMembershipStatuses')]
    public function test_invalid_membership_status_is_rejected(string $status): void
    {
        $before = $this->snapshot();
        $this->deny(fn () => $this->service()->changeMembershipStatus($this->schoolId, $this->actorId, $this->targetId, $status));
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidMembershipStatuses(): array
    {
        return ['inactive' => ['INACTIVE'], 'unknown' => ['DELETED'], 'lowercase' => ['active'], 'padded' => [' ACTIVE ']];
    }

    public function test_actor_cannot_suspend_self(): void
    {
        $before = $this->snapshot();
        $this->deny(fn () => $this->service()->changeMembershipStatus($this->schoolId, $this->actorId, $this->actorId, 'SUSPENDED'));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('editableMemberships')]
    public function test_same_membership_status_is_a_no_op_without_audit(string $status): void
    {
        $this->pdo->prepare('UPDATE school_memberships SET status = ? WHERE id = ?')->execute([$status, $this->membershipId]);
        $before = $this->snapshot();
        $this->service()->changeMembershipStatus($this->schoolId, $this->actorId, $this->targetId, $status);
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('invalidReactivations')]
    public function test_reactivation_cannot_create_invalid_active_membership_configuration(string $configuration): void
    {
        $this->pdo->prepare("UPDATE school_memberships SET status = 'SUSPENDED' WHERE id = ?")->execute([$this->membershipId]);
        if ($configuration === 'roleless') {
            $this->pdo->prepare("UPDATE user_role_assignments SET status = 'INACTIVE' WHERE user_id = ?")->execute([$this->targetId]);
        } elseif ($configuration === 'system') {
            $this->assignment($this->targetId, null, 'SYSTEM_ADMIN');
        } else {
            $this->insert('INSERT INTO school_memberships (user_id, school_id) VALUES (?, ?)', [$this->targetId, $this->foreignSchoolId]);
            $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?')->execute([$configuration, $this->foreignSchoolId]);
        }
        $before = $this->snapshot();
        $this->deny(fn () => $this->service()->changeMembershipStatus($this->schoolId, $this->actorId, $this->targetId, 'ACTIVE'));
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidReactivations(): array
    {
        return ['roleless' => ['roleless'], 'system admin' => ['system'], 'second active row' => ['ACTIVE'],
            'second active row suspended school' => ['SUSPENDED'], 'second active row inactive school' => ['INACTIVE']];
    }

    public function test_role_replacement_deactivates_and_reactivates_rows_without_deletion(): void
    {
        $before = $this->snapshot();
        $viewerId = $this->row('SELECT id FROM user_role_assignments WHERE user_id = ?', [$this->targetId])['id'];
        $teacherId = $this->assignment($this->targetId, $this->schoolId, 'HOMEROOM_TEACHER', 'INACTIVE');
        $service = $this->service();
        $service->replaceRoles($this->schoolId, $this->actorId, $this->targetId, [' HOMEROOM_TEACHER ', 'HOMEROOM_TEACHER', ''], '192.0.2.6');
        self::assertSame('INACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$viewerId])['status']);
        $teacher = $this->row('SELECT * FROM user_role_assignments WHERE id = ?', [$teacherId]);
        self::assertSame('ACTIVE', $teacher['status']);
        self::assertSame($this->actorId, $teacher['assigned_by']);
        self::assertNull($teacher['academic_year_id']);
        $service->replaceRoles($this->schoolId, $this->actorId, $this->targetId, ['VIEWER'], '192.0.2.6');
        self::assertSame('ACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$viewerId])['status']);
        self::assertSame('INACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$teacherId])['status']);
        self::assertCount(2, $this->rows('SELECT * FROM user_role_assignments WHERE user_id = ?', [$this->targetId]));
        $audits = $this->rows('SELECT * FROM audit_logs ORDER BY id');
        self::assertCount(2, $audits);
        foreach ($audits as $audit) {
            self::assertSame('SCHOOL_ROLES_CHANGED', $audit['action']);
            $this->assertAudit($audit, $this->targetId, 'users');
            $this->assertNoSecrets($audit);
        }
        self::assertSame(['role_codes' => ['VIEWER']], json_decode($audits[0]['old_value'], true));
        self::assertSame(['role_codes' => ['HOMEROOM_TEACHER']], json_decode($audits[0]['new_value'], true));
        $this->assertForeignUnchanged($before);
    }

    public function test_same_normalized_roles_are_a_no_op_without_audit(): void
    {
        $before = $this->snapshot();
        $this->service()->replaceRoles($this->schoolId, $this->actorId, $this->targetId, [' VIEWER ', 'VIEWER', ' ']);
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('staleRoleDefinitions')]
    public function test_replacement_deactivates_stale_school_wide_assignments_without_touching_other_scopes(string $roleStatus, string $roleScope): void
    {
        $viewerId = $this->row('SELECT id FROM user_role_assignments WHERE user_id = ?', [$this->targetId])['id'];
        $executiveId = $this->assignment($this->targetId, $this->schoolId, 'EXECUTIVE');
        $this->insert('INSERT INTO school_memberships (user_id, school_id, status) VALUES (?, ?, ?)',
            [$this->targetId, $this->foreignSchoolId, 'SUSPENDED']);
        $unrelatedIds = [
            $this->assignment($this->targetId, $this->foreignSchoolId, 'EXECUTIVE'),
            $this->assignment($this->foreignUserId, $this->foreignSchoolId, 'EXECUTIVE'),
            $this->assignment($this->actorId, $this->schoolId, 'EXECUTIVE'),
            $this->assignment($this->targetId, $this->schoolId, 'EXECUTIVE', 'ACTIVE', $this->academicYearId),
            $this->assignment($this->targetId, null, 'EXECUTIVE'),
        ];
        $unrelated = [];
        foreach ($unrelatedIds as $id) {
            $unrelated[$id] = $this->row('SELECT * FROM user_role_assignments WHERE id = ?', [$id]);
        }
        $this->pdo->prepare("UPDATE roles SET status = ?, scope_type = ? WHERE code = 'EXECUTIVE'")
            ->execute([$roleStatus, $roleScope]);
        $repository = new RoleAssignmentRepository($this->pdo);
        self::assertSame(['VIEWER'], $repository->activeSchoolRoleCodes($this->targetId, $this->schoolId));

        $service = $this->service();
        $service->replaceRoles($this->schoolId, $this->actorId, $this->targetId, ['VIEWER'], '192.0.2.6');

        self::assertSame('INACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$executiveId])['status']);
        self::assertSame('ACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$viewerId])['status']);
        self::assertSame([$viewerId, $executiveId], array_column($this->rows(
            'SELECT id FROM user_role_assignments WHERE user_id = ? AND school_id = ? AND academic_year_id IS NULL ORDER BY id',
            [$this->targetId, $this->schoolId]
        ), 'id'));
        foreach ($unrelated as $id => $assignment) {
            self::assertSame($assignment, $this->row('SELECT * FROM user_role_assignments WHERE id = ?', [$id]));
        }
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame('SCHOOL_ROLES_CHANGED', $audit['action']);
        self::assertSame(['role_codes' => ['VIEWER']], json_decode($audit['old_value'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(['role_codes' => ['VIEWER']], json_decode($audit['new_value'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertAudit($audit, $this->targetId, 'users');
        $this->assertNoSecrets($audit);
        foreach (['SQLSTATE', 'SELECT ', 'UPDATE ', 'Stack trace'] as $unsafe) {
            self::assertStringNotContainsString($unsafe, json_encode($audit, JSON_THROW_ON_ERROR));
        }

        // The first cleanup is audited; repeating it makes no assignment or audit changes.
        $afterCleanup = $this->snapshot();
        $service->replaceRoles($this->schoolId, $this->actorId, $this->targetId, ['VIEWER'], '192.0.2.6');
        self::assertSame($afterCleanup, $this->snapshot());

        // Restoring the definition must not silently restore the removed authorization.
        $this->pdo->prepare("UPDATE roles SET status = 'ACTIVE', scope_type = 'SCHOOL' WHERE code = 'EXECUTIVE'")->execute();
        self::assertSame(['VIEWER'], $repository->activeSchoolRoleCodes($this->targetId, $this->schoolId));
        $service->replaceRoles($this->schoolId, $this->actorId, $this->targetId, ['EXECUTIVE', 'VIEWER'], '192.0.2.6');
        self::assertSame('ACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$executiveId])['status']);
        self::assertSame(['EXECUTIVE', 'VIEWER'], $repository->activeSchoolRoleCodes($this->targetId, $this->schoolId));
        self::assertCount(2, $this->rows('SELECT id FROM user_role_assignments WHERE user_id = ? AND school_id = ? AND academic_year_id IS NULL',
            [$this->targetId, $this->schoolId]));
    }

    public static function staleRoleDefinitions(): array
    {
        return ['inactive role definition' => ['INACTIVE', 'SCHOOL'], 'scope drift to system' => ['ACTIVE', 'SYSTEM']];
    }

    public function test_actor_can_add_own_roles_but_cannot_remove_own_school_admin(): void
    {
        $before = $this->snapshot();
        $service = $this->service();
        $this->deny(fn () => $service->replaceRoles($this->schoolId, $this->actorId, $this->actorId, ['VIEWER']));
        self::assertSame($before, $this->snapshot());
        $service->replaceRoles($this->schoolId, $this->actorId, $this->actorId, ['VIEWER', ' SCHOOL_ADMIN ']);
        self::assertSame(['SCHOOL_ADMIN', 'VIEWER'], (new RoleAssignmentRepository($this->pdo))->activeSchoolRoleCodes($this->actorId, $this->schoolId));
    }

    #[DataProvider('editableMemberships')]
    public function test_password_reset_replaces_hash_with_event_only_audit(string $status): void
    {
        $this->pdo->prepare('UPDATE school_memberships SET status = ? WHERE id = ?')->execute([$status, $this->membershipId]);
        $before = $this->snapshot();
        $this->service()->resetPassword($this->schoolId, $this->actorId, $this->targetId, self::PASSWORD, '192.0.2.6');
        $user = $this->row('SELECT * FROM users WHERE id = ?', [$this->targetId]);
        self::assertTrue(password_verify(self::PASSWORD, $user['password_hash']));
        self::assertFalse(password_verify(self::ORIGINAL_PASSWORD, $user['password_hash']));
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertSame('USER_PASSWORD_RESET', $audit['action']);
        self::assertNull($audit['old_value']);
        self::assertSame(['password_reset' => true], json_decode($audit['new_value'], true));
        $this->assertAudit($audit, $this->targetId, 'users');
        $this->assertNoSecrets($audit, $user['password_hash']);
        $this->assertForeignUnchanged($before);
    }

    public function test_short_reset_password_is_rejected_without_changing_hash_or_audit(): void
    {
        $before = $this->snapshot();
        $this->deny(fn () => $this->service()->resetPassword($this->schoolId, $this->actorId, $this->targetId, 'shortsecret'));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('secretBoundaries')]
    public function test_exception_traces_redact_plaintext_and_hash_arguments(string $boundary): void
    {
        $service = $this->service();
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            if ($boundary === 'create') {
                $service->createUser($this->schoolId, $this->actorId, 'new-user', 'Name', null, 'shortsecret', ['VIEWER']);
            } elseif ($boundary === 'reset') {
                $service->resetPassword($this->schoolId, $this->actorId, $this->targetId, 'shortsecret');
            } else {
                $this->pdo->failPrepare = 'UPDATE users';
                (new UserRepository($this->pdo))->updatePasswordHash($this->schoolId, $this->targetId, 'hash-secret');
            }
            self::fail('Expected failure at the selected password boundary.');
        } catch (DomainException|PDOException $exception) {
            self::assertStringNotContainsString('shortsecret', (string) $exception);
            self::assertStringNotContainsString('hash-secret', (string) $exception);
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }

    public static function secretBoundaries(): array
    {
        return ['create plaintext' => ['create'], 'reset plaintext' => ['reset'], 'repository hash' => ['hash']];
    }

    public function test_repository_target_reads_and_lists_are_scoped_and_do_not_return_hashes(): void
    {
        $this->service();
        $repository = new SchoolMembershipRepository($this->pdo);
        self::assertNull($repository->findForSchoolUser($this->schoolId, $this->foreignUserId));
        self::assertNull($repository->findForSchoolUser($this->foreignSchoolId, $this->targetId));
        $target = $repository->findForSchoolUser($this->schoolId, $this->targetId);
        self::assertSame($this->membershipId, $target['id']);
        self::assertSame($this->targetId, $target['user_id']);
        self::assertSame($this->schoolId, $target['school_id']);
        self::assertArrayNotHasKey('password_hash', $target);
        $rows = $repository->listForSchool($this->schoolId);
        self::assertSame([$this->actorId, $this->targetId], array_column($rows, 'user_id'));
        foreach ($rows as $row) {
            self::assertSame($this->schoolId, $row['school_id']);
            self::assertArrayNotHasKey('password_hash', $row);
        }
        self::assertSame([], $repository->listForSchool(0));
    }

    #[DataProvider('repositoryTargetStatuses')]
    public function test_profile_and_password_repository_writes_enforce_school_pair_and_membership_status(string $status, bool $allowed): void
    {
        $this->service();
        $this->pdo->prepare('UPDATE school_memberships SET status = ? WHERE id = ?')->execute([$status, $this->membershipId]);
        $repository = new UserRepository($this->pdo);
        $before = $this->snapshot();
        $repository->updateProfile($this->schoolId, $this->foreignUserId, 'Forbidden', null);
        $repository->updatePasswordHash($this->schoolId, $this->foreignUserId, 'never-stored');
        $repository->updateProfile($this->foreignSchoolId, $this->targetId, 'Forbidden', null);
        $repository->updatePasswordHash($this->foreignSchoolId, $this->targetId, 'never-stored');
        self::assertSame($before, $this->snapshot());
        $repository->updateProfile($this->schoolId, $this->targetId, 'Allowed', null);
        $repository->updatePasswordHash($this->schoolId, $this->targetId, 'replacement-hash');
        $target = $this->row('SELECT * FROM users WHERE id = ?', [$this->targetId]);
        self::assertSame($allowed ? 'Allowed' : 'school-user-target', $target['display_name']);
        self::assertSame($allowed ? 'replacement-hash' : self::$originalHash, $target['password_hash']);
    }

    public static function repositoryTargetStatuses(): array
    {
        return ['active' => ['ACTIVE', true], 'suspended' => ['SUSPENDED', true], 'inactive' => ['INACTIVE', false]];
    }

    public function test_role_list_excludes_inactive_system_scope_and_system_admin_code(): void
    {
        $this->service();
        $this->pdo->prepare("UPDATE roles SET status = 'INACTIVE' WHERE code = 'VIEWER'")->execute();
        $this->pdo->prepare("UPDATE roles SET scope_type = 'SYSTEM' WHERE code = 'EXECUTIVE'")->execute();
        $this->pdo->prepare("UPDATE roles SET scope_type = 'SCHOOL' WHERE code = 'SYSTEM_ADMIN'")->execute();
        self::assertSame(['ACADEMIC_ADMIN', 'HOMEROOM_TEACHER', 'SCHOOL_ADMIN', 'SUBJECT_TEACHER'],
            array_column((new RoleRepository($this->pdo))->listActiveSchoolRoles(), 'code'));
    }

    public function test_active_role_codes_are_distinct_sorted_and_scoped_to_school_wide_active_roles(): void
    {
        $this->service();
        $this->assignment($this->targetId, $this->schoolId, 'VIEWER');
        $this->assignment($this->targetId, $this->schoolId, 'HOMEROOM_TEACHER');
        $this->assignment($this->targetId, $this->schoolId, 'SUBJECT_TEACHER', 'INACTIVE');
        $this->assignment($this->targetId, $this->schoolId, 'ACADEMIC_ADMIN', 'ACTIVE', $this->academicYearId);
        $this->assignment($this->targetId, $this->schoolId, 'EXECUTIVE');
        $this->pdo->prepare("UPDATE roles SET status = 'INACTIVE' WHERE code = 'EXECUTIVE'")->execute();
        $this->assignment($this->targetId, null, 'SYSTEM_ADMIN');
        $this->insert('INSERT INTO school_memberships (user_id, school_id, status) VALUES (?, ?, ?)', [$this->targetId, $this->foreignSchoolId, 'SUSPENDED']);
        $this->assignment($this->targetId, $this->foreignSchoolId, 'SCHOOL_ADMIN');
        $repository = new RoleAssignmentRepository($this->pdo);
        self::assertSame(['HOMEROOM_TEACHER', 'VIEWER'], $repository->activeSchoolRoleCodes($this->targetId, $this->schoolId));
        self::assertSame([], $repository->activeSchoolRoleCodes(0, $this->schoolId));
    }

    public function test_role_activation_and_deactivation_are_idempotent_and_leave_other_scopes_untouched(): void
    {
        $this->service();
        $roleId = $this->roleId('VIEWER');
        $viewerId = $this->row('SELECT id FROM user_role_assignments WHERE user_id = ?', [$this->targetId])['id'];
        $yearId = $this->assignment($this->targetId, $this->schoolId, 'VIEWER', 'ACTIVE', $this->academicYearId);
        $repository = new RoleAssignmentRepository($this->pdo);
        $foreignBefore = $this->rows('SELECT * FROM user_role_assignments WHERE user_id = ?', [$this->foreignUserId]);
        $repository->deactivateSchoolRole($this->targetId, $this->schoolId, $roleId);
        $repository->deactivateSchoolRole($this->targetId, $this->schoolId, $roleId);
        self::assertSame('INACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$viewerId])['status']);
        $repository->activateSchoolRole($this->targetId, $this->schoolId, $roleId, $this->actorId);
        $repository->activateSchoolRole($this->targetId, $this->schoolId, $roleId, $this->targetId);
        $assignment = $this->row('SELECT * FROM user_role_assignments WHERE id = ?', [$viewerId]);
        self::assertSame('ACTIVE', $assignment['status']);
        self::assertSame($this->targetId, $assignment['assigned_by']);
        self::assertSame('ACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$yearId])['status']);
        self::assertCount(1, $this->rows('SELECT id FROM user_role_assignments WHERE user_id = ? AND school_id = ? AND role_id = ? AND academic_year_id IS NULL', [$this->targetId, $this->schoolId, $roleId]));
        self::assertSame($foreignBefore, $this->rows('SELECT * FROM user_role_assignments WHERE user_id = ?', [$this->foreignUserId]));
        $repository->deactivateSchoolRole($this->targetId, $this->foreignSchoolId, $roleId);
        self::assertSame('ACTIVE', $this->row('SELECT status FROM user_role_assignments WHERE id = ?', [$viewerId])['status']);
    }

    #[DataProvider('transactionFailures')]
    public function test_database_failures_are_sanitized_and_fixture_transaction_survives(string $failure): void
    {
        $before = $this->snapshot();
        if ($failure === 'begin') {
            $this->pdo->failBegin = true;
        } else {
            $this->pdo->failPrepare = 'INSERT INTO audit_logs';
            $this->pdo->abortOnFailure = true;
        }
        $this->deny(fn () => $this->create());
        self::assertSame(1, $this->pdo->depth);
        self::assertSame($before, $this->snapshot());
    }

    public static function transactionFailures(): array
    {
        return ['begin failure' => ['begin'], 'database abort' => ['abort']];
    }

    private function service(): SchoolUserAdministrationService
    {
        self::assertTrue(class_exists(SchoolUserAdministrationService::class), 'Task 6 school user service is not implemented yet.');
        return new SchoolUserAdministrationService($this->pdo, new UserRepository($this->pdo), new SchoolMembershipRepository($this->pdo),
            new RoleRepository($this->pdo), new RoleAssignmentRepository($this->pdo), new AuditLogRepository($this->pdo), new AuthorizationRepository($this->pdo));
    }

    private function create(array $overrides = []): int
    {
        return $this->service()->createUser(...array_replace(['schoolId' => $this->schoolId, 'actorUserId' => $this->actorId,
            'username' => 'school-user-new', 'displayName' => 'ผู้ใช้ใหม่', 'email' => 'new-user@example.test',
            'password' => self::PASSWORD, 'roleCodes' => ['VIEWER'], 'ipAddress' => '192.0.2.6'], $overrides));
    }

    private function operate(string $operation, int $userId): void
    {
        $service = $this->service();
        match ($operation) {
            'profile' => $service->updateProfile($this->schoolId, $this->actorId, $userId, 'Updated', 'updated@example.test', '192.0.2.6'),
            'membership' => $service->changeMembershipStatus($this->schoolId, $this->actorId, $userId, 'SUSPENDED', '192.0.2.6'),
            'roles' => $service->replaceRoles($this->schoolId, $this->actorId, $userId, ['HOMEROOM_TEACHER'], '192.0.2.6'),
            'password' => $service->resetPassword($this->schoolId, $this->actorId, $userId, self::PASSWORD, '192.0.2.6'),
        };
    }

    private function deny(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected a friendly DomainException.');
        } catch (DomainException $exception) {
            self::assertNotSame('', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            foreach (['SQLSTATE', 'PDOException', 'private-db-details', self::PASSWORD, self::ORIGINAL_PASSWORD, 'password_hash'] as $secret) {
                self::assertStringNotContainsString($secret, (string) $exception);
            }
        } catch (PDOException) {
            self::fail('Raw PDOException must not escape the service boundary.');
        }
    }

    private function assertAudit(array $audit, int $entityId, string $entityType): void
    {
        self::assertSame($this->schoolId, $audit['school_id']);
        self::assertSame($this->actorId, $audit['user_id']);
        self::assertSame($entityId, $audit['entity_id']);
        self::assertSame($entityType, $audit['entity_type']);
        self::assertSame('192.0.2.6', $audit['ip_address']);
        self::assertNull($audit['reason']);
        self::assertNotEmpty($audit['created_at']);
    }

    private function assertNoSecrets(array $audit, ?string $hash = null): void
    {
        foreach ($audit as $value) {
            if (!is_string($value)) {
                continue;
            }
            foreach ([self::PASSWORD, self::ORIGINAL_PASSWORD, self::$originalHash, $hash, 'password_hash'] as $secret) {
                if ($secret !== null) {
                    self::assertStringNotContainsString($secret, $value);
                }
            }
            self::assertDoesNotMatchRegularExpression('/\$(?:2[aby]\$|argon2)/', $value);
        }
    }

    private function assertForeignUnchanged(array $before): void
    {
        $after = $this->snapshot();
        foreach (['schools', 'users', 'school_memberships', 'user_role_assignments'] as $table) {
            $filter = fn (array $row): bool => match ($table) {
                'schools' => $row['id'] === $this->foreignSchoolId,
                'users' => $row['id'] === $this->foreignUserId,
                default => $row['school_id'] === $this->foreignSchoolId,
            };
            self::assertSame(array_values(array_filter($before[$table], $filter)), array_values(array_filter($after[$table], $filter)));
        }
    }

    private function fixtureUser(string $username, int $schoolId, string $role): int
    {
        $id = $this->insert('INSERT INTO users (username, display_name, email, password_hash) VALUES (?, ?, ?, ?)', [$username, $username, $username . '@example.test', self::$originalHash]);
        $this->insert('INSERT INTO school_memberships (user_id, school_id) VALUES (?, ?)', [$id, $schoolId]);
        $this->assignment($id, $schoolId, $role);
        return $id;
    }

    private function assignment(int $userId, ?int $schoolId, string $role, string $status = 'ACTIVE', ?int $year = null): int
    {
        return $this->insert('INSERT INTO user_role_assignments (user_id, school_id, role_id, status, academic_year_id) VALUES (?, ?, ?, ?, ?)', [$userId, $schoolId, $this->roleId($role), $status, $year]);
    }

    private function roleId(string $code): int
    {
        return $this->row('SELECT id FROM roles WHERE code = ?', [$code])['id'];
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['schools', 'users', 'school_memberships', 'user_role_assignments', 'audit_logs', 'roles', 'permissions', 'role_permissions'] as $table) {
            $snapshot[$table] = $this->rows('SELECT * FROM ' . $table . ($table === 'role_permissions' ? ' ORDER BY role_id, permission_id' : ' ORDER BY id'));
        }
        return $snapshot;
    }

    private function rows(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    private function row(string $sql, array $parameters = []): array
    {
        $rows = $this->rows($sql, $parameters);
        self::assertCount(1, $rows);
        return $rows[0];
    }

    private function insert(string $sql, array $parameters): int
    {
        $this->pdo->prepare($sql)->execute($parameters);
        return (int) $this->pdo->lastInsertId();
    }
}

/** Use real MySQL savepoints so every service commit remains inside the fixture rollback. */
final class SchoolUserTestPDO extends PDO
{
    public int $depth = 0;
    public int $writeAttempts = 0;
    public ?string $failPrepare = null;
    public int $skipMatches = 0;
    public bool $failBegin = false;
    public bool $abortOnFailure = false;
    public bool $serviceAborted = false;

    public function beginTransaction(): bool
    {
        if ($this->failBegin) {
            $this->failBegin = false;
            throw new PDOException('SQLSTATE private-db-details');
        }
        $result = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT school_user_' . $this->depth) !== false;
        ++$this->depth;
        return $result;
    }

    public function commit(): bool
    {
        $result = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT school_user_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $result;
    }

    public function rollBack(): bool
    {
        if ($this->serviceAborted) {
            throw new PDOException('There is no active transaction');
        }
        if ($this->depth === 1) {
            $result = parent::rollBack();
        } else {
            $result = $this->exec('ROLLBACK TO SAVEPOINT school_user_' . ($this->depth - 1)) !== false;
            $this->exec('RELEASE SAVEPOINT school_user_' . ($this->depth - 1));
        }
        --$this->depth;
        return $result;
    }

    public function inTransaction(): bool
    {
        return !$this->serviceAborted && parent::inTransaction();
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $query)) {
            ++$this->writeAttempts;
        }
        if ($this->failPrepare !== null && str_contains($query, $this->failPrepare) && $this->skipMatches-- === 0) {
            $this->failPrepare = null;
            if ($this->abortOnFailure) {
                $this->rollBack();
                $this->serviceAborted = true;
            }
            throw new PDOException('SQLSTATE private-db-details');
        }
        return parent::prepare($query, $options);
    }
}
