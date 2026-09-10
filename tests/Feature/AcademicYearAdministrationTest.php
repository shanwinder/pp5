<?php
declare(strict_types=1);

use App\Repositories\AcademicYearRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\SchoolRepository;
use App\Services\AcademicYearAdministrationService;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AcademicYearAdministrationTest extends TestCase
{
    private AcademicYearTestPDO $pdo;
    private int $schoolId;
    private int $foreignSchoolId;
    private int $actorId;
    private const IP = '192.0.2.20';

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new AcademicYearTestPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->schoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['year-domain-a', 'School A']);
        $this->foreignSchoolId = $this->insert('INSERT INTO schools (school_code, name_th) VALUES (?, ?)', ['year-domain-b', 'School B']);
        $this->actorId = $this->insert('INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)',
            ['year-domain-actor', 'unused-test-hash', 'Academic Actor']);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->serviceAborted = false;
            $this->pdo->beforeYearRead = null;
            $this->pdo->failPrepare = null;
            while ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    public function test_create_draft_in_own_school_with_exact_audit(): void
    {
        $id = $this->create();
        $row = $this->year($id);
        self::assertSame($this->schoolId, $row['school_id']);
        self::assertSame(2569, $row['year_be']);
        self::assertSame('2026-05-16', $row['start_date']);
        self::assertSame('2027-03-31', $row['end_date']);
        self::assertSame('DRAFT', $row['status']);
        self::assertSame(1, $this->pdo->depth);
        $this->assertAudit($id, 'ACADEMIC_YEAR_CREATED', null, [
            'year_be' => 2569, 'start_date' => '2026-05-16', 'end_date' => '2027-03-31', 'status' => 'DRAFT',
        ]);
    }

    #[DataProvider('draftDates')]
    public function test_draft_accepts_nullable_partial_and_real_iso_dates(?string $start, ?string $end, ?string $expectedStart, ?string $expectedEnd, int $yearBe = 2569): void
    {
        $id = $this->create(['yearBe' => $yearBe, 'startDate' => $start, 'endDate' => $end]);
        self::assertSame($expectedStart, $this->year($id)['start_date']);
        self::assertSame($expectedEnd, $this->year($id)['end_date']);
        self::assertSame('DRAFT', $this->year($id)['status']);
    }

    public static function draftDates(): array
    {
        return [
            'both null' => [null, null, null, null],
            'form feed whitespace' => [" \f\t ", null, null, null],
            'blank strings' => ['', " \t\n ", null, null],
            'start only' => ['2026-05-16', null, '2026-05-16', null],
            'end only' => [null, '2027-03-31', null, '2027-03-31'],
            'same calendar year end' => ['2026-05-16', '2026-10-01', '2026-05-16', '2026-10-01'],
            'minimum academic year dates' => ['1857-05-16', '1858-03-31', '1857-05-16', '1858-03-31', 2400],
            'maximum academic year dates' => ['2157-05-16', '2158-03-31', '2157-05-16', '2158-03-31', 2700],
            'equal dates' => ['2026-05-16', '2026-05-16', '2026-05-16', '2026-05-16'],
            'leap day' => ['2024-02-29', '2024-03-01', '2024-02-29', '2024-03-01', 2567],
        ];
    }

    #[DataProvider('yearBoundaries')]
    public function test_year_range_is_inclusive_for_create_and_update(int $year, bool $allowed): void
    {
        $target = $this->fixtureYear($this->schoolId, 2500);
        $service = $this->service();
        if ($allowed) {
            $id = $this->create(['yearBe' => $year, 'startDate' => null, 'endDate' => null]);
            self::assertSame($year, $this->year($id)['year_be']);
            $service->updateYear($this->schoolId, $this->actorId, $target, $year === 2400 ? 2700 : 2400, null, null);
            self::assertSame($year === 2400 ? 2700 : 2400, $this->year($target)['year_be']);
        } else {
            $before = $this->snapshot();
            $this->deny(fn () => $this->create(['yearBe' => $year]));
            $this->deny(fn () => $service->updateYear($this->schoolId, $this->actorId, $target, $year, null, null));
            self::assertSame($before, $this->snapshot());
        }
    }

    public static function yearBoundaries(): array
    {
        return [[2400, true], [2700, true], [2399, false], [2701, false], [0, false]];
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_dates_are_rejected_on_both_create_and_draft_update(?string $start, ?string $end): void
    {
        $target = $this->fixtureYear($this->schoolId, 2568);
        $service = $this->service();
        $before = $this->snapshot();
        $this->deny(fn () => $this->create(['startDate' => $start, 'endDate' => $end]));
        $this->deny(fn () => $service->updateYear($this->schoolId, $this->actorId, $target, 2569, $start, $end));
        self::assertSame($before, $this->snapshot());
    }

    public static function invalidDates(): array
    {
        return [
            'local date' => ['16/05/2026', null], 'unpadded date' => ['2026-5-1', null],
            'impossible start' => ['2026-02-30', null], 'impossible end' => [null, '2026-02-30'],
            'non leap year' => ['2025-02-29', null], 'Buddhist year' => ['2569-05-16', null],
            'NUL only is not blank' => ["\0", null],
            'garbage' => ['garbage', null], 'end garbage' => [null, 'garbage'],
            'reversed' => ['2026-06-01', '2026-05-31'], 'time included' => ['2026-05-16 00:00:00', null],
            'trailing newline' => ["2026-05-16\n", null], 'NUL suffix' => ["2026-05-16\0", null],
            'zero date' => ['0000-00-00', null],
            'start prior academic year' => ['2025-05-16', null],
            'start in next calendar year' => ['2027-01-01', null],
            'end prior academic year' => [null, '2025-12-31'],
            'end two years later' => [null, '2028-03-31'],
            'Buddhist end year' => [null, '2569-05-16'],
        ];
    }

    public function test_duplicate_year_is_school_scoped_and_safe_for_create_and_update(): void
    {
        $first = $this->create();
        $foreign = $this->create(['schoolId' => $this->foreignSchoolId]);
        self::assertSame(2569, $this->year($foreign)['year_be']);
        self::assertSame($this->foreignSchoolId, $this->year($foreign)['school_id']);
        $other = $this->fixtureYear($this->schoolId, 2570);
        $before = $this->snapshot();
        $this->deny(fn () => $this->create());
        $this->deny(fn () => $this->service()->updateYear($this->schoolId, $this->actorId, $other, 2569, null, null));
        self::assertSame($before, $this->snapshot());
        self::assertSame('DRAFT', $this->year($first)['status']);
    }

    #[DataProvider('detailChanges')]
    public function test_draft_updates_audit_only_changed_business_fields(int $yearBe, ?string $start, ?string $end, array $old, array $new, ?string $initialStart = '2026-05-16', ?string $initialEnd = '2027-03-31'): void
    {
        $id = $this->fixtureYear($this->schoolId, 2569, 'DRAFT', $initialStart, $initialEnd);
        $this->service()->updateYear($this->schoolId, $this->actorId, $id, $yearBe, $start, $end, self::IP);
        $row = $this->year($id);
        self::assertSame([$yearBe, $start, $end, 'DRAFT'], [$row['year_be'], $row['start_date'], $row['end_date'], $row['status']]);
        $this->assertAudit($id, 'ACADEMIC_YEAR_UPDATED', $old, $new);
        self::assertSame(1, $this->pdo->depth);
    }

    public static function detailChanges(): array
    {
        return [
            'year only with empty dates' => [2570, null, null, ['year_be' => 2569], ['year_be' => 2570], null, null],
            'start only' => [2569, '2026-05-17', '2027-03-31', ['start_date' => '2026-05-16'], ['start_date' => '2026-05-17']],
            'end only' => [2569, '2026-05-16', null, ['end_date' => '2027-03-31'], ['end_date' => null]],
            'all fields' => [2570, null, null,
                ['year_be' => 2569, 'start_date' => '2026-05-16', 'end_date' => '2027-03-31'],
                ['year_be' => 2570, 'start_date' => null, 'end_date' => null]],
        ];
    }

    #[DataProvider('immutableStates')]
    public function test_active_and_closed_details_are_immutable_even_for_same_values(string $status): void
    {
        $id = $this->fixtureYear($this->schoolId, 2569, $status);
        $before = $this->snapshot();
        $this->deny(fn () => $this->service()->updateYear($this->schoolId, $this->actorId, $id, 2570, null, null));
        $this->deny(fn () => $this->service()->updateYear($this->schoolId, $this->actorId, $id, 2569, '2026-05-16', '2027-03-31'));
        self::assertSame($before, $this->snapshot());
    }

    public static function immutableStates(): array
    {
        return [['ACTIVE'], ['CLOSED']];
    }

    public function test_normalized_draft_no_op_performs_no_write_or_audit(): void
    {
        $id = $this->fixtureYear($this->schoolId, 2569, 'DRAFT', null, null);
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->service()->updateYear($this->schoolId, $this->actorId, $id, 2569, '', " \t\n ");
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions_and_exact_status_audit(string $from, string $to): void
    {
        $id = $this->fixtureYear($this->schoolId, 2569, $from);
        $this->service()->changeStatus($this->schoolId, $this->actorId, $id, $to, self::IP);
        self::assertSame($to, $this->year($id)['status']);
        $this->assertAudit($id, 'ACADEMIC_YEAR_STATUS_CHANGED', ['status' => $from], ['status' => $to]);
        self::assertSame(1, $this->pdo->depth);
    }

    public static function allowedTransitions(): array
    {
        return [['DRAFT', 'ACTIVE'], ['ACTIVE', 'CLOSED']];
    }

    #[DataProvider('deniedTransitions')]
    public function test_invalid_transitions_leave_year_and_audit_unchanged(string $from, string $to): void
    {
        $id = $this->fixtureYear($this->schoolId, 2569, $from);
        $before = $this->snapshot();
        $this->deny(fn () => $this->service()->changeStatus($this->schoolId, $this->actorId, $id, $to));
        self::assertSame($before, $this->snapshot());
    }

    public static function deniedTransitions(): array
    {
        return [
            ['DRAFT', 'CLOSED'], ['ACTIVE', 'DRAFT'], ['CLOSED', 'DRAFT'], ['CLOSED', 'ACTIVE'],
            ['DRAFT', 'INACTIVE'], ['DRAFT', 'SUSPENDED'], ['DRAFT', 'UNKNOWN'],
            ['DRAFT', 'active'], ['DRAFT', ' ACTIVE '], ['DRAFT', ''], ['UNKNOWN', 'ACTIVE'],
        ];
    }

    #[DataProvider('sameStates')]
    public function test_same_state_is_exact_no_op_without_writes_or_audit(string $status): void
    {
        $id = $this->fixtureYear($this->schoolId, 2569, $status, null, null);
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->service()->changeStatus($this->schoolId, $this->actorId, $id, $status);
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }

    public static function sameStates(): array
    {
        return [['DRAFT'], ['ACTIVE'], ['CLOSED']];
    }

    #[DataProvider('incompleteActivationDates')]
    public function test_activation_requires_both_dates_and_valid_order(?string $start, ?string $end): void
    {
        $id = $this->fixtureYear($this->schoolId, 2569, 'DRAFT', $start, $end);
        $before = $this->snapshot();
        $this->deny(fn () => $this->service()->changeStatus($this->schoolId, $this->actorId, $id, 'ACTIVE'));
        self::assertSame($before, $this->snapshot());
    }

    public static function incompleteActivationDates(): array
    {
        return [[null, null], [null, '2027-03-31'], ['2026-05-16', null], ['2026-10-01', '2026-05-16'],
            ['2025-05-16', '2027-03-31'], ['2027-01-01', '2027-03-31'], ['2026-05-16', '2028-03-31'],
            ['2569-05-16', '2570-03-31']];
    }

    public function test_only_one_active_year_per_school_and_closed_year_releases_slot(): void
    {
        $service = $this->service();
        $first = $this->fixtureYear($this->schoolId, 2569);
        $second = $this->fixtureYear($this->schoolId, 2570);
        $foreign = $this->fixtureYear($this->foreignSchoolId, 2569);
        $service->changeStatus($this->schoolId, $this->actorId, $first, 'ACTIVE');
        $before = $this->snapshot();
        $this->deny(fn () => $service->changeStatus($this->schoolId, $this->actorId, $second, 'ACTIVE'));
        self::assertSame($before, $this->snapshot());
        $service->changeStatus($this->foreignSchoolId, $this->actorId, $foreign, 'ACTIVE');
        self::assertSame('ACTIVE', $this->year($foreign)['status']);
        $service->changeStatus($this->schoolId, $this->actorId, $first, 'CLOSED');
        $service->changeStatus($this->schoolId, $this->actorId, $second, 'ACTIVE');
        self::assertSame('CLOSED', $this->year($first)['status']);
        self::assertSame('ACTIVE', $this->year($second)['status']);
    }

    public function test_changing_academic_year_requires_correlated_dates_and_preserves_row_on_failure(): void
    {
        $service = $this->service();
        $id = $this->fixtureYear($this->schoolId, 2569);
        $before = $this->snapshot();
        $this->deny(fn () => $service->updateYear($this->schoolId, $this->actorId, $id, 2570, '2026-05-16', '2027-03-31'));
        self::assertSame($before, $this->snapshot());
        $service->updateYear($this->schoolId, $this->actorId, $id, 2570, '2027-05-16', '2028-03-31', self::IP);
        $this->assertAudit($id, 'ACADEMIC_YEAR_UPDATED',
            ['year_be' => 2569, 'start_date' => '2026-05-16', 'end_date' => '2027-03-31'],
            ['year_be' => 2570, 'start_date' => '2027-05-16', 'end_date' => '2028-03-31']);
    }

    public function test_repositories_scope_reads_locks_and_writes_by_school_and_target(): void
    {
        $repository = $this->repository();
        $first = $this->fixtureYear($this->schoolId, 2569);
        $newer = $this->fixtureYear($this->schoolId, 2570, 'CLOSED');
        $active = $this->fixtureYear($this->schoolId, 2568, 'ACTIVE');
        $foreign = $this->fixtureYear($this->foreignSchoolId, 2571, 'ACTIVE');
        self::assertSame([$newer, $first, $active], array_column($repository->listForSchool($this->schoolId), 'id'));
        self::assertSame([], $repository->listForSchool(0));
        self::assertSame($active, $repository->findActiveForSchool($this->schoolId)['id']);
        self::assertSame($foreign, $repository->findActiveForSchool($this->foreignSchoolId)['id']);
        self::assertNull($repository->findActiveForSchool(0));
        foreach ([$foreign, 0] as $id) {
            self::assertNull($repository->findForSchool($this->schoolId, $id));
            self::assertNull($repository->lockForSchool($this->schoolId, $id));
            $before = $this->snapshot();
            $repository->updateDraft($this->schoolId, $id, 2600, null, null);
            $repository->updateStatus($this->schoolId, $id, 'CLOSED');
            self::assertSame($before, $this->snapshot());
        }
        self::assertSame($first, $repository->lockForSchool($this->schoolId, $first)['id']);
        $repository->updateDraft($this->schoolId, $first, 2572, null, null);
        self::assertSame('DRAFT', $this->year($first)['status']);
        $before = $this->year($first);
        $repository->updateStatus($this->schoolId, $first, 'ACTIVE');
        $after = $this->year($first);
        foreach (['id', 'school_id', 'year_be', 'start_date', 'end_date', 'created_at'] as $field) {
            self::assertSame($before[$field], $after[$field]);
        }
        self::assertSame('ACTIVE', $after['status']);
        self::assertSame(2572, $repository->findForSchool($this->schoolId, $first)['year_be']);
    }

    public function test_repository_duplicate_create_and_update_errors_are_friendly(): void
    {
        $repository = $this->repository();
        $id = $repository->create($this->schoolId, 2569, null, null);
        self::assertSame('DRAFT', $this->year($id)['status']);
        $other = $repository->create($this->schoolId, 2570, null, null);
        $before = $this->snapshot();
        $this->deny(fn () => $repository->create($this->schoolId, 2569, null, null));
        $this->deny(fn () => $repository->updateDraft($this->schoolId, $other, 2569, null, null));
        self::assertSame($before, $this->snapshot());
    }

    public function test_foreign_and_missing_service_targets_have_same_safe_error_and_no_changes(): void
    {
        $service = $this->service();
        $foreign = $this->fixtureYear($this->foreignSchoolId, 2569);
        $before = $this->snapshot();
        foreach (['update', 'status'] as $operation) {
            $foreignError = $this->deny(fn () => $this->operate($service, $operation, $foreign));
            $missingError = $this->deny(fn () => $this->operate($service, $operation, 0));
            self::assertSame($missingError, $foreignError);
            self::assertSame($before, $this->snapshot());
        }
    }

    #[DataProvider('schoolStates')]
    public function test_school_lock_only_returns_an_active_school(string $status, bool $available): void
    {
        self::assertTrue(method_exists(SchoolRepository::class, 'lockActiveById'), 'SchoolRepository::lockActiveById is missing.');
        $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?')->execute([$status, $this->schoolId]);
        $repository = new SchoolRepository($this->pdo);
        $row = $repository->lockActiveById($this->schoolId);
        if ($available) {
            self::assertSame($this->schoolId, $row['id']);
        } else {
            self::assertNull($row);
        }
        self::assertNull($repository->lockActiveById(0));
    }

    public static function schoolStates(): array
    {
        return [['ACTIVE', true], ['SUSPENDED', false], ['INACTIVE', false]];
    }

    #[DataProvider('unavailableSchools')]
    public function test_mutations_require_an_existing_active_school(string $status): void
    {
        $service = $this->service();
        $target = $this->fixtureYear($this->schoolId, 2569);
        $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?')->execute([$status, $this->schoolId]);
        $before = $this->snapshot();
        $this->deny(fn () => $this->create(['yearBe' => 2570, 'startDate' => null, 'endDate' => null]));
        $this->deny(fn () => $service->updateYear($this->schoolId, $this->actorId, $target, 2569, null, null));
        $this->deny(fn () => $service->changeStatus($this->schoolId, $this->actorId, $target, 'ACTIVE'));
        $this->deny(fn () => $this->create(['schoolId' => 0]));
        self::assertSame($before, $this->snapshot());
    }

    public static function unavailableSchools(): array
    {
        return [['SUSPENDED'], ['INACTIVE']];
    }

    #[DataProvider('operationFailures')]
    public function test_write_audit_commit_and_transaction_failures_roll_back_atomically(string $operation, string $failure): void
    {
        $service = $this->service();
        $id = $this->fixtureYear($this->schoolId, 2568);
        $before = $this->snapshot();
        match ($failure) {
            'begin' => $this->pdo->failBegin = true,
            'commit' => $this->pdo->failCommit = true,
            'write' => $this->pdo->failPrepare = $operation === 'create' ? 'INSERT INTO academic_years' : 'UPDATE academic_years',
            default => $this->pdo->failPrepare = 'INSERT INTO audit_logs',
        };
        $this->pdo->abortOnFailure = $failure === 'database abort';
        $this->deny(fn () => $this->operate($service, $operation, $id));
        self::assertSame(1, $this->pdo->depth);
        self::assertSame($before, $this->snapshot());
    }

    public static function operationFailures(): array
    {
        $cases = [];
        foreach (['create', 'update', 'status'] as $operation) {
            foreach (['write', 'audit', 'begin', 'commit', 'database abort'] as $failure) {
                $cases[$operation . ' / ' . $failure] = [$operation, $failure];
            }
        }
        return $cases;
    }

    public function test_activation_serializes_competing_years_on_school_before_reading_target(): void
    {
        $service = $this->service();
        $first = $this->fixtureYear($this->schoolId, 2569);
        $second = $this->fixtureYear($this->schoolId, 2570);
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $contender = Database::connect($config);
        $contender->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $otherService = new AcademicYearAdministrationService($contender, new SchoolRepository($contender),
            new AcademicYearRepository($contender), new AuditLogRepository($contender));
        // The second connection must see committed fixtures; cleanup below owns only these IDs.
        $this->pdo->commit();
        try {
            $blocked = false;
            $this->pdo->beforeYearRead = function () use ($otherService, $second, &$blocked): void {
                try {
                    $otherService->changeStatus($this->schoolId, $this->actorId, $second, 'ACTIVE');
                } catch (DomainException) {
                    $blocked = true;
                }
            };
            $service->changeStatus($this->schoolId, $this->actorId, $first, 'ACTIVE', self::IP);
            self::assertTrue($blocked, 'Competing activation must wait on the school lock before the first target is read.');
            self::assertFalse($contender->inTransaction());
            self::assertFalse($this->pdo->inTransaction());
            self::assertSame('ACTIVE', $this->year($first)['status']);
            self::assertSame('DRAFT', $this->year($second)['status']);
            $this->deny(fn () => $otherService->changeStatus($this->schoolId, $this->actorId, $second, 'ACTIVE'));
            self::assertSame(1, (int) $contender->query("SELECT COUNT(*) FROM academic_years WHERE school_id = {$this->schoolId} AND status = 'ACTIVE'")->fetchColumn());
            $this->assertAudit($first, 'ACADEMIC_YEAR_STATUS_CHANGED', ['status' => 'DRAFT'], ['status' => 'ACTIVE']);
        } finally {
            $this->pdo->beforeYearRead = null;
            if ($contender->inTransaction()) {
                $contender->rollBack();
            }
            while ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->pdo->beginTransaction();
            try {
                $this->pdo->prepare('DELETE FROM audit_logs WHERE user_id = ? AND school_id IN (?, ?)')->execute([$this->actorId, $this->schoolId, $this->foreignSchoolId]);
                $this->pdo->prepare('DELETE FROM academic_years WHERE school_id IN (?, ?)')->execute([$this->schoolId, $this->foreignSchoolId]);
                $this->pdo->prepare('DELETE FROM schools WHERE id IN (?, ?)')->execute([$this->schoolId, $this->foreignSchoolId]);
                $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$this->actorId]);
                $this->pdo->commit();
            } catch (Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $exception;
            }
        }
    }

    private function service(): AcademicYearAdministrationService
    {
        self::assertTrue(class_exists(AcademicYearAdministrationService::class), 'AcademicYearAdministrationService is missing.');
        return new AcademicYearAdministrationService($this->pdo, new SchoolRepository($this->pdo),
            $this->repository(), new AuditLogRepository($this->pdo));
    }

    private function repository(): AcademicYearRepository
    {
        self::assertTrue(class_exists(AcademicYearRepository::class), 'AcademicYearRepository is missing.');
        return new AcademicYearRepository($this->pdo);
    }

    private function create(array $overrides = []): int
    {
        return $this->service()->createYear(...array_replace([
            'schoolId' => $this->schoolId, 'actorUserId' => $this->actorId, 'yearBe' => 2569,
            'startDate' => '2026-05-16', 'endDate' => '2027-03-31', 'ipAddress' => self::IP,
        ], $overrides));
    }

    private function operate(AcademicYearAdministrationService $service, string $operation, int $id): void
    {
        match ($operation) {
            'create' => $service->createYear($this->schoolId, $this->actorId, 2569, '2026-05-16', '2027-03-31', self::IP),
            'update' => $service->updateYear($this->schoolId, $this->actorId, $id, 2570, '2027-05-16', '2028-03-31', self::IP),
            'status' => $service->changeStatus($this->schoolId, $this->actorId, $id, 'ACTIVE', self::IP),
        };
    }

    private function deny(callable $operation): string
    {
        try {
            $operation();
            self::fail('Expected a friendly DomainException.');
        } catch (DomainException $exception) {
            self::assertNotSame('', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            foreach (['SQLSTATE', 'PDOException', 'private-db-details', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', 'uq_academic_year'] as $unsafe) {
                self::assertStringNotContainsString($unsafe, $exception->getMessage());
            }
            return $exception->getMessage();
        } catch (PDOException) {
            self::fail('Raw PDOException must not escape the domain boundary.');
        }
    }

    private function assertAudit(int $id, string $action, ?array $old, array $new): void
    {
        $rows = $this->rows('SELECT * FROM audit_logs WHERE school_id = ? ORDER BY id', [$this->schoolId]);
        self::assertCount(1, $rows);
        $row = $rows[0];
        self::assertSame($this->schoolId, $row['school_id']);
        self::assertSame($this->actorId, $row['user_id']);
        self::assertSame($id, $row['entity_id']);
        self::assertSame('academic_years', $row['entity_type']);
        self::assertSame($action, $row['action']);
        self::assertSame(self::IP, $row['ip_address']);
        self::assertNull($row['reason']);
        self::assertNotEmpty($row['created_at']);
        self::assertSame($old, $row['old_value'] === null ? null : json_decode($row['old_value'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame($new, json_decode($row['new_value'], true, 512, JSON_THROW_ON_ERROR));
    }

    private function fixtureYear(int $schoolId, int $year, string $status = 'DRAFT', ?string $start = 'auto', ?string $end = 'auto'): int
    {
        return $this->insert('INSERT INTO academic_years (school_id, year_be, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)',
            [$schoolId, $year, $start === 'auto' ? ($year - 543) . '-05-16' : $start,
                $end === 'auto' ? ($year - 542) . '-03-31' : $end, $status]);
    }

    private function year(int $id): array
    {
        return $this->rows('SELECT * FROM academic_years WHERE id = ?', [$id])[0];
    }

    private function snapshot(): array
    {
        return ['years' => $this->rows('SELECT * FROM academic_years ORDER BY id'),
            'audit' => $this->rows('SELECT * FROM audit_logs ORDER BY id')];
    }

    private function rows(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    private function insert(string $sql, array $parameters): int
    {
        $this->pdo->prepare($sql)->execute($parameters);
        return (int) $this->pdo->lastInsertId();
    }
}

/** Real MySQL savepoints keep service commits within each test's fixture rollback. */
final class AcademicYearTestPDO extends PDO
{
    public int $depth = 0;
    public int $writeAttempts = 0;
    public bool $failBegin = false;
    public bool $failCommit = false;
    public bool $abortOnFailure = false;
    public bool $serviceAborted = false;
    public ?string $failPrepare = null;
    public ?Closure $beforeYearRead = null;

    public function beginTransaction(): bool
    {
        if ($this->failBegin) {
            $this->failBegin = false;
            throw new PDOException('SQLSTATE private-db-details');
        }
        $result = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT academic_year_' . $this->depth) !== false;
        ++$this->depth;
        return $result;
    }

    public function commit(): bool
    {
        if ($this->failCommit) {
            $this->failCommit = false;
            throw new PDOException('SQLSTATE private-db-details');
        }
        $result = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT academic_year_' . ($this->depth - 1)) !== false;
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
            $result = $this->exec('ROLLBACK TO SAVEPOINT academic_year_' . ($this->depth - 1)) !== false;
            $this->exec('RELEASE SAVEPOINT academic_year_' . ($this->depth - 1));
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
        if ($this->beforeYearRead !== null && preg_match('/\bFROM\s+academic_years\b/i', $query)) {
            $callback = $this->beforeYearRead;
            $this->beforeYearRead = null;
            $callback();
        }
        if ($this->failPrepare !== null && str_contains($query, $this->failPrepare)) {
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
