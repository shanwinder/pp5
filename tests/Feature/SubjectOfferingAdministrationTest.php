<?php
declare(strict_types=1);

use App\Repositories\AcademicYearRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClassroomRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\SubjectOfferingRepository;
use App\Services\SubjectOfferingAdministrationService;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SubjectOfferingAdministrationTest extends TestCase
{
    private OfferingDomainPDO $pdo;
    private int $school;
    private int $foreignSchool;
    private int $actor;
    private int $year;
    private int $nextYear;
    private int $foreignYear;
    private int $room;
    private int $otherRoom;
    private int $wrongYearRoom;
    private int $inactiveRoom;
    private int $foreignRoom;
    private int $subject;
    private int $otherSubject;
    private int $inactiveSubject;
    private int $foreignSubject;

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new OfferingDomainPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->school = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['offering-domain-a', 'School A']);
        $this->foreignSchool = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['offering-domain-b', 'School B']);
        $this->actor = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['offering-domain-actor', 'unused', 'Actor']);
        $this->year = $this->fixtureYear($this->school, 2569);
        $this->nextYear = $this->fixtureYear($this->school, 2570);
        $this->foreignYear = $this->fixtureYear($this->foreignSchool, 2569);
        $this->room = $this->fixtureRoom($this->school, $this->year, 'A');
        $this->otherRoom = $this->fixtureRoom($this->school, $this->year, 'B');
        $this->wrongYearRoom = $this->fixtureRoom($this->school, $this->nextYear, 'A');
        $this->inactiveRoom = $this->fixtureRoom($this->school, $this->year, 'INACTIVE', 'INACTIVE');
        $this->foreignRoom = $this->fixtureRoom($this->foreignSchool, $this->foreignYear, 'A');
        $this->subject = $this->fixtureSubject($this->school, 'SCI');
        $this->otherSubject = $this->fixtureSubject($this->school, 'MATH');
        $this->inactiveSubject = $this->fixtureSubject($this->school, 'INACTIVE', 'INACTIVE');
        $this->foreignSubject = $this->fixtureSubject($this->foreignSchool, 'SCI');
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->failPrepare = null;
            while ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
        }
    }

    public function test_repository_scopes_targets_filters_and_detail_joins_with_deterministic_order(): void
    {
        $term2 = $this->fixture(['termNo' => 2]);
        $term1 = $this->fixture();
        $math = $this->fixture(['subjectId' => $this->otherSubject]);
        $next = $this->fixture(['academicYearId' => $this->nextYear, 'classroomId' => $this->wrongYearRoom]);
        $foreign = $this->fixture(['schoolId' => $this->foreignSchool, 'academicYearId' => $this->foreignYear, 'classroomId' => $this->foreignRoom, 'subjectId' => $this->foreignSubject]);
        $repo = $this->repo();
        self::assertSame([$next, $math, $term1, $term2], array_column($repo->listForSchool($this->school), 'id'));
        self::assertSame([$math, $term1, $term2], array_column($repo->listForSchool($this->school, $this->year), 'id'));
        self::assertSame([], $repo->listForSchool($this->school, $this->foreignYear));
        self::assertNull($repo->findForSchool($this->school, $foreign));
        self::assertNull($repo->lockForSchool($this->school, $foreign));
        self::assertNull($repo->findForSchool($this->school, 0));
        $detail = $repo->findForSchool($this->school, $term1);
        self::assertSame([2569, 'DRAFT', 'A', 'ห้อง A', 'ACTIVE', 'SCI', 'วิชา SCI', 'ACTIVE'],
            array_map(static fn (string $key): mixed => $detail[$key], ['year_be', 'academic_year_status', 'classroom_code', 'classroom_name', 'classroom_status', 'subject_code', 'subject_name', 'subject_status']));
        $before = $this->snapshot();
        $repo->update($this->school, $foreign, $this->room, $this->subject, 2);
        $repo->updateStatus($this->school, $foreign, 'INACTIVE');
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('openYearsAndTerms')]
    public function test_create_in_open_year_defaults_active_with_exact_identity_and_audit(string $state, int $term): void
    {
        $this->setYear($state);
        $id = $this->create(['termNo' => $term]);
        $row = $this->offering($id);
        self::assertSame([$this->school, $this->year, $this->room, $this->subject, $term, 'ACTIVE'],
            array_map(static fn (string $key): mixed => $row[$key], ['school_id', 'academic_year_id', 'classroom_id', 'subject_id', 'term_no', 'status']));
        $this->assertAudit($id, 'SUBJECT_OFFERING_CREATED', null, ['academic_year_id' => $this->year, 'classroom_id' => $this->room, 'subject_id' => $this->subject, 'term_no' => $term, 'status' => 'ACTIVE']);
    }
    public static function openYearsAndTerms(): array { return [['DRAFT', 1], ['DRAFT', 2], ['ACTIVE', 1], ['ACTIVE', 2]]; }

    #[DataProvider('invalidParents')]
    public function test_invalid_parent_is_rejected_before_any_write_for_create_and_update(string $field, ?string $property): void
    {
        $id = $this->fixture();
        $service = $this->service();
        $value = $property === null ? 0 : $this->$property;
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->deny(fn () => $this->create([$field => $value]));
        if ($field !== 'academicYearId') {
            $this->deny(fn () => $service->updateOffering($this->school, $this->actor, $id,
                $field === 'classroomId' ? $value : $this->room, $field === 'subjectId' ? $value : $this->subject, 2));
        }
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }
    public static function invalidParents(): array
    {
        return [['academicYearId', 'foreignYear'], ['academicYearId', null], ['classroomId', 'foreignRoom'],
            ['classroomId', 'wrongYearRoom'], ['classroomId', 'inactiveRoom'], ['classroomId', null],
            ['subjectId', 'foreignSubject'], ['subjectId', 'inactiveSubject'], ['subjectId', null]];
    }

    #[DataProvider('invalidTerms')]
    public function test_invalid_integer_terms_cannot_create_or_update(int $term): void
    {
        $id = $this->fixture();
        $service = $this->service();
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->deny(fn () => $this->create(['termNo' => $term]));
        $this->deny(fn () => $service->updateOffering($this->school, $this->actor, $id, $this->room, $this->subject, $term));
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }
    public static function invalidTerms(): array { return [[0], [3], [-1], [PHP_INT_MAX]]; }

    public function test_duplicates_are_scoped_to_full_identity_including_inactive_rows(): void
    {
        $first = $this->fixture(['status' => 'INACTIVE']);
        $service = $this->service();
        $before = $this->snapshot();
        self::assertSame('มีการเปิดรายวิชานี้สำหรับห้องเรียนและภาคเรียนนี้แล้ว', $this->deny(fn () => $this->create()));
        self::assertSame($before, $this->snapshot());
        self::assertSame('INACTIVE', $this->offering($first)['status']);
        $term2 = $this->create(['termNo' => 2]);
        $this->create(['academicYearId' => $this->nextYear, 'classroomId' => $this->wrongYearRoom]);
        $this->create(['schoolId' => $this->foreignSchool, 'academicYearId' => $this->foreignYear, 'classroomId' => $this->foreignRoom, 'subjectId' => $this->foreignSubject]);
        $before = $this->snapshot();
        self::assertSame('มีการเปิดรายวิชานี้สำหรับห้องเรียนและภาคเรียนนี้แล้ว', $this->deny(fn () => $service->updateOffering($this->school, $this->actor, $term2, $this->room, $this->subject, 1)));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('detailChanges')]
    public function test_updates_only_requested_business_fields_and_preserves_school_year(string $change, int $initialTerm, int $term): void
    {
        $id = $this->fixture(['termNo' => $initialTerm]);
        $classroom = in_array($change, ['classroom', 'all'], true) ? $this->otherRoom : $this->room;
        $subject = in_array($change, ['subject', 'all'], true) ? $this->otherSubject : $this->subject;
        $this->service()->updateOffering($this->school, $this->actor, $id, $classroom, $subject, $term, '192.0.2.60');
        $row = $this->offering($id);
        self::assertSame([$this->school, $this->year, $classroom, $subject, $term],
            array_map(static fn (string $key): mixed => $row[$key], ['school_id', 'academic_year_id', 'classroom_id', 'subject_id', 'term_no']));
        $old = []; $new = [];
        if ($classroom !== $this->room) { $old['classroom_id'] = $this->room; $new['classroom_id'] = $classroom; }
        if ($subject !== $this->subject) { $old['subject_id'] = $this->subject; $new['subject_id'] = $subject; }
        if ($term !== $initialTerm) { $old['term_no'] = $initialTerm; $new['term_no'] = $term; }
        $this->assertAudit($id, 'SUBJECT_OFFERING_UPDATED', $old, $new);
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->service()->updateOffering($this->school, $this->actor, $id, $classroom, $subject, $term);
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }
    public static function detailChanges(): array { return [['classroom', 1, 1], ['subject', 1, 1], ['term', 1, 2], ['term', 2, 1], ['all', 1, 2]]; }

    #[DataProvider('statusFlows')]
    public function test_status_transition_retains_same_row_and_exact_audit_with_noop(string $yearStatus, string $from, string $to): void
    {
        $this->setYear($yearStatus);
        $id = $this->fixture(['status' => $from]);
        $beforeRow = $this->offering($id);
        $this->service()->changeStatus($this->school, $this->actor, $id, $to, '192.0.2.60');
        $row = $this->offering($id);
        self::assertSame($id, $row['id']);
        self::assertSame($to, $row['status']);
        foreach (['school_id', 'academic_year_id', 'classroom_id', 'subject_id', 'term_no', 'created_at'] as $field) { self::assertSame($beforeRow[$field], $row[$field]); }
        self::assertCount(1, $this->repo()->listForSchool($this->school));
        $this->assertAudit($id, 'SUBJECT_OFFERING_STATUS_CHANGED', ['status' => $from], ['status' => $to]);
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->service()->changeStatus($this->school, $this->actor, $id, $to);
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }
    public static function statusFlows(): array
    {
        return [['DRAFT', 'ACTIVE', 'INACTIVE'], ['DRAFT', 'INACTIVE', 'ACTIVE'], ['ACTIVE', 'ACTIVE', 'INACTIVE'], ['ACTIVE', 'INACTIVE', 'ACTIVE']];
    }

    #[DataProvider('parentStates')]
    public function test_inactivation_allows_inactive_parent_but_reactivation_revalidates_it(string $table): void
    {
        $id = $this->fixture();
        $parentId = $table === 'classrooms' ? $this->room : $this->subject;
        $this->pdo->prepare("UPDATE {$table} SET status = 'INACTIVE' WHERE id = ?")->execute([$parentId]);
        $this->service()->changeStatus($this->school, $this->actor, $id, 'INACTIVE', '192.0.2.60');
        self::assertSame('INACTIVE', $this->offering($id)['status']);
        $this->assertAudit($id, 'SUBJECT_OFFERING_STATUS_CHANGED', ['status' => 'ACTIVE'], ['status' => 'INACTIVE']);
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->service()->changeStatus($this->school, $this->actor, $id, 'INACTIVE');
        $this->deny(fn () => $this->service()->changeStatus($this->school, $this->actor, $id, 'ACTIVE'));
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }
    public static function parentStates(): array { return [['classrooms'], ['subjects']]; }

    #[DataProvider('closedTransitions')]
    public function test_closed_year_blocks_all_mutations_even_same_status_and_keeps_history(string $from, string $to): void
    {
        $id = $this->fixture(['status' => $from]);
        $this->setYear('CLOSED');
        $service = $this->service();
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->deny(fn () => $this->create(['termNo' => 2]));
        $this->deny(fn () => $service->updateOffering($this->school, $this->actor, $id, $this->room, $this->subject, 1));
        $this->deny(fn () => $service->changeStatus($this->school, $this->actor, $id, $to));
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
        self::assertSame('CLOSED', $this->repo()->findForSchool($this->school, $id)['academic_year_status']);
    }
    public static function closedTransitions(): array { return [['ACTIVE', 'INACTIVE'], ['INACTIVE', 'ACTIVE'], ['ACTIVE', 'ACTIVE'], ['INACTIVE', 'INACTIVE']]; }

    public function test_invalid_statuses_and_foreign_missing_targets_do_not_write_or_disclose(): void
    {
        $id = $this->fixture();
        $foreign = $this->fixture(['schoolId' => $this->foreignSchool, 'academicYearId' => $this->foreignYear, 'classroomId' => $this->foreignRoom, 'subjectId' => $this->foreignSubject]);
        $service = $this->service();
        $before = $this->snapshot();
        foreach (['UNKNOWN', 'CLOSED', 'DRAFT', 'active', 'inactive', ''] as $status) { $this->deny(fn () => $service->changeStatus($this->school, $this->actor, $id, $status)); }
        $messages = [];
        foreach ([$foreign, 0] as $target) {
            $messages[] = $this->deny(fn () => $service->updateOffering($this->school, $this->actor, $target, $this->room, $this->subject, 2));
            $messages[] = $this->deny(fn () => $service->changeStatus($this->school, $this->actor, $target, 'INACTIVE'));
        }
        self::assertCount(1, array_unique($messages));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('unavailableSchools')]
    public function test_school_must_be_active_for_every_mutation(string $state): void
    {
        $id = $this->fixture();
        $school = $this->school;
        if ($state === 'MISSING') { $school = 0; }
        else { $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?')->execute([$state, $school]); }
        $service = $this->service();
        $before = $this->snapshot();
        $this->deny(fn () => $service->createOffering($school, $this->actor, $this->year, $this->room, $this->subject, 2));
        $this->deny(fn () => $service->updateOffering($school, $this->actor, $id, $this->room, $this->subject, 2));
        $this->deny(fn () => $service->changeStatus($school, $this->actor, $id, 'ACTIVE'));
        self::assertSame($before, $this->snapshot());
    }
    public static function unavailableSchools(): array { return [['INACTIVE'], ['SUSPENDED'], ['MISSING']]; }

    #[DataProvider('failureCases')]
    public function test_begin_write_audit_commit_failures_are_atomic(string $action, string $failure): void
    {
        $id = $this->fixture(['status' => 'INACTIVE']);
        $service = $this->service();
        $before = $this->snapshot();
        if ($failure === 'begin') { $this->pdo->failBegin = true; }
        elseif ($failure === 'commit') { $this->pdo->failCommit = true; }
        else { $this->pdo->failPrepare = $failure === 'audit' ? 'INSERT INTO audit_logs' : ($action === 'create' ? 'INSERT INTO subject_offerings' : 'UPDATE subject_offerings'); }
        $this->deny(fn () => $this->operate($service, $action, $id));
        self::assertTrue($this->pdo->failureTriggered);
        self::assertSame(1, $this->pdo->depth);
        self::assertTrue($this->pdo->inTransaction());
        self::assertSame($before, $this->snapshot());
    }
    public static function failureCases(): array
    {
        $cases = [];
        foreach (['create', 'update', 'status'] as $action) { foreach (['begin', 'write', 'audit', 'commit'] as $failure) { $cases[] = [$action, $failure]; } }
        return $cases;
    }

    public function test_school_first_and_tenant_scoped_parent_target_locks(): void
    {
        $id = $this->fixture(['status' => 'INACTIVE']);
        $service = $this->service();
        foreach (['create', 'update', 'status'] as $action) {
            $this->pdo->beginTransaction();
            $this->pdo->queries = [];
            $this->operate($service, $action, $id);
            $locks = array_values(array_filter($this->pdo->queries, static fn (string $q): bool => str_contains($q, 'FOR UPDATE')));
            $expected = $action === 'create' ? ['schools', 'academic_years', 'classrooms', 'subjects'] : ['schools', 'subject_offerings', 'academic_years', 'classrooms', 'subjects'];
            self::assertCount(count($expected), $locks);
            foreach ($expected as $index => $table) {
                self::assertStringContainsString('FROM ' . $table, $locks[$index]);
                if ($table !== 'schools') { self::assertStringContainsString('school_id = ?', $locks[$index]); }
            }
            $this->pdo->rollBack();
        }
    }

    private function service(): SubjectOfferingAdministrationService
    {
        self::assertTrue(class_exists(SubjectOfferingAdministrationService::class), 'SubjectOfferingAdministrationService missing');
        return new SubjectOfferingAdministrationService($this->pdo, new SchoolRepository($this->pdo), new AcademicYearRepository($this->pdo),
            new ClassroomRepository($this->pdo), new SubjectRepository($this->pdo), $this->repo(), new AuditLogRepository($this->pdo));
    }
    private function repo(): SubjectOfferingRepository
    {
        self::assertTrue(class_exists(SubjectOfferingRepository::class), 'SubjectOfferingRepository missing');
        return new SubjectOfferingRepository($this->pdo);
    }
    private function create(array $overrides = []): int
    {
        return $this->service()->createOffering(...array_replace(['schoolId' => $this->school, 'actorUserId' => $this->actor,
            'academicYearId' => $this->year, 'classroomId' => $this->room, 'subjectId' => $this->subject, 'termNo' => 1, 'ipAddress' => '192.0.2.60'], $overrides));
    }
    private function operate(SubjectOfferingAdministrationService $service, string $action, int $id): void
    {
        match ($action) {
            'create' => $service->createOffering($this->school, $this->actor, $this->year, $this->room, $this->subject, 2),
            'update' => $service->updateOffering($this->school, $this->actor, $id, $this->otherRoom, $this->otherSubject, 2),
            'status' => $service->changeStatus($this->school, $this->actor, $id, 'ACTIVE'),
        };
    }
    private function assertAudit(int $id, string $action, ?array $old, array $new): void
    {
        $rows = $this->rows('SELECT * FROM audit_logs ORDER BY id'); self::assertCount(1, $rows); $row = $rows[0];
        self::assertSame([$this->school, $this->actor, 'subject_offerings', $id, $action, '192.0.2.60', null], array_map(static fn (string $k): mixed => $row[$k], ['school_id', 'user_id', 'entity_type', 'entity_id', 'action', 'ip_address', 'reason']));
        self::assertSame($old, $row['old_value'] === null ? null : json_decode($row['old_value'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame($new, json_decode($row['new_value'], true, 512, JSON_THROW_ON_ERROR)); self::assertNotEmpty($row['created_at']);
    }
    private function deny(callable $operation): string
    {
        try { $operation(); self::fail('Expected friendly DomainException'); }
        catch (DomainException $e) {
            self::assertNotSame('', $e->getMessage()); self::assertNull($e->getPrevious());
            foreach (['SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', 'uq_offering', 'fk_offering', 'private-db-details', '/Applications/MAMP/', 'htdocs/app/'] as $secret) { self::assertStringNotContainsString($secret, $e->getMessage()); }
            return $e->getMessage();
        }
    }
    private function fixture(array $overrides = []): int
    {
        $values = array_replace(['schoolId' => $this->school, 'academicYearId' => $this->year, 'classroomId' => $this->room, 'subjectId' => $this->subject, 'termNo' => 1, 'status' => 'ACTIVE'], $overrides);
        return $this->insert('INSERT INTO subject_offerings (school_id, academic_year_id, classroom_id, subject_id, term_no, status) VALUES (?, ?, ?, ?, ?, ?)', array_values($values));
    }
    private function fixtureYear(int $school, int $year): int { return $this->insert('INSERT INTO academic_years (school_id, year_be) VALUES (?, ?)', [$school, $year]); }
    private function fixtureRoom(int $school, int $year, string $code, string $status = 'ACTIVE'): int
    {
        return $this->insert("INSERT INTO classrooms (school_id, academic_year_id, grade_level_id, code, name_th, status) SELECT ?, ?, id, ?, ?, ? FROM grade_levels WHERE code = 'P1'", [$school, $year, $code, 'ห้อง ' . $code, $status]);
    }
    private function fixtureSubject(int $school, string $code, string $status = 'ACTIVE'): int { return $this->insert('INSERT INTO subjects (school_id, code, name_th, status) VALUES (?, ?, ?, ?)', [$school, $code, 'วิชา ' . $code, $status]); }
    private function setYear(string $status): void { $this->pdo->prepare('UPDATE academic_years SET status = ? WHERE id = ?')->execute([$status, $this->year]); }
    private function offering(int $id): array { return $this->rows('SELECT * FROM subject_offerings WHERE id = ?', [$id])[0]; }
    private function snapshot(): array { return [$this->rows('SELECT * FROM subject_offerings ORDER BY id'), $this->rows('SELECT * FROM audit_logs ORDER BY id')]; }
    private function rows(string $sql, array $params = []): array { $q = $this->pdo->prepare($sql); $q->execute($params); return $q->fetchAll(); }
    private function insert(string $sql, array $params): int { $this->pdo->prepare($sql)->execute($params); return (int) $this->pdo->lastInsertId(); }
}

/** Keep service transactions inside fixture rollback using real savepoints. */
final class OfferingDomainPDO extends PDO
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
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT offering_domain_' . $this->depth) !== false;
        ++$this->depth;
        return $ok;
    }
    public function commit(): bool
    {
        if ($this->failCommit) { $this->failCommit = false; $this->failureTriggered = true; throw new PDOException('SQLSTATE private-db-details'); }
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT offering_domain_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else { $ok = $this->exec('ROLLBACK TO SAVEPOINT offering_domain_' . ($this->depth - 1)) !== false; $this->exec('RELEASE SAVEPOINT offering_domain_' . ($this->depth - 1)); }
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
