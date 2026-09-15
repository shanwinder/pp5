<?php
declare(strict_types=1);

use App\Repositories\AuditLogRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\StudentRepository;
use App\Repositories\StudentEnrollmentRepository;
use App\Services\StudentAdministrationService;
use App\Validation\StudentProfileRules;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StudentAdministrationTest extends TestCase
{
    private StudentDomainPDO $pdo;
    private int $school;
    private int $foreignSchool;
    private int $actor;
    private const NATIONAL = '0000000000001';
    private const IP = '192.0.2.42';

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new StudentDomainPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->school = $this->insert('schools', ['school_code' => 'student-domain-a', 'name_th' => 'School A']);
        $this->foreignSchool = $this->insert('schools', ['school_code' => 'student-domain-b', 'name_th' => 'School B']);
        $this->actor = $this->insert('users', ['username' => 'student-domain-actor', 'password_hash' => 'unused', 'display_name' => 'Actor']);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->failPrepare = null; $this->pdo->failBegin = false; $this->pdo->failCommit = false;
            while ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
        }
    }

    public function test_create_normalizes_unicode_without_year_and_records_only_safe_metadata(): void
    {
        $id = $this->create(['studentCode' => "\u{00A0}ท001\u{3000}", 'firstNameTh' => "\u{2003}ทดสอบ\u{00A0}"]);
        $r = $this->student($id);
        self::assertSame([$this->school, 'ท001', 'ทดสอบ', self::NATIONAL, 'ACTIVE'], [$r['school_id'], $r['student_code'], $r['first_name_th'], $r['national_id'], $r['status']]);
        self::assertArrayNotHasKey('academic_year_id', $r);
        $this->audit($id, 'STUDENT_CREATED', null, ['status' => 'ACTIVE', 'has_national_id' => true]);
    }

    #[DataProvider('validProfiles')]
    public function test_normalizer_accepts_boundaries_and_nullable_fields(array $changes, array $expected): void
    {
        $normal = $this->normalize($changes);
        foreach ($expected as $field => $value) { self::assertSame($value, $normal[$field]); }
    }

    public static function validProfiles(): array
    {
        $cases = [];
        foreach (['studentCode' => ['student_code', 50], 'prefixTh' => ['prefix_th', 50], 'firstNameTh' => ['first_name_th', 100], 'lastNameTh' => ['last_name_th', 100]] as $input => [$field, $max]) {
            foreach ([1, $max] as $length) { $cases[] = [[$input => str_repeat('ก', $length)], [$field => str_repeat('ก', $length)]]; }
        }
        foreach ([null, '', "\u{00A0}\u{3000} "] as $blank) {
            $cases[] = [['nationalId' => $blank, 'genderCode' => $blank, 'birthDate' => $blank], ['national_id' => null, 'gender_code' => null, 'birth_date' => null]];
        }
        foreach (['MALE', 'FEMALE', 'OTHER'] as $gender) { $cases[] = [['genderCode' => ' ' . $gender . ' '], ['gender_code' => $gender]]; }
        $cases[] = [['nationalId' => ' 0000000000000 '], ['national_id' => '0000000000000']]; // No checksum policy.
        $cases[] = [['birthDate' => '2024-02-29'], ['birth_date' => '2024-02-29']];
        $cases[] = [['birthDate' => date('Y-m-d')], ['birth_date' => date('Y-m-d')]];
        return $cases;
    }

    #[DataProvider('invalidProfiles')]
    public function test_invalid_profile_is_rejected_by_normalizer_and_both_mutations_without_writes(array $changes): void
    {
        $id = $this->fixture();
        $service = $this->service();
        $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        $this->deny(fn () => $this->normalize($changes));
        $this->deny(fn () => $this->create($changes));
        $this->deny(fn () => $this->update($id, $changes));
        self::assertSame(0, $this->pdo->writeAttempts); self::assertSame($before, $this->snapshot());
    }

    public static function invalidProfiles(): array
    {
        $cases = [];
        foreach (['studentCode' => 50, 'prefixTh' => 50, 'firstNameTh' => 100, 'lastNameTh' => 100] as $field => $max) {
            foreach (['', " \u{3000}", str_repeat('ก', $max + 1)] as $value) { $cases[] = [[$field => $value]]; }
        }
        foreach (['studentCode', 'nationalId', 'prefixTh', 'firstNameTh', 'lastNameTh', 'genderCode', 'birthDate'] as $field) {
            foreach (["\xC3\x28", "a\0b", "a\nb", "\t", "a\u{0085}b"] as $value) { $cases[] = [[$field => $value]]; }
        }
        foreach (['123', '000000000000', '00000000000000', '๑๒๓๔๕๖๗๘๙๐๑๒๓', '１２３４５６７８９０１２３', '000000000000A', '+000000000001', '000000 000001'] as $value) { $cases[] = [['nationalId' => $value]]; }
        foreach (['male', 'UNKNOWN', '0', 'F'] as $value) { $cases[] = [['genderCode' => $value]]; }
        foreach (['2023-02-29', '2024-02-30', '2024-2-01', '01-02-2024', '0000-00-00', '2024-01-01 00:00:00', '2024-01-01Z', date('Y-m-d', strtotime('+1 day'))] as $value) { $cases[] = [['birthDate' => $value]]; }
        return $cases;
    }

    public function test_school_scoped_identity_uniqueness_for_create_and_update_is_secret_safe(): void
    {
        $first = $this->create();
        $foreign = $this->create(['schoolId' => $this->foreignSchool]); self::assertNotSame($first, $foreign);
        $other = $this->create(['studentCode' => 'other', 'nationalId' => null]);
        $this->create(['studentCode' => 'another-null', 'nationalId' => null]);
        foreach ([['studentCode' => 'S001', 'nationalId' => null], ['studentCode' => 'unique', 'nationalId' => self::NATIONAL]] as $change) {
            $before = $this->snapshot();
            $this->deny(fn () => $this->create($change)); $this->deny(fn () => $this->update($other, $change));
            self::assertSame($before, $this->snapshot());
        }
    }

    public function test_repository_scopes_all_reads_and_writes_and_never_searches_or_lists_national_id(): void
    {
        $own = $this->fixture(['student_code' => 'A']);
        $inactive = $this->fixture(['student_code' => 'Z', 'national_id' => null, 'status' => 'INACTIVE']);
        $foreign = $this->fixture(['school_id' => $this->foreignSchool, 'student_code' => 'FOREIGN', 'national_id' => '0000000000002', 'first_name_th' => 'Foreign']);
        $repo = $this->repo();
        self::assertSame([$own, $inactive], array_column($repo->listForSchool($this->school), 'id'));
        foreach ($repo->listForSchool($this->school) as $row) { self::assertArrayNotHasKey('national_id', $row); }
        foreach (['A', 'ทดสอบ', 'สมมติ', 'ด.ช. ทดสอบ สมมติ'] as $search) { self::assertContains($own, array_column($repo->listForSchool($this->school, $search), 'id')); }
        foreach ([self::NATIONAL, '0000000000002', 'FOREIGN', '%', '_', "' OR 1=1 --"] as $search) { self::assertSame([], $repo->listForSchool($this->school, $search)); }
        self::assertSame($own, $repo->findForSchool($this->school, $own)['id']);
        self::assertSame($own, $repo->lockForSchool($this->school, $own)['id']);
        self::assertSame($own, $repo->findByCodeForSchool($this->school, 'A')['id']);
        self::assertSame($own, $repo->findByNationalIdForSchool($this->school, self::NATIONAL)['id']);
        self::assertNull($repo->findByCodeForSchool($this->school, 'FOREIGN'));
        self::assertNull($repo->findByNationalIdForSchool($this->school, '0000000000002'));
        foreach ([$foreign, 0] as $id) {
            self::assertNull($repo->findForSchool($this->school, $id)); self::assertNull($repo->lockForSchool($this->school, $id));
            $before = $this->snapshot(); $repo->update($this->school, $id, $this->normalize(['studentCode' => 'Changed']));
            $repo->updateStatus($this->school, $id, 'INACTIVE'); self::assertSame($before, $this->snapshot());
        }
        self::assertSame([], $repo->listForSchool(0));
    }

    public function test_foreign_and_missing_mutation_targets_fail_identically_without_writes(): void
    {
        $foreign = $this->fixture(['school_id' => $this->foreignSchool]); $service = $this->service();
        $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        $errors = [];
        foreach ([$foreign, 0] as $id) {
            $errors[] = $this->deny(fn () => $this->update($id));
            $errors[] = $this->deny(fn () => $service->changeStatus($this->school, $this->actor, $id, 'INACTIVE'));
        }
        self::assertCount(1, array_unique($errors)); self::assertSame(0, $this->pdo->writeAttempts); self::assertSame($before, $this->snapshot());
    }

    public function test_code_and_profile_correction_preserves_identity_enrollment_and_placement_history(): void
    {
        $id = $this->fixture(); $this->enroll($id, 'CLOSED', 'ACTIVE', true);
        $history = $this->history();
        $this->update($id, ['studentCode' => 'CORRECTED', 'nationalId' => '0000000000003', 'firstNameTh' => 'แก้ไข']);
        self::assertSame($id, $this->student($id)['id']); self::assertSame('CORRECTED', $this->student($id)['student_code']);
        self::assertSame('0000000000003', $this->student($id)['national_id']); self::assertSame('แก้ไข', $this->student($id)['first_name_th']);
        self::assertSame($history, $this->history());
        $this->audit($id, 'STUDENT_UPDATED', null, ['changed_fields' => ['student_code', 'national_id', 'first_name_th']]);
    }

    public function test_semantic_profile_noop_and_same_status_have_no_write_or_audit(): void
    {
        $id = $this->fixture(['national_id' => null]); $service = $this->service();
        $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        $this->update($id, ['studentCode' => "\u{3000}S001 ", 'nationalId' => ' ', 'genderCode' => '', 'birthDate' => '']);
        $service->changeStatus($this->school, $this->actor, $id, 'ACTIVE');
        self::assertSame(0, $this->pdo->writeAttempts); self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('statusPairs')]
    public function test_status_round_trip_and_noop_preserve_student(string $from, string $to): void
    {
        $id = $this->fixture(['status' => $from]); $service = $this->service();
        $service->changeStatus($this->school, $this->actor, $id, $to, self::IP);
        self::assertSame($to, $this->student($id)['status']);
        $this->audit($id, 'STUDENT_STATUS_CHANGED', ['status' => $from], ['status' => $to]);
        $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        $service->changeStatus($this->school, $this->actor, $id, $to);
        self::assertSame(0, $this->pdo->writeAttempts); self::assertSame($before, $this->snapshot());
    }
    public static function statusPairs(): array { return [['ACTIVE', 'INACTIVE'], ['INACTIVE', 'ACTIVE']]; }

    #[DataProvider('enrollmentStates')]
    public function test_inactivation_guard_counts_only_own_active_enrollment_in_open_year(string $yearStatus, string $enrollmentStatus, bool $blocked): void
    {
        $id = $this->fixture(); $this->enroll($id, $yearStatus, $enrollmentStatus);
        $service = $this->service(); $repo = new StudentEnrollmentRepository($this->pdo);
        self::assertSame($blocked, $repo->hasActiveInOpenYear($this->school, $id));
        self::assertFalse($repo->hasActiveInOpenYear($this->foreignSchool, $id)); self::assertFalse($repo->hasActiveInOpenYear($this->school, 0));
        $before = $this->snapshot(); $history = $this->history(); $this->pdo->writeAttempts = 0;
        if ($blocked) {
            $this->deny(fn () => $service->changeStatus($this->school, $this->actor, $id, 'INACTIVE'));
            self::assertSame(0, $this->pdo->writeAttempts); self::assertSame($before, $this->snapshot());
        } else { $service->changeStatus($this->school, $this->actor, $id, 'INACTIVE'); self::assertSame('INACTIVE', $this->student($id)['status']); }
        self::assertSame($history, $this->history());
    }
    public static function enrollmentStates(): array
    {
        $cases = [];
        foreach (['DRAFT', 'ACTIVE', 'CLOSED'] as $year) { foreach (['ACTIVE', 'TRANSFERRED_OUT', 'WITHDRAWN'] as $state) { $cases[] = [$year, $state, $year !== 'CLOSED' && $state === 'ACTIVE']; } }
        return $cases;
    }

    #[DataProvider('badStatuses')]
    public function test_invalid_status_does_not_write(string $status): void
    {
        $id = $this->fixture(); $service = $this->service(); $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        $this->deny(fn () => $service->changeStatus($this->school, $this->actor, $id, $status));
        self::assertSame(0, $this->pdo->writeAttempts); self::assertSame($before, $this->snapshot());
    }
    public static function badStatuses(): array { return [[''], ['active'], [' ACTIVE '], ['CLOSED'], ['SUSPENDED'], [self::NATIONAL]]; }

    #[DataProvider('unavailableSchools')]
    public function test_every_mutation_requires_active_school(string $state): void
    {
        $id = $this->fixture(); $service = $this->service();
        $this->pdo->prepare('UPDATE schools SET status=? WHERE id=?')->execute([$state, $this->school]);
        $before = $this->snapshot(); $this->pdo->writeAttempts = 0;
        foreach (['create', 'update', 'status'] as $action) { $this->deny(fn () => $this->operate($action, $id)); }
        $this->deny(fn () => $this->create(['schoolId' => 0]));
        self::assertSame(0, $this->pdo->writeAttempts); self::assertSame($before, $this->snapshot());
    }
    public static function unavailableSchools(): array { return [['INACTIVE'], ['SUSPENDED']]; }

    #[DataProvider('failures')]
    public function test_begin_repository_audit_commit_failures_roll_back_all_changes(string $action, string $failure): void
    {
        $id = $this->fixture(); $service = $this->service(); $before = $this->snapshot();
        if ($failure === 'begin') { $this->pdo->failBegin = true; }
        elseif ($failure === 'commit') { $this->pdo->failCommit = true; }
        else { $this->pdo->failPrepare = $failure === 'audit' ? 'INSERT INTO audit_logs' : ($action === 'create' ? 'INSERT INTO students' : 'UPDATE students'); }
        $this->deny(fn () => $this->operate($action, $id));
        self::assertTrue($this->pdo->failureTriggered, 'Injection must actually fire');
        self::assertSame(1, $this->pdo->depth); self::assertTrue($this->pdo->inTransaction());
        self::assertSame($before, $this->snapshot());
    }
    public static function failures(): array
    {
        $cases = [];
        foreach (['create', 'update', 'status'] as $action) { foreach (['begin', 'write', 'audit', 'commit'] as $failure) { $cases[] = [$action, $failure]; } }
        return $cases;
    }

    public function test_mutations_lock_school_before_tenant_target_and_enrollment_check(): void
    {
        $id = $this->fixture(); $service = $this->service();
        foreach (['create', 'update', 'status'] as $action) {
            $this->pdo->beginTransaction(); $this->pdo->queries = [];
            $this->operate($action, $id);
            $queries = $this->pdo->queries;
            $locks = array_values(array_filter($queries, static fn (string $q): bool => str_contains($q, 'FOR UPDATE')));
            self::assertCount($action === 'create' ? 1 : 2, $locks);
            self::assertStringContainsString('FROM schools', $locks[0]);
            if ($action !== 'create') {
                self::assertStringContainsString('FROM students', $locks[1]);
                self::assertMatchesRegularExpression('/school_id\s*=\s*:school_id\s+AND\s+id\s*=\s*:student_id/', $locks[1]);
            }
            if ($action === 'status') {
                $guard = array_values(array_filter($queries, static fn (string $q): bool => str_contains($q, 'FROM student_enrollments')));
                self::assertCount(1, $guard);
                self::assertGreaterThan(array_search($locks[1], $queries, true), array_search($guard[0], $queries, true));
            }
            $this->pdo->rollBack();
        }
    }

    private function profile(): array
    {
        return ['studentCode' => 'S001', 'nationalId' => self::NATIONAL, 'prefixTh' => 'ด.ช.', 'firstNameTh' => 'ทดสอบ', 'lastNameTh' => 'สมมติ', 'genderCode' => null, 'birthDate' => null];
    }
    private function normalize(array $changes = []): array
    {
        self::assertTrue(class_exists(StudentProfileRules::class), 'StudentProfileRules missing');
        return StudentProfileRules::normalize(...array_replace($this->profile(), $changes));
    }
    private function repo(): StudentRepository
    {
        self::assertTrue(class_exists(StudentRepository::class), 'StudentRepository missing');
        return new StudentRepository($this->pdo);
    }
    private function service(): StudentAdministrationService
    {
        self::assertTrue(class_exists(StudentAdministrationService::class), 'StudentAdministrationService missing');
        self::assertTrue(class_exists(StudentEnrollmentRepository::class), 'StudentEnrollmentRepository missing');
        return new StudentAdministrationService($this->pdo, new SchoolRepository($this->pdo), $this->repo(), new StudentEnrollmentRepository($this->pdo), new AuditLogRepository($this->pdo));
    }
    private function create(array $changes = []): int
    {
        return $this->service()->createStudent(...array_replace(['schoolId' => $this->school, 'actorUserId' => $this->actor, ...$this->profile(), 'ipAddress' => self::IP], $changes));
    }
    private function update(int $id, array $changes = []): void
    {
        $this->service()->updateStudent(...array_replace(['schoolId' => $this->school, 'actorUserId' => $this->actor, 'studentId' => $id, ...$this->profile(), 'ipAddress' => self::IP], $changes));
    }
    private function operate(string $action, int $id): void
    {
        match ($action) {
            'create' => $this->create(['studentCode' => 'NEW', 'nationalId' => null]),
            'update' => $this->update($id, ['studentCode' => 'UPDATED']),
            'status' => $this->service()->changeStatus($this->school, $this->actor, $id, 'INACTIVE', self::IP),
        };
    }
    private function fixture(array $changes = []): int
    {
        return $this->insert('students', array_replace(['school_id' => $this->school, 'student_code' => 'S001', 'national_id' => self::NATIONAL,
            'prefix_th' => 'ด.ช.', 'first_name_th' => 'ทดสอบ', 'last_name_th' => 'สมมติ'], $changes));
    }
    private function enroll(int $student, string $yearStatus, string $status, bool $placement = false): void
    {
        $year = $this->insert('academic_years', ['school_id' => $this->school, 'year_be' => 2569, 'status' => $yearStatus]);
        $grade = (int) $this->pdo->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        $enrollment = $this->insert('student_enrollments', ['school_id' => $this->school, 'academic_year_id' => $year, 'student_id' => $student, 'grade_level_id' => $grade, 'status' => $status]);
        if ($placement) {
            $room = $this->insert('classrooms', ['school_id' => $this->school, 'academic_year_id' => $year, 'grade_level_id' => $grade, 'code' => 'P1', 'name_th' => 'Class']);
            $this->insert('student_classroom_placements', ['school_id' => $this->school, 'academic_year_id' => $year, 'grade_level_id' => $grade, 'enrollment_id' => $enrollment, 'classroom_id' => $room]);
        }
    }
    private function insert(string $table, array $values): int
    {
        $q = $this->pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($values)) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')');
        $q->execute(array_values($values)); return (int) $this->pdo->lastInsertId();
    }
    private function student(int $id): array { $q = $this->pdo->prepare('SELECT * FROM students WHERE id=?'); $q->execute([$id]); return $q->fetch(); }
    private function history(): array { return [$this->pdo->query('SELECT * FROM student_enrollments ORDER BY id')->fetchAll(), $this->pdo->query('SELECT * FROM student_classroom_placements ORDER BY id')->fetchAll()]; }
    private function snapshot(): array { return [$this->pdo->query('SELECT * FROM students ORDER BY id')->fetchAll(), $this->pdo->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll(), $this->history()]; }
    private function audit(int $id, string $action, ?array $old, array $new): void
    {
        $rows = $this->pdo->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll(); self::assertCount(1, $rows); $r = $rows[0];
        self::assertSame([$this->school, $this->actor, $id, 'students', $action, self::IP], [$r['school_id'], $r['user_id'], $r['entity_id'], $r['entity_type'], $r['action'], $r['ip_address']]);
        self::assertSame($old, $r['old_value'] === null ? null : json_decode($r['old_value'], true));
        self::assertSame($new, json_decode($r['new_value'], true)); self::assertNotEmpty($r['created_at']);
        $this->safe(json_encode($r, JSON_UNESCAPED_UNICODE));
    }
    private function safe(string $text): void
    {
        foreach ([self::NATIONAL, '0000000000003', 'ทดสอบ', 'สมมติ', 'แก้ไข', 'SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', '/Applications/', 'private-details', 'password', 'uq_students', 'fk_student'] as $secret) {
            self::assertStringNotContainsString($secret, $text);
        }
    }
    private function deny(callable $operation): string
    {
        try { $operation(); } catch (DomainException $e) {
            self::assertNotSame('', $e->getMessage()); self::assertNull($e->getPrevious()); $this->safe($e->getMessage()); return $e->getMessage();
        }
        self::fail('Expected safe DomainException');
    }
}

