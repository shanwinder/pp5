<?php
declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\SubjectRepository;
use App\Services\SubjectAdministrationService;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SubjectAdministrationTest extends TestCase
{
    private SubjectDomainPDO $pdo;
    private int $school;
    private int $foreignSchool;
    private int $actor;

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new SubjectDomainPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->school = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['subject-domain-a', 'School A']);
        $this->foreignSchool = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['subject-domain-b', 'School B']);
        $this->actor = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['subject-domain-actor', 'unused', 'Actor']);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->failPrepare = null;
            while ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
        }
    }

    public function test_repository_lists_both_statuses_in_code_order_and_scopes_all_targets(): void
    {
        $z = $this->fixture($this->school, 'Z');
        $a = $this->fixture($this->school, 'A', 'INACTIVE');
        $foreign = $this->fixture($this->foreignSchool, 'FOREIGN');
        $repo = $this->repo();
        self::assertSame([$a, $z], array_column($repo->listForSchool($this->school), 'id'));
        self::assertSame([], $repo->listForSchool(0));
        self::assertSame($a, $repo->findForSchool($this->school, $a)['id']);
        self::assertSame('INACTIVE', $repo->findForSchool($this->school, $a)['status']);
        self::assertNull($repo->findForSchool($this->school, $foreign));
        self::assertNull($repo->findForSchool($this->school, 0));
        self::assertNull($repo->lockForSchool($this->school, $foreign));
        $before = $this->snapshot();
        $repo->update($this->school, $foreign, 'ATTACK', 'Attack');
        $repo->updateStatus($this->school, $foreign, 'INACTIVE');
        self::assertSame($before, $this->snapshot());
    }

    public function test_create_without_any_academic_year_defaults_active_with_exact_audit(): void
    {
        self::assertSame([], $this->rows('SELECT id FROM academic_years WHERE school_id = ?', [$this->school]));
        $id = $this->create(['code' => "\u{00A0}ค11101\u{2003}", 'nameTh' => "\u{3000}ภาษาไทย\u{00A0}"]);
        $row = $this->subject($id);
        self::assertSame([$this->school, 'ค11101', 'ภาษาไทย', 'ACTIVE'], [$row['school_id'], $row['code'], $row['name_th'], $row['status']]);
        $this->assertAudit($id, 'SUBJECT_CREATED', null, ['code' => 'ค11101', 'name_th' => 'ภาษาไทย', 'status' => 'ACTIVE']);
        self::assertArrayNotHasKey('academic_year_id', $row);
    }

    #[DataProvider('printable')]
    public function test_printable_unicode_codes_preserve_case_and_accept_limits(string $code, string $name): void
    {
        $id = $this->create(['code' => $code, 'nameTh' => $name]);
        self::assertSame($code, $this->subject($id)['code']);
        self::assertSame($name, $this->subject($id)['name_th']);
    }

    public static function printable(): array
    {
        return [['ค11101', 'ภาษาไทย'], ['ว14101', 'วิทยาศาสตร์'], ['SCI-P4', 'Science'], ['MATH.4', 'Math'],
            ['ภาษาไทย ป.4', 'ชื่อ'], ['วิทยาศาสตร์/เทคโนโลยี', 'ชื่อ'], ['English P4', 'English'], ['ศิลปะ-ดนตรี', 'ศิลปะ'],
            ['eNgLiSh-é😀', '<b>"Name"</b>'], [str_repeat('ก', 50), str_repeat('ข', 190)]];
    }

    #[DataProvider('invalidText')]
    public function test_invalid_utf8_controls_blanks_and_overlong_text_rejected_on_create_update(string $field, string $value): void
    {
        $id = $this->fixture($this->school);
        $service = $this->service();
        $before = $this->snapshot();
        $this->deny(fn () => $this->create([$field => $value]));
        $this->deny(fn () => $service->updateSubject($this->school, $this->actor, $id, $field === 'code' ? $value : 'SCI', $field === 'nameTh' ? $value : 'Science'));
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidText(): array
    {
        $cases = [];
        foreach (['code' => 50, 'nameTh' => 190] as $field => $max) {
            foreach (['', '   ', "\u{00A0}\u{2003}\u{3000}", str_repeat('ก', $max + 1), "\xFF", "\xC3\x28", "bad\0", "\tbad", "bad\n", "bad\r", "bad\x1F", "bad\x7F", "bad\u{0085}", "bad\u{009F}"] as $i => $value) {
                $cases[$field . '-' . $i] = [$field, $value];
            }
        }
        return $cases;
    }

    public function test_duplicate_is_per_school_for_create_update_and_safe(): void
    {
        $this->create();
        $foreign = $this->create(['schoolId' => $this->foreignSchool]);
        self::assertSame($this->foreignSchool, $this->subject($foreign)['school_id']);
        $other = $this->fixture($this->school, 'OTHER');
        $before = $this->snapshot();
        self::assertSame('รหัสรายวิชานี้มีอยู่แล้วในโรงเรียน', $this->deny(fn () => $this->create()));
        self::assertSame('รหัสรายวิชานี้มีอยู่แล้วในโรงเรียน', $this->deny(fn () => $this->service()->updateSubject($this->school, $this->actor, $other, 'SCI', 'New')));
        self::assertSame($before, $this->snapshot());
        $this->fixture($this->foreignSchool, 'NEW');
        $this->service()->updateSubject($this->school, $this->actor, $other, 'NEW', 'New');
        self::assertSame('NEW', $this->subject($other)['code']);
    }

    #[DataProvider('updates')]
    public function test_update_changes_only_business_fields_with_exact_audit(string $code, string $name, array $old, array $new): void
    {
        $id = $this->fixture($this->school);
        $this->service()->updateSubject($this->school, $this->actor, $id, $code, $name, '192.0.2.50');
        $row = $this->subject($id);
        self::assertSame($this->school, $row['school_id']);
        self::assertSame($code, $row['code']);
        self::assertSame($name, $row['name_th']);
        self::assertSame('ACTIVE', $row['status']);
        $this->assertAudit($id, 'SUBJECT_UPDATED', $old, $new);
    }

    public static function updates(): array
    {
        return [['MATH', 'Science', ['code' => 'SCI'], ['code' => 'MATH']],
            ['SCI', 'วิทยาศาสตร์', ['name_th' => 'Science'], ['name_th' => 'วิทยาศาสตร์']],
            ['ว14101', 'วิทยาศาสตร์', ['code' => 'SCI', 'name_th' => 'Science'], ['code' => 'ว14101', 'name_th' => 'วิทยาศาสตร์']]];
    }

    #[DataProvider('statuses')]
    public function test_normalized_update_noop_has_zero_writes_audits_even_when_inactive(string $status): void
    {
        $id = $this->fixture($this->school, 'SCI', $status);
        $service = $this->service();
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $service->updateSubject($this->school, $this->actor, $id, "\u{00A0}SCI\u{2003}", "\u{3000}Science ");
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('statuses')]
    public function test_status_toggle_exact_audit_and_same_state_noop(string $from): void
    {
        $id = $this->fixture($this->school, 'SCI', $from);
        $to = $from === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $service = $this->service();
        $service->changeStatus($this->school, $this->actor, $id, $to, '192.0.2.50');
        self::assertSame($to, $this->subject($id)['status']);
        $this->assertAudit($id, 'SUBJECT_STATUS_CHANGED', ['status' => $from], ['status' => $to]);
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $service->changeStatus($this->school, $this->actor, $id, $to);
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
        self::assertCount(1, $this->repo()->listForSchool($this->school));
    }

    public static function statuses(): array { return [['ACTIVE'], ['INACTIVE']]; }

    #[DataProvider('badStatuses')]
    public function test_invalid_statuses_are_safe_and_leave_no_audit(string $status): void
    {
        $id = $this->fixture($this->school);
        $service = $this->service();
        $before = $this->snapshot();
        $this->deny(fn () => $service->changeStatus($this->school, $this->actor, $id, $status));
        self::assertSame($before, $this->snapshot());
    }

    public static function badStatuses(): array { return [['UNKNOWN'], ['CLOSED'], ['DRAFT'], ['SUSPENDED'], ['active'], ['inactive'], [''], [' ACTIVE ']]; }

    public function test_foreign_missing_update_status_fail_identically_without_tenant_disclosure(): void
    {
        $foreign = $this->fixture($this->foreignSchool);
        $service = $this->service();
        $before = $this->snapshot();
        $messages = [];
        foreach ([$foreign, 0] as $id) {
            $messages[] = $this->deny(fn () => $service->updateSubject($this->school, $this->actor, $id, 'NEW', 'New'));
            $messages[] = $this->deny(fn () => $service->changeStatus($this->school, $this->actor, $id, 'INACTIVE'));
        }
        self::assertCount(1, array_unique($messages));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('unavailableSchools')]
    public function test_all_mutations_require_active_school_even_noop_targets(string $state): void
    {
        $id = $this->fixture($this->school);
        $school = $this->school;
        if ($state === 'MISSING') { $school = 0; }
        else { $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?')->execute([$state, $school]); }
        $service = $this->service();
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->deny(fn () => $service->createSubject($school, $this->actor, 'NEW', 'New'));
        $this->deny(fn () => $service->updateSubject($school, $this->actor, $id, 'SCI', 'Science'));
        $this->deny(fn () => $service->changeStatus($school, $this->actor, $id, 'ACTIVE'));
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }

    public static function unavailableSchools(): array { return [['INACTIVE'], ['SUSPENDED'], ['MISSING']]; }

    #[DataProvider('failureCases')]
    public function test_atomicity_on_write_audit_begin_commit_failures(string $action, string $failure): void
    {
        $id = $this->fixture($this->school);
        $service = $this->service();
        $before = $this->snapshot();
        if ($failure === 'commit') { $this->pdo->failCommit = true; }
        elseif ($failure === 'begin') { $this->pdo->failBegin = true; }
        else { $this->pdo->failPrepare = $failure === 'audit' ? 'INSERT INTO audit_logs' : ($action === 'create' ? 'INSERT INTO subjects' : 'UPDATE subjects'); }
        $this->deny(fn () => $this->operate($service, $action, $id));
        self::assertTrue($this->pdo->failureTriggered);
        self::assertSame(1, $this->pdo->depth);
        self::assertTrue($this->pdo->inTransaction());
        self::assertSame($before, $this->snapshot());
    }

    public static function failureCases(): array
    {
        $cases = [];
        foreach (['create', 'update', 'status'] as $action) {
            foreach (['write', 'audit', 'begin', 'commit'] as $failure) { $cases[] = [$action, $failure]; }
        }
        return $cases;
    }

    public function test_lock_order_school_before_subject_for_consistent_mutations_and_audit(): void
    {
        $id = $this->fixture($this->school);
        $service = $this->service();
        foreach (['create', 'update', 'status'] as $action) {
            $this->pdo->beginTransaction();
            $this->pdo->queries = [];
            $this->operate($service, $action, $id);
            $locks = array_values(array_filter($this->pdo->queries, static fn (string $sql): bool => str_contains($sql, 'FOR UPDATE')));
            self::assertStringContainsString('FROM schools', $locks[0]);
            if ($action !== 'create') {
                self::assertStringContainsString('FROM subjects', $locks[1]);
                self::assertStringContainsString('school_id = ?', $locks[1]);
            }
            $this->pdo->rollBack();
        }
    }

    private function service(): SubjectAdministrationService
    {
        self::assertTrue(class_exists(SubjectAdministrationService::class), 'SubjectAdministrationService missing');
        return new SubjectAdministrationService($this->pdo, new SchoolRepository($this->pdo), $this->repo(), new AuditLogRepository($this->pdo));
    }
    private function repo(): SubjectRepository
    {
        self::assertTrue(class_exists(SubjectRepository::class), 'SubjectRepository missing');
        return new SubjectRepository($this->pdo);
    }
    private function create(array $overrides = []): int
    {
        return $this->service()->createSubject(...array_replace(['schoolId' => $this->school, 'actorUserId' => $this->actor, 'code' => 'SCI', 'nameTh' => 'Science', 'ipAddress' => '192.0.2.50'], $overrides));
    }
    private function operate(SubjectAdministrationService $service, string $action, int $id): void
    {
        match ($action) {
            'create' => $service->createSubject($this->school, $this->actor, 'NEW', 'New'),
            'update' => $service->updateSubject($this->school, $this->actor, $id, 'NEW', 'New'),
            'status' => $service->changeStatus($this->school, $this->actor, $id, 'INACTIVE'),
        };
    }
    private function assertAudit(int $id, string $action, ?array $old, array $new): void
    {
        $rows = $this->rows('SELECT * FROM audit_logs ORDER BY id');
        self::assertCount(1, $rows);
        $row = $rows[0];
        self::assertSame([$this->school, $this->actor, 'subjects', $id, $action, '192.0.2.50', null],
            array_map(static fn (string $key): mixed => $row[$key], ['school_id', 'user_id', 'entity_type', 'entity_id', 'action', 'ip_address', 'reason']));
        self::assertSame($old, $row['old_value'] === null ? null : json_decode($row['old_value'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame($new, json_decode($row['new_value'], true, 512, JSON_THROW_ON_ERROR));
        self::assertNotEmpty($row['created_at']);
    }
    private function deny(callable $operation): string
    {
        try { $operation(); self::fail('Expected friendly DomainException'); }
        catch (DomainException $e) {
            self::assertNotSame('', $e->getMessage());
            self::assertNull($e->getPrevious());
            foreach (['SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', 'uq_subject', 'private-db-details', '/Applications/MAMP/', 'htdocs/app/'] as $secret) { self::assertStringNotContainsString($secret, $e->getMessage()); }
            return $e->getMessage();
        }
    }
    private function fixture(int $school, string $code = 'SCI', string $status = 'ACTIVE'): int
    {
        return $this->insert('INSERT INTO subjects (school_id, code, name_th, status) VALUES (?, ?, ?, ?)', [$school, $code, 'Science', $status]);
    }
    private function subject(int $id): array { return $this->rows('SELECT * FROM subjects WHERE id = ?', [$id])[0]; }
    private function snapshot(): array { return [$this->rows('SELECT * FROM subjects ORDER BY id'), $this->rows('SELECT * FROM audit_logs ORDER BY id')]; }
    private function rows(string $sql, array $params = []): array { $q = $this->pdo->prepare($sql); $q->execute($params); return $q->fetchAll(); }
    private function insert(string $sql, array $params): int { $this->pdo->prepare($sql)->execute($params); return (int) $this->pdo->lastInsertId(); }
}

/** Real savepoints keep service commits inside fixture rollback. */
final class SubjectDomainPDO extends PDO
{
    public int $depth = 0;
    public int $writeAttempts = 0;
    public bool $failBegin = false;
    public bool $failCommit = false;
    public bool $failureTriggered = false;
    public ?string $failPrepare = null;
    public array $queries = [];

    public function beginTransaction(): bool
    {
        if ($this->failBegin) { $this->failBegin = false; $this->failureTriggered = true; throw new PDOException('SQLSTATE private-db-details'); }
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT subject_domain_' . $this->depth) !== false;
        ++$this->depth;
        return $ok;
    }
    public function commit(): bool
    {
        if ($this->failCommit) { $this->failCommit = false; $this->failureTriggered = true; throw new PDOException('SQLSTATE private-db-details'); }
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT subject_domain_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else { $ok = $this->exec('ROLLBACK TO SAVEPOINT subject_domain_' . ($this->depth - 1)) !== false; $this->exec('RELEASE SAVEPOINT subject_domain_' . ($this->depth - 1)); }
        --$this->depth;
        return $ok;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $query)) { ++$this->writeAttempts; }
        if ($this->failPrepare !== null && str_contains($query, $this->failPrepare)) {
            $this->failPrepare = null; $this->failureTriggered = true;
            throw new PDOException('SQLSTATE private-db-details');
        }
        return parent::prepare($query, $options);
    }
}
