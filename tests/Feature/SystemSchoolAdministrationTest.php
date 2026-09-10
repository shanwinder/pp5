<?php
declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\RoleAssignmentRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SchoolMembershipRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\UserRepository;
use App\Services\SystemSchoolAdministrationService;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SystemSchoolAdministrationTest extends TestCase
{
    private SystemSchoolTestPDO $pdo;
    private int $actorId;
    private const PASSWORD = 'task4-secret-password';

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new SystemSchoolTestPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->actorId = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)',
            ['system-task4-actor', 'unused-fixture-hash', 'System Actor']);
        $this->insert("INSERT INTO user_role_assignments (user_id, role_id) SELECT ?, id FROM roles WHERE code = 'SYSTEM_ADMIN'", [$this->actorId]);
        $this->pdo->begins = 0;
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->serviceTransactionAborted = false;
            while ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    public function test_create_school_and_first_admin_is_atomic_with_safe_audit(): void
    {
        $before = $this->snapshot();
        $result = $this->create();
        self::assertSame(1, $this->pdo->depth);
        self::assertSame(1, $this->pdo->begins);
        self::assertIsInt($result['school_id']);
        self::assertIsInt($result['user_id']);
        self::assertIsInt($result['school_membership_id']);
        $school = $this->row('SELECT * FROM schools WHERE id = ?', [$result['school_id']]);
        $user = $this->row('SELECT * FROM users WHERE id = ?', [$result['user_id']]);
        $membership = $this->row('SELECT * FROM school_memberships WHERE id = ?', [$result['school_membership_id']]);
        $assignment = $this->row('SELECT ura.*, r.code FROM user_role_assignments ura JOIN roles r ON r.id = ura.role_id WHERE ura.user_id = ?', [$result['user_id']]);
        self::assertSame('task4-school', $school['school_code']);
        self::assertSame('โรงเรียนทดสอบ', $school['name_th']);
        self::assertSame('ACTIVE', $school['status']);
        self::assertSame('task4-admin', $user['username']);
        self::assertSame('admin@example.test', $user['email']);
        self::assertSame('ผู้ดูแลโรงเรียน', $user['display_name']);
        self::assertSame('ACTIVE', $user['status']);
        self::assertNotSame(self::PASSWORD, $user['password_hash']);
        self::assertTrue(password_verify(self::PASSWORD, $user['password_hash']));
        self::assertSame($result['user_id'], $membership['user_id']);
        self::assertSame($result['school_id'], $membership['school_id']);
        self::assertSame('ACTIVE', $membership['status']);
        self::assertSame($this->actorId, $membership['created_by']);
        self::assertSame('SCHOOL_ADMIN', $assignment['code']);
        self::assertSame('ACTIVE', $assignment['status']);
        self::assertSame($result['school_id'], $assignment['school_id']);
        self::assertNull($assignment['academic_year_id']);
        self::assertSame($this->actorId, $assignment['assigned_by']);
        foreach (['schools', 'users', 'school_memberships', 'user_role_assignments'] as $table) {
            self::assertCount(count($before[$table]) + 1, $this->snapshot()[$table]);
        }
        $audits = $this->rows('SELECT * FROM audit_logs ORDER BY id');
        self::assertCount(2, $audits);
        self::assertSame(['SCHOOL_CREATED', 'SCHOOL_ADMIN_CREATED'], array_column($audits, 'action'));
        self::assertSame(['schools', 'users'], array_column($audits, 'entity_type'));
        self::assertSame([$result['school_id'], $result['user_id']], array_column($audits, 'entity_id'));
        foreach ($audits as $audit) {
            self::assertSame($this->actorId, $audit['user_id']);
            self::assertSame($result['school_id'], $audit['school_id']);
            self::assertSame('127.0.0.1', $audit['ip_address']);
            self::assertNull($audit['reason']);
            self::assertNull($audit['old_value']);
            self::assertIsArray(json_decode($audit['new_value'], true, 512, JSON_THROW_ON_ERROR));
            self::assertNotEmpty($audit['created_at']);
            $this->assertNoSecrets($audit, $user['password_hash']);
        }
        self::assertStringContainsString('โรงเรียนทดสอบ', $audits[0]['new_value']);
        self::assertSame('task4-school', json_decode($audits[0]['new_value'], true)['school_code']);
        self::assertSame('task4-admin', json_decode($audits[1]['new_value'], true)['username']);
    }

    #[DataProvider('blankEmails')]
    public function test_blank_email_is_stored_as_null(?string $email): void
    {
        $result = $this->create(['adminEmail' => $email]);
        self::assertNull($this->row('SELECT email FROM users WHERE id = ?', [$result['user_id']])['email']);
    }

    public static function blankEmails(): array
    {
        return ['null' => [null], 'empty' => [''], 'whitespace' => [" \t\n "]];
    }

    public function test_inputs_are_trimmed_and_minimum_password_is_accepted_without_trimming(): void
    {
        $password = ' 1234567890 ';
        $result = $this->create(['schoolCode' => ' xy ', 'schoolName' => ' ก ', 'adminUsername' => ' abc ',
            'adminDisplayName' => ' ข ', 'adminEmail' => ' admin@example.test ', 'adminPassword' => $password]);
        $school = $this->row('SELECT * FROM schools WHERE id = ?', [$result['school_id']]);
        $user = $this->row('SELECT * FROM users WHERE id = ?', [$result['user_id']]);
        self::assertSame('xy', $school['school_code']);
        self::assertSame('ก', $school['name_th']);
        self::assertSame('abc', $user['username']);
        self::assertSame('ข', $user['display_name']);
        self::assertSame('admin@example.test', $user['email']);
        self::assertTrue(password_verify($password, $user['password_hash']));
    }

    public function test_maximum_identifier_and_unicode_name_lengths_are_accepted(): void
    {
        $result = $this->create(['schoolCode' => str_repeat('a', 27) . '._-', 'schoolName' => str_repeat('ก', 190),
            'adminUsername' => str_repeat('b', 97) . '._-', 'adminDisplayName' => str_repeat('ข', 190)]);
        self::assertSame(str_repeat('ก', 190), $this->row('SELECT name_th FROM schools WHERE id = ?', [$result['school_id']])['name_th']);
        self::assertSame(str_repeat('ข', 190), $this->row('SELECT display_name FROM users WHERE id = ?', [$result['user_id']])['display_name']);
    }

    #[DataProvider('invalidInputs')]
    public function test_validation_rejects_before_transaction_or_write(string $field, string $value): void
    {
        $before = $this->snapshot();
        $this->expectFriendlyFailure(fn () => $this->create([$field => $value]));
        self::assertSame(0, $this->pdo->begins);
        self::assertSame($before, $this->snapshot());
        self::assertSame(1, $this->pdo->depth);
    }

    public static function invalidInputs(): array
    {
        return [
            'blank code' => ['schoolCode', '  '], 'short code' => ['schoolCode', 'a'],
            'long code' => ['schoolCode', str_repeat('a', 31)], 'invalid code' => ['schoolCode', 'school/code'],
            'blank school name' => ['schoolName', '  '], 'long school name' => ['schoolName', str_repeat('ก', 191)],
            'blank username' => ['adminUsername', '  '], 'short username' => ['adminUsername', 'ab'],
            'long username' => ['adminUsername', str_repeat('a', 101)], 'invalid username' => ['adminUsername', 'admin user'],
            'blank display name' => ['adminDisplayName', '  '], 'long display name' => ['adminDisplayName', str_repeat('ข', 191)],
            'invalid email' => ['adminEmail', 'not-an-email'], 'short password' => ['adminPassword', '12345678901'],
        ];
    }

    #[DataProvider('duplicateFields')]
    public function test_duplicate_keys_are_friendly_and_leave_no_partial_rows(string $field): void
    {
        if ($field === 'schoolCode') {
            $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['task4-school', 'Existing school']);
        } else {
            $this->insert('INSERT INTO users (username, email, password_hash, display_name) VALUES (?, ?, ?, ?)',
                [$field === 'adminUsername' ? 'task4-admin' : 'task4-existing', $field === 'adminEmail' ? 'admin@example.test' : null, 'unused-fixture-hash', 'Existing User']);
        }
        $before = $this->snapshot();
        $this->expectFriendlyFailure(fn () => $this->create());
        self::assertSame($before, $this->snapshot());
        self::assertSame(1, $this->pdo->depth);
    }

    public static function duplicateFields(): array
    {
        return ['school code' => ['schoolCode'], 'username' => ['adminUsername'], 'email' => ['adminEmail']];
    }

    #[DataProvider('duplicateFields')]
    public function test_repositories_translate_duplicate_keys_without_a_service_wrapper(string $field): void
    {
        $this->create();
        $before = $this->snapshot();
        $this->expectFriendlyFailure(function () use ($field): void {
            if ($field === 'schoolCode') {
                (new SchoolRepository($this->pdo))->create('task4-school', 'Duplicate');
            } else {
                (new UserRepository($this->pdo))->create(
                    $field === 'adminUsername' ? 'task4-admin' : 'task4-other',
                    $field === 'adminEmail' ? 'admin@example.test' : null,
                    'unused-fixture-hash',
                    'Duplicate'
                );
            }
        });
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('transactionOperations')]
    public function test_transaction_start_failure_is_friendly_and_preserves_existing_fixture_transaction(string $operation): void
    {
        $service = $this->service();
        $before = $this->snapshot();
        $this->pdo->failBegin = true;
        $this->expectFriendlyFailure(fn () => $operation === 'create'
            ? $this->create()
            : $service->changeSchoolStatus(0, 'ACTIVE', $this->actorId));
        self::assertSame($before, $this->snapshot());
        self::assertSame(1, $this->pdo->depth);
    }

    public static function transactionOperations(): array
    {
        return ['create school' => ['create'], 'change status' => ['status']];
    }

    #[DataProvider('transactionOperations')]
    public function test_database_aborted_transaction_does_not_trigger_a_second_rollback(string $operation): void
    {
        $schoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['task4-status', 'Status school']);
        $before = $this->snapshot();
        $this->pdo->failPrepare = 'INSERT INTO audit_logs';
        $this->pdo->abortOnFailure = true;
        $this->expectFriendlyFailure(fn () => $operation === 'create'
            ? $this->create()
            : $this->service()->changeSchoolStatus($schoolId, 'SUSPENDED', $this->actorId));
        self::assertSame(1, $this->pdo->depth);
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('unavailableAdminRoles')]
    public function test_missing_active_school_admin_role_rolls_back_after_membership_creation(string $change): void
    {
        $this->pdo->prepare($change)->execute();
        $before = $this->snapshot();
        $this->expectFriendlyFailure(fn () => $this->create());
        self::assertSame($before, $this->snapshot());
        self::assertSame(1, $this->pdo->depth);
    }

    public static function unavailableAdminRoles(): array
    {
        return [
            'inactive' => ["UPDATE roles SET status = 'INACTIVE' WHERE code = 'SCHOOL_ADMIN'"],
            'missing' => ["UPDATE roles SET code = 'TASK4_RENAMED_ADMIN' WHERE code = 'SCHOOL_ADMIN'"],
            'wrong scope' => ["UPDATE roles SET scope_type = 'SYSTEM' WHERE code = 'SCHOOL_ADMIN'"],
        ];
    }

    #[DataProvider('writeFailures')]
    public function test_write_failure_rolls_back_every_row_and_does_not_leak_exception_secrets(string $table, int $skip): void
    {
        $before = $this->snapshot();
        $this->pdo->failPrepare = 'INSERT INTO ' . $table;
        $this->pdo->skipMatches = $skip;
        $this->expectFriendlyFailure(fn () => $this->create());
        self::assertSame($before, $this->snapshot());
        self::assertSame(1, $this->pdo->depth);
    }

    public static function writeFailures(): array
    {
        return [
            'membership write' => ['school_memberships', 0],
            'assignment write' => ['user_role_assignments', 0],
            'second audit after first audit written' => ['audit_logs', 1],
        ];
    }

    #[DataProvider('statusTransitions')]
    public function test_school_status_transitions_and_audit(string $oldStatus, string $newStatus): void
    {
        $schoolId = $this->insert('INSERT INTO schools (school_code, name_th, status) VALUES (?, ?, ?)', ['task4-status', 'Status school', $oldStatus]);
        $this->service()->changeSchoolStatus($schoolId, $newStatus, $this->actorId, '192.0.2.10');
        self::assertSame($newStatus, $this->row('SELECT status FROM schools WHERE id = ?', [$schoolId])['status']);
        self::assertSame(1, $this->pdo->depth);
        $audits = $this->rows('SELECT * FROM audit_logs ORDER BY id');
        self::assertCount(1, $audits);
        $audit = $audits[0];
        self::assertSame('SCHOOL_STATUS_CHANGED', $audit['action']);
        self::assertSame('schools', $audit['entity_type']);
        self::assertSame($schoolId, $audit['entity_id']);
        self::assertSame($schoolId, $audit['school_id']);
        self::assertSame($this->actorId, $audit['user_id']);
        self::assertSame('192.0.2.10', $audit['ip_address']);
        self::assertSame(['status' => $oldStatus], json_decode($audit['old_value'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(['status' => $newStatus], json_decode($audit['new_value'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertNoSecrets($audit);
    }

    public static function statusTransitions(): array
    {
        return [
            'suspend' => ['ACTIVE', 'SUSPENDED'], 'reactivate' => ['SUSPENDED', 'ACTIVE'],
            'deactivate' => ['ACTIVE', 'INACTIVE'], 'reactivate inactive' => ['INACTIVE', 'ACTIVE'],
            'suspended to inactive' => ['SUSPENDED', 'INACTIVE'], 'inactive to suspended' => ['INACTIVE', 'SUSPENDED'],
        ];
    }

    #[DataProvider('schoolStatuses')]
    public function test_repeating_school_status_is_a_no_op_without_audit(string $status): void
    {
        $schoolId = $this->insert('INSERT INTO schools (school_code, name_th, status) VALUES (?, ?, ?)', ['task4-status', 'Status school', $status]);
        $before = $this->snapshot();
        $this->service()->changeSchoolStatus($schoolId, $status, $this->actorId);
        self::assertSame($before, $this->snapshot());
        self::assertSame(1, $this->pdo->depth);
    }

    public static function schoolStatuses(): array
    {
        return ['active' => ['ACTIVE'], 'suspended' => ['SUSPENDED'], 'inactive' => ['INACTIVE']];
    }

    public function test_missing_school_is_rejected_without_mutation_or_audit(): void
    {
        $before = $this->snapshot();
        $this->expectFriendlyFailure(fn () => $this->service()->changeSchoolStatus(0, 'SUSPENDED', $this->actorId));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('invalidStatuses')]
    public function test_invalid_status_is_rejected_without_mutation_or_audit(string $status): void
    {
        $schoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['task4-status', 'Status school']);
        $before = $this->snapshot();
        $this->expectFriendlyFailure(fn () => $this->service()->changeSchoolStatus($schoolId, $status, $this->actorId));
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidStatuses(): array
    {
        return ['unknown' => ['DELETED'], 'lowercase' => ['active'], 'blank' => [''], 'whitespace' => [' ACTIVE ']];
    }

    public function test_status_update_rolls_back_if_audit_fails(): void
    {
        $schoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['task4-status', 'Status school']);
        $before = $this->snapshot();
        $this->pdo->failPrepare = 'INSERT INTO audit_logs';
        $this->expectFriendlyFailure(fn () => $this->service()->changeSchoolStatus($schoolId, 'SUSPENDED', $this->actorId));
        self::assertSame($before, $this->snapshot());
        self::assertSame(1, $this->pdo->depth);
    }

    public function test_repositories_resolve_inactive_identity_and_return_null_for_missing_rows(): void
    {
        $service = $this->service();
        $result = $this->create();
        $schools = new SchoolRepository($this->pdo);
        self::assertSame($schools->findById($result['school_id']), $schools->findByCode('task4-school'));
        foreach (['SUSPENDED', 'INACTIVE'] as $status) {
            $service->changeSchoolStatus($result['school_id'], $status, $this->actorId);
            self::assertSame(['id' => $result['school_id'], 'school_code' => 'task4-school', 'name_th' => 'โรงเรียนทดสอบ', 'status' => $status], $schools->findById($result['school_id']));
            self::assertSame([$schools->findById($result['school_id'])], $schools->all());
        }
        self::assertNull($schools->findById(0));
        self::assertNull($schools->findByCode('missing'));
        $this->pdo->prepare("UPDATE users SET status = 'INACTIVE' WHERE id = ?")->execute([$result['user_id']]);
        $users = new UserRepository($this->pdo);
        self::assertSame($result['user_id'], $users->findByUsername('task4-admin')['id']);
        self::assertSame($result['user_id'], $users->findByEmail('admin@example.test')['id']);
        self::assertNull($users->findByUsername('missing'));
        self::assertNull($users->findByEmail('missing@example.test'));
        $roles = new RoleRepository($this->pdo);
        self::assertSame('SCHOOL', $roles->findActiveByCode('SCHOOL_ADMIN')['scope_type']);
        self::assertNull($roles->findActiveByCode('missing'));
        $this->pdo->prepare("UPDATE roles SET status = 'INACTIVE' WHERE code = 'SCHOOL_ADMIN'")->execute();
        self::assertNull($roles->findActiveByCode('SCHOOL_ADMIN'));
    }

    public function test_system_role_assignment_has_null_school_and_year_with_optional_actor(): void
    {
        $this->service();
        $roleId = $this->row("SELECT id FROM roles WHERE code = 'SYSTEM_ADMIN'")['id'];
        $repository = new RoleAssignmentRepository($this->pdo);
        foreach ([null, $this->actorId] as $actor) {
            $id = $actor === null
                ? $repository->assignSystemRole($this->actorId, $roleId)
                : $repository->assignSystemRole($this->actorId, $roleId, $actor);
            self::assertIsInt($id);
            $assignment = $this->row('SELECT * FROM user_role_assignments WHERE id = ?', [$id]);
            self::assertNull($assignment['school_id']);
            self::assertNull($assignment['academic_year_id']);
            self::assertSame('ACTIVE', $assignment['status']);
            self::assertSame($this->actorId, $assignment['user_id']);
            self::assertSame($roleId, $assignment['role_id']);
            self::assertSame($actor, $assignment['assigned_by']);
        }
    }

    public function test_audit_repository_preserves_nullable_fields_and_unicode_json(): void
    {
        $this->service();
        $repository = new AuditLogRepository($this->pdo);
        $repository->record(null, null, 'SCHOOL_CREATED', 'schools', null, ['ชื่อ' => 'เดิม'], ['ชื่อ' => 'ใหม่'], null, null);
        $audit = $this->row('SELECT * FROM audit_logs');
        self::assertNull($audit['school_id']);
        self::assertNull($audit['user_id']);
        self::assertNull($audit['entity_id']);
        self::assertNull($audit['reason']);
        self::assertNull($audit['ip_address']);
        self::assertSame('{"ชื่อ":"เดิม"}', $audit['old_value']);
        self::assertSame('{"ชื่อ":"ใหม่"}', $audit['new_value']);
    }

    public function test_invalid_audit_json_throws_without_inserting_a_row(): void
    {
        $this->service();
        $before = $this->snapshot();
        try {
            (new AuditLogRepository($this->pdo))->record(null, null, 'SCHOOL_CREATED', 'schools', null, null, ['invalid' => "\xB1\x31"], null, null);
            self::fail('Invalid UTF-8 must not silently become an empty audit payload.');
        } catch (JsonException) {
            self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('secretBoundaries')]
    public function test_exception_traces_redact_password_and_hash_when_php_includes_arguments(string $boundary): void
    {
        $service = $this->service();
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            if ($boundary === 'service') {
                $service->createSchoolWithAdmin('task4-school', 'School', 'task4-admin', 'Admin', null, 'shortsecret', $this->actorId);
            } else {
                (new UserRepository($this->pdo))->create('system-task4-actor', null, 'secret-hash', 'Duplicate');
            }
            self::fail('Expected invalid input or duplicate username to be rejected.');
        } catch (DomainException $exception) {
            self::assertStringNotContainsString('shortsecret', (string) $exception);
            self::assertStringNotContainsString('secret-hash', (string) $exception);
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }

    public static function secretBoundaries(): array
    {
        return ['plaintext password' => ['service'], 'password hash' => ['repository']];
    }

    private function create(array $overrides = []): array
    {
        return $this->service()->createSchoolWithAdmin(...array_replace([
            'schoolCode' => 'task4-school', 'schoolName' => 'โรงเรียนทดสอบ',
            'adminUsername' => 'task4-admin', 'adminDisplayName' => 'ผู้ดูแลโรงเรียน',
            'adminEmail' => 'admin@example.test', 'adminPassword' => self::PASSWORD,
            'actorUserId' => $this->actorId, 'ipAddress' => '127.0.0.1',
        ], $overrides));
    }

    private function service(): SystemSchoolAdministrationService
    {
        self::assertTrue(class_exists(SystemSchoolAdministrationService::class), 'Task 4 domain service is not implemented yet.');
        return new SystemSchoolAdministrationService($this->pdo, new SchoolRepository($this->pdo), new UserRepository($this->pdo),
            new SchoolMembershipRepository($this->pdo), new RoleRepository($this->pdo),
            new RoleAssignmentRepository($this->pdo), new AuditLogRepository($this->pdo));
    }

    private function expectFriendlyFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected a friendly DomainException.');
        } catch (DomainException $exception) {
            self::assertNotSame('', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', (string) $exception);
            self::assertStringNotContainsString('PDOException', (string) $exception);
            self::assertStringNotContainsString(self::PASSWORD, (string) $exception);
            self::assertStringNotContainsString('private-database-details', (string) $exception);
            self::assertNull($exception->getPrevious());
        } catch (PDOException) {
            self::fail('Raw PDOException must not escape to the caller.');
        }
    }

    private function assertNoSecrets(array $audit, string $hash = 'unused-fixture-hash'): void
    {
        foreach ($audit as $value) {
            if (is_string($value)) {
                self::assertStringNotContainsString(self::PASSWORD, $value);
                self::assertStringNotContainsString($hash, $value);
                self::assertStringNotContainsString('password', strtolower($value));
                self::assertDoesNotMatchRegularExpression('/\$(?:2[aby]\$|argon2)/', $value);
            }
        }
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

/** Keep a real MySQL fixture transaction outside each service transaction. */
final class SystemSchoolTestPDO extends PDO
{
    public int $depth = 0;
    public int $begins = 0;
    public bool $failBegin = false;
    public bool $abortOnFailure = false;
    public bool $serviceTransactionAborted = false;
    public ?string $failPrepare = null;
    public int $skipMatches = 0;

    public function beginTransaction(): bool
    {
        if ($this->failBegin) {
            $this->failBegin = false;
            throw new PDOException('SQLSTATE private-database-details');
        }
        ++$this->begins;
        if ($this->depth === 0) {
            $result = parent::beginTransaction();
        } else {
            $result = $this->exec('SAVEPOINT task4_' . $this->depth) !== false;
        }
        ++$this->depth;
        return $result;
    }

    public function commit(): bool
    {
        if ($this->depth === 1) {
            $result = parent::commit();
        } else {
            $result = $this->exec('RELEASE SAVEPOINT task4_' . ($this->depth - 1)) !== false;
        }
        --$this->depth;
        return $result;
    }

    public function rollBack(): bool
    {
        if ($this->serviceTransactionAborted) {
            throw new PDOException('There is no active transaction');
        }
        if ($this->depth === 1) {
            $result = parent::rollBack();
        } else {
            $result = $this->exec('ROLLBACK TO SAVEPOINT task4_' . ($this->depth - 1)) !== false;
            $this->exec('RELEASE SAVEPOINT task4_' . ($this->depth - 1));
        }
        --$this->depth;
        return $result;
    }

    public function inTransaction(): bool
    {
        // Simulate MySQL aborting the service transaction while retaining fixture isolation.
        return !$this->serviceTransactionAborted && parent::inTransaction();
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failPrepare !== null && str_contains($query, $this->failPrepare) && $this->skipMatches-- === 0) {
            $this->failPrepare = null;
            if ($this->abortOnFailure) {
                $this->rollBack();
                $this->serviceTransactionAborted = true;
            }
            throw new PDOException('SQLSTATE private-database-details task4-secret-password');
        }
        return parent::prepare($query, $options);
    }
}