/** Real PDO writes with savepoints for fixture isolation and targeted failure injection. */
final class StudentDomainPDO extends PDO
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
        if ($this->failBegin) { $this->failBegin = false; $this->failureTriggered = true; throw new PDOException('SQLSTATE private-details 0000000000001'); }
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT student_domain_' . $this->depth) !== false;
        ++$this->depth; return $ok;
    }
    public function commit(): bool
    {
        if ($this->failCommit) { $this->failCommit = false; $this->failureTriggered = true; throw new PDOException('SQLSTATE private-details 0000000000001'); }
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT student_domain_' . ($this->depth - 1)) !== false;
        --$this->depth; return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else { $ok = $this->exec('ROLLBACK TO SAVEPOINT student_domain_' . ($this->depth - 1)) !== false; $this->exec('RELEASE SAVEPOINT student_domain_' . ($this->depth - 1)); }
        --$this->depth; return $ok;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $query)) { ++$this->writeAttempts; }
        if ($this->failPrepare !== null && str_contains($query, $this->failPrepare)) {
            $this->failPrepare = null; $this->failureTriggered = true;
            throw new PDOException('SQLSTATE private-details 0000000000001 SELECT /Applications/MAMP/secret.php');
        }
        return parent::prepare($query, $options);
    }
}
