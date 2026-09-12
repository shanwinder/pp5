<?php
declare(strict_types=1);

use App\Repositories\AcademicYearRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClassroomRepository;
use App\Repositories\GradeLevelRepository;
use App\Repositories\SchoolRepository;
use App\Services\ClassroomAdministrationService;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClassroomAdministrationTest extends TestCase
{
    private ClassroomDomainPDO $pdo;
    private int $school;
    private int $foreignSchool;
    private int $year;
    private int $foreignYear;
    private int $actor;
    private int $grade;
    private int $otherGrade;

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new ClassroomDomainPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->school = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['room-domain-a', 'School A']);
        $this->foreignSchool = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['room-domain-b', 'School B']);
        $this->year = $this->fixtureYear($this->school, 2569);
        $this->foreignYear = $this->fixtureYear($this->foreignSchool, 2569);
        $this->actor = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)', ['room-domain-actor', 'unused', 'Actor']);
        $this->grade = $this->rows("SELECT id FROM grade_levels WHERE code = 'P1'")[0]['id'];
        $this->otherGrade = $this->rows("SELECT id FROM grade_levels WHERE code = 'P2'")[0]['id'];
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->failPrepare = null;
            while ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    public function test_global_grade_reference_is_active_only_and_deterministic(): void
    {
        self::assertTrue(class_exists(GradeLevelRepository::class), 'GradeLevelRepository missing');
        $this->pdo->prepare('UPDATE grade_levels SET sort_order = 10 WHERE id = ?')->execute([$this->otherGrade]);
        $this->pdo->exec("UPDATE grade_levels SET status = 'INACTIVE' WHERE code = 'P3'");
        $repo = new GradeLevelRepository($this->pdo);
        self::assertSame(['P1', 'P2', 'P4', 'P5', 'P6'], array_column($repo->listActive(), 'code'));
        self::assertSame('P1', $repo->findActiveById($this->grade)['code']);
        self::assertNull($repo->findActiveById(0));
        $inactive = $this->rows("SELECT id FROM grade_levels WHERE code = 'P3'")[0]['id'];
        self::assertNull($repo->findActiveById($inactive));
    }

    public function test_classroom_repository_reads_and_writes_are_tenant_scoped(): void
    {
        $a = $this->fixtureRoom($this->school, $this->year, 'Z');
        $b = $this->fixtureRoom($this->foreignSchool, $this->foreignYear, 'FOREIGN');
        $newYear = $this->fixtureYear($this->school, 2570);
        $new = $this->fixtureRoom($this->school, $newYear, 'A');
        $repo = $this->repo();
        self::assertSame([$new, $a], array_column($repo->listForSchool($this->school), 'id'));
        self::assertSame([$a], array_column($repo->listForSchool($this->school, $this->year), 'id'));
        self::assertSame([], $repo->listForSchool($this->school, $this->foreignYear));
        self::assertNull($repo->findForSchool($this->school, $b));
        self::assertNull($repo->lockForSchool($this->school, $b));
        self::assertNull($repo->findForSchool($this->school, 0));
        self::assertSame($a, $repo->findForSchool($this->school, $a)['id']);
        $before = $this->snapshot();
        $repo->update($this->school, $b, $this->otherGrade, 'ATTACK', 'Attack');
        $repo->updateStatus($this->school, $b, 'INACTIVE');
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('openYears')]
    public function test_create_defaults_active_trims_unicode_and_exact_audit(string $state): void
    {
        $this->setYearStatus($state);
        $id = $this->create(['code' => '  ป.4/1  ', 'nameTh' => '  ห้องเรียน ป.4/1  ']);
        $row = $this->room($id);
        self::assertSame([$this->school, $this->year, $this->grade, 'ป.4/1', 'ห้องเรียน ป.4/1', 'ACTIVE'],
            array_map(static fn (string $key): mixed => $row[$key], ['school_id', 'academic_year_id', 'grade_level_id', 'code', 'name_th', 'status']));
        $this->assertAudit($id, 'CLASSROOM_CREATED', null, ['academic_year_id' => $this->year,
            'grade_level_id' => $this->grade, 'code' => 'ป.4/1', 'name_th' => 'ห้องเรียน ป.4/1', 'status' => 'ACTIVE']);
    }

    public static function openYears(): array { return [['DRAFT'], ['ACTIVE']]; }

    #[DataProvider('printableValues')]
    public function test_printable_codes_and_multibyte_limits_are_accepted(string $code, string $name): void
    {
        $id = $this->create(['code' => $code, 'nameTh' => $name]);
        self::assertSame(trim($code), $this->room($id)['code']);
        self::assertSame(trim($name), $this->room($id)['name_th']);
    }

    public static function printableValues(): array
    {
        return [['P4-1', 'Primary 4/1'], ['ป4-1', 'ชื่อห้อง'], ['Primary 4/1', 'Name . / - 123'],
            [str_repeat('ก', 50), str_repeat('ข', 120)], ['é😀', 'ห้อง <b>"ทดสอบ"</b>']];
    }

    #[DataProvider('badText')]
    public function test_invalid_text_is_rejected_for_create_and_update(string $field, string $value): void
    {
        $id = $this->fixtureRoom($this->school, $this->year);
        $service = $this->service();
        $before = $this->snapshot();
        $this->deny(fn () => $this->create([$field => $value]));
        $this->deny(fn () => $service->updateClassroom($this->school, $this->actor, $id, $this->grade,
            $field === 'code' ? $value : 'P1-1', $field === 'nameTh' ? $value : 'ห้องหนึ่ง'));
        self::assertSame($before, $this->snapshot());
    }

    public static function badText(): array
    {
        $cases = [];
        foreach (['code' => 50, 'nameTh' => 120] as $field => $max) {
            foreach (['', '   ', str_repeat('ก', $max + 1), "bad\0", "bad\n", "\rbad", "bad\t", "bad\x1F", "bad\x7F", "bad\u{0085}", "\xFF"] as $i => $value) {
                $cases[$field . '-' . $i] = [$field, $value];
            }
        }
        return $cases;
    }

    public function test_duplicates_are_scoped_to_school_and_year_and_safe(): void
    {
        $this->create();
        $other = $this->fixtureYear($this->school, 2570);
        $this->create(['academicYearId' => $other]);
        $this->create(['schoolId' => $this->foreignSchool, 'academicYearId' => $this->foreignYear]);
        $target = $this->fixtureRoom($this->school, $this->year, 'OTHER');
        $before = $this->snapshot();
        self::assertSame('รหัสห้องเรียนนี้มีอยู่แล้วในปีการศึกษานี้', $this->deny(fn () => $this->create()));
        self::assertSame('รหัสห้องเรียนนี้มีอยู่แล้วในปีการศึกษานี้', $this->deny(fn () => $this->service()->updateClassroom($this->school, $this->actor, $target, $this->grade, 'P1-1', 'ห้องหนึ่ง')));
        self::assertSame($before, $this->snapshot());
    }

    public function test_foreign_missing_year_and_inactive_missing_grade_are_denied(): void
    {
        $id = $this->fixtureRoom($this->school, $this->year);
        $this->pdo->prepare("UPDATE grade_levels SET status = 'INACTIVE' WHERE id = ?")->execute([$this->otherGrade]);
        $before = $this->snapshot();
        foreach ([$this->foreignYear, 0] as $year) {
            $this->deny(fn () => $this->create(['academicYearId' => $year]));
        }
        foreach ([$this->otherGrade, 0] as $grade) {
            $this->deny(fn () => $this->create(['gradeLevelId' => $grade]));
            $this->deny(fn () => $this->service()->updateClassroom($this->school, $this->actor, $id, $grade, 'NEW', 'New'));
        }
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('openYears')]
    public function test_update_all_business_fields_preserves_identity_with_exact_audit(string $state): void
    {
        $this->setYearStatus($state);
        $id = $this->fixtureRoom($this->school, $this->year);
        $this->service()->updateClassroom($this->school, $this->actor, $id, $this->otherGrade, ' NEW ', ' ใหม่ ', '192.0.2.40');
        $row = $this->room($id);
        self::assertSame([$this->school, $this->year, $this->otherGrade, 'NEW', 'ใหม่', 'ACTIVE'],
            array_map(static fn (string $key): mixed => $row[$key], ['school_id', 'academic_year_id', 'grade_level_id', 'code', 'name_th', 'status']));
        $this->assertAudit($id, 'CLASSROOM_UPDATED', ['grade_level_id' => $this->grade, 'code' => 'P1-1', 'name_th' => 'ห้องหนึ่ง'],
            ['grade_level_id' => $this->otherGrade, 'code' => 'NEW', 'name_th' => 'ใหม่']);
    }

    public function test_update_audit_contains_only_changed_fields_and_normalized_noop_writes_nothing(): void
    {
        $id = $this->fixtureRoom($this->school, $this->year);
        $this->service()->updateClassroom($this->school, $this->actor, $id, $this->grade, 'P1-1', 'ใหม่', '192.0.2.40');
        $this->assertAudit($id, 'CLASSROOM_UPDATED', ['name_th' => 'ห้องหนึ่ง'], ['name_th' => 'ใหม่']);
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->service()->updateClassroom($this->school, $this->actor, $id, $this->grade, '  P1-1 ', ' ใหม่ ');
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('openYears')]
    public function test_status_toggle_exact_audit_and_same_state_noop(string $state): void
    {
        $this->setYearStatus($state);
        $id = $this->fixtureRoom($this->school, $this->year);
        $this->service()->changeStatus($this->school, $this->actor, $id, 'INACTIVE', '192.0.2.40');
        $this->assertAudit($id, 'CLASSROOM_STATUS_CHANGED', ['status' => 'ACTIVE'], ['status' => 'INACTIVE']);
        $this->service()->changeStatus($this->school, $this->actor, $id, 'ACTIVE');
        self::assertSame('ACTIVE', $this->room($id)['status']);
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->service()->changeStatus($this->school, $this->actor, $id, 'ACTIVE');
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
        foreach (['CLOSED', '', 'active', 'UNKNOWN'] as $status) {
            $this->deny(fn () => $this->service()->changeStatus($this->school, $this->actor, $id, $status));
        }
        self::assertSame($before, $this->snapshot());
    }

    public function test_closed_year_blocks_every_mutation_including_noops_but_keeps_reads(): void
    {
        $id = $this->fixtureRoom($this->school, $this->year);
        $this->setYearStatus('CLOSED');
        $before = $this->snapshot();
        $this->deny(fn () => $this->create(['code' => 'NEW']));
        $this->deny(fn () => $this->service()->updateClassroom($this->school, $this->actor, $id, $this->grade, 'P1-1', 'ห้องหนึ่ง'));
        $this->deny(fn () => $this->service()->changeStatus($this->school, $this->actor, $id, 'ACTIVE'));
        $this->deny(fn () => $this->service()->changeStatus($this->school, $this->actor, $id, 'INACTIVE'));
        self::assertSame($before, $this->snapshot());
        self::assertSame($id, $this->repo()->findForSchool($this->school, $id)['id']);
    }

    public function test_foreign_and_missing_classroom_fail_identically_without_writes(): void
    {
        $foreign = $this->fixtureRoom($this->foreignSchool, $this->foreignYear);
        $before = $this->snapshot();
        $messages = [];
        foreach ([$foreign, 0] as $id) {
            $messages[] = $this->deny(fn () => $this->service()->updateClassroom($this->school, $this->actor, $id, $this->grade, 'NEW', 'New'));
            $messages[] = $this->deny(fn () => $this->service()->changeStatus($this->school, $this->actor, $id, 'INACTIVE'));
        }
        self::assertCount(1, array_unique($messages));
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('failureCases')]
    public function test_write_audit_begin_commit_failures_restore_data_and_transaction(string $action, string $failure): void
    {
        $id = $this->fixtureRoom($this->school, $this->year, 'OLD');
        $service = $this->service();
        $before = $this->snapshot();
        if ($failure === 'commit') { $this->pdo->failCommit = true; }
        elseif ($failure === 'begin') { $this->pdo->failBegin = true; }
        else { $this->pdo->failPrepare = $failure === 'audit' ? 'INSERT INTO audit_logs' : ($action === 'create' ? 'INSERT INTO classrooms' : 'UPDATE classrooms'); }
        $this->deny(fn () => match ($action) {
            'create' => $service->createClassroom($this->school, $this->actor, $this->year, $this->grade, 'NEW', 'New'),
            'update' => $service->updateClassroom($this->school, $this->actor, $id, $this->otherGrade, 'NEW', 'New'),
            'status' => $service->changeStatus($this->school, $this->actor, $id, 'INACTIVE'),
        });
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

    public function test_all_mutations_lock_school_and_year_to_serialize_with_year_closure(): void
    {
        $id = $this->fixtureRoom($this->school, $this->year);
        $service = $this->service();
        foreach (['create', 'update', 'status'] as $action) {
            $this->pdo->queries = [];
            match ($action) {
                'create' => $service->createClassroom($this->school, $this->actor, $this->year, $this->grade, 'NEW', 'New'),
                'update' => $service->updateClassroom($this->school, $this->actor, $id, $this->grade, 'EDIT', 'New'),
                'status' => $service->changeStatus($this->school, $this->actor, $id, 'INACTIVE'),
            };
            $locks = array_values(array_filter($this->pdo->queries, static fn (string $q): bool => str_contains($q, 'FOR UPDATE')));
            self::assertStringContainsString('FROM schools', $locks[0]);
            self::assertTrue((bool) array_filter($locks, static fn (string $q): bool => str_contains($q, 'FROM academic_years') && str_contains($q, 'school_id = ?')));
        }
    }

    private function service(): ClassroomAdministrationService
    {
        self::assertTrue(class_exists(ClassroomAdministrationService::class), 'ClassroomAdministrationService missing');
        return new ClassroomAdministrationService($this->pdo, new SchoolRepository($this->pdo), new AcademicYearRepository($this->pdo),
            new GradeLevelRepository($this->pdo), $this->repo(), new AuditLogRepository($this->pdo));
    }

    private function repo(): ClassroomRepository
    {
        self::assertTrue(class_exists(ClassroomRepository::class), 'ClassroomRepository missing');
        return new ClassroomRepository($this->pdo);
    }

    private function create(array $overrides = []): int
    {
        return $this->service()->createClassroom(...array_replace(['schoolId' => $this->school, 'actorUserId' => $this->actor,
            'academicYearId' => $this->year, 'gradeLevelId' => $this->grade, 'code' => 'P1-1', 'nameTh' => 'ห้องหนึ่ง', 'ipAddress' => '192.0.2.40'], $overrides));
    }

    private function assertAudit(int $id, string $action, ?array $old, array $new): void
    {
        $rows = $this->rows('SELECT * FROM audit_logs ORDER BY id');
        self::assertCount(1, $rows);
        $row = $rows[0];
        self::assertSame([$this->school, $this->actor, 'classrooms', $id, $action, '192.0.2.40', null],
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
            foreach (['SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', 'uq_classroom', 'private-db-details'] as $secret) {
                self::assertStringNotContainsString($secret, $e->getMessage());
            }
            return $e->getMessage();
        }
    }

    private function fixtureYear(int $school, int $year): int
    {
        return $this->insert('INSERT INTO academic_years (school_id, year_be) VALUES (?, ?)', [$school, $year]);
    }
    private function fixtureRoom(int $school, int $year, string $code = 'P1-1'): int
    {
        return $this->insert('INSERT INTO classrooms (school_id, academic_year_id, grade_level_id, code, name_th) VALUES (?, ?, ?, ?, ?)', [$school, $year, $this->grade, $code, 'ห้องหนึ่ง']);
    }
    private function setYearStatus(string $status): void { $this->pdo->prepare('UPDATE academic_years SET status = ? WHERE id = ?')->execute([$status, $this->year]); }
    private function room(int $id): array { return $this->rows('SELECT * FROM classrooms WHERE id = ?', [$id])[0]; }
    private function snapshot(): array { return [$this->rows('SELECT * FROM classrooms ORDER BY id'), $this->rows('SELECT * FROM audit_logs ORDER BY id')]; }
    private function rows(string $sql, array $params = []): array { $q = $this->pdo->prepare($sql); $q->execute($params); return $q->fetchAll(); }
    private function insert(string $sql, array $params): int { $this->pdo->prepare($sql)->execute($params); return (int) $this->pdo->lastInsertId(); }
}

final class ClassroomDomainPDO extends PDO
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
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT classroom_domain_' . $this->depth) !== false;
        ++$this->depth;
        return $ok;
    }
    public function commit(): bool
    {
        if ($this->failCommit) { $this->failCommit = false; $this->failureTriggered = true; throw new PDOException('SQLSTATE private-db-details'); }
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT classroom_domain_' . ($this->depth - 1)) !== false;
        --$this->depth;
        return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else { $ok = $this->exec('ROLLBACK TO SAVEPOINT classroom_domain_' . ($this->depth - 1)) !== false; $this->exec('RELEASE SAVEPOINT classroom_domain_' . ($this->depth - 1)); }
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
