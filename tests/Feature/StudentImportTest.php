<?php
declare(strict_types=1);

use App\Support\CanonicalStudentCsvReader;
use App\Validation\StudentProfileRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Shared real database fixtures for the import domain and HTTP tests. */
abstract class StudentImportFixtureTestCase extends TestCase
{
    protected StudentImportPDO $pdo;
    protected int $school;
    protected int $foreign;
    protected int $year;
    protected int $nextYear;
    protected int $foreignYear;
    protected int $grade;
    protected int $otherGrade;
    protected int $room;
    protected int $user;
    protected int $otherUser;
    protected array $files = [];

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__,2) . '/htdocs/config/database.php'; $config['database'] = 'pp5_test';
        $this->pdo = new StudentImportPDO(App\Support\Database::dsn($config),$config['username'],$config['password'],[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        $this->pdo->beginTransaction();
        $this->school = $this->insert('INSERT INTO schools (school_code,name_th) VALUES (?,?)',['import-a','Import school A']);
        $this->foreign = $this->insert('INSERT INTO schools (school_code,name_th) VALUES (?,?)',['import-b','FOREIGN_SECRET_SCHOOL']);
        $this->user = $this->insert('INSERT INTO users (username,password_hash,display_name) VALUES (?,?,?)',['import-user','unused','Import user']);
        $this->otherUser = $this->insert('INSERT INTO users (username,password_hash,display_name) VALUES (?,?,?)',['import-other','unused','FOREIGN_SECRET_USER']);
        $membership = $this->insert('INSERT INTO school_memberships (school_id,user_id) VALUES (?,?)',[$this->school,$this->user]);
        $this->insert("INSERT INTO user_role_assignments (school_id,user_id,role_id) SELECT ?,?,id FROM roles WHERE code = 'SCHOOL_ADMIN'",[$this->school,$this->user]);
        $this->year = $this->insert("INSERT INTO academic_years (school_id,year_be,status,start_date,end_date) VALUES (?,2569,'ACTIVE','2026-05-01','2027-03-31')",[$this->school]);
        $this->nextYear = $this->insert('INSERT INTO academic_years (school_id,year_be) VALUES (?,2570)',[$this->school]);
        $this->foreignYear = $this->insert('INSERT INTO academic_years (school_id,year_be) VALUES (?,2569)',[$this->foreign]);
        $this->grade = (int)$this->pdo->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        $this->otherGrade = (int)$this->pdo->query("SELECT id FROM grade_levels WHERE code='P2'")->fetchColumn();
        $rooms = new App\Repositories\ClassroomRepository($this->pdo);
        $this->room = $rooms->create($this->school,$this->year,$this->grade,'A','Room A');
        $rooms->create($this->school,$this->year,$this->otherGrade,'GRADE2','Other grade');
        $rooms->create($this->school,$this->nextYear,$this->grade,'NEXT','Next year');
        $rooms->create($this->foreign,$this->foreignYear,$this->grade,'FOREIGN_SECRET_ROOM','FOREIGN_SECRET_ROOM');
        $_SESSION = ['user_id'=>$this->user,'school_id'=>$this->school,'school_membership_id'=>$membership,'context_type'=>'SCHOOL'];
        (new App\Support\Csrf())->token(new App\Http\Session());
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) { $this->pdo->failSql=null; while ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } }
        foreach ($this->files as $file) { unlink($file); } $this->files=[]; $_SESSION=[];
    }

    protected function service(): App\Services\StudentImportService
    {
        self::assertTrue(class_exists(App\Services\StudentImportService::class),'Student import domain is missing');
        return new App\Services\StudentImportService($this->pdo,new App\Repositories\SchoolRepository($this->pdo),
            new App\Repositories\AcademicYearRepository($this->pdo),new App\Repositories\StudentRepository($this->pdo),
            new App\Repositories\GradeLevelRepository($this->pdo),new App\Repositories\ClassroomRepository($this->pdo),
            new App\Repositories\StudentEnrollmentRepository($this->pdo),new App\Repositories\StudentClassroomPlacementRepository($this->pdo),
            new App\Repositories\StudentImportBatchRepository($this->pdo),new App\Repositories\StudentImportRowRepository($this->pdo),new App\Repositories\AuditLogRepository($this->pdo));
    }
    protected function row(array $overrides=[]): array
    {
        return array_replace(['row_no'=>2,'student_code'=>'S1','national_id'=>'1234567890123','prefix_th'=>'ด.ช.',
            'first_name_th'=>'สมชาย','last_name_th'=>'ทดสอบ','gender_code'=>'MALE','birth_date'=>'2018-01-01',
            'grade_level_code'=>'P1','classroom_code'=>'A','entry_date'=>'2026-05-01'],$overrides);
    }
    protected function profile(array $row): array
    {
        return StudentProfileRules::normalize(...array_values(array_intersect_key($row,array_flip(['student_code','national_id','prefix_th','first_name_th','last_name_th','gender_code','birth_date']))));
    }
    protected function makeStudent(array $row): int { return (new App\Repositories\StudentRepository($this->pdo))->create($this->school,$this->profile($row)); }
    protected function makeEnrollment(int $student,int $grade,?int $room): int
    {
        $id=(new App\Repositories\StudentEnrollmentRepository($this->pdo))->create($this->school,$this->year,$student,$grade,'2026-05-01');
        if ($room!==null) { (new App\Repositories\StudentClassroomPlacementRepository($this->pdo))->create($this->school,$this->year,$grade,$id,$room); }
        return $id;
    }
    protected function preview(array $rows): int { return $this->service()->preview($this->school,$this->user,$this->year,'file.csv',hash('sha256','fixture'),$rows); }
    protected function batch(int $id): array { return (new App\Repositories\StudentImportBatchRepository($this->pdo))->findForSchool($this->school,$id); }
    protected function staging(int $id): array { return (new App\Repositories\StudentImportRowRepository($this->pdo))->listForBatch($this->school,$id); }
    protected function snapshot(bool $staging=false): array
    {
        $result=[];
        foreach (array_merge(['students','student_enrollments','student_classroom_placements','audit_logs'],$staging?['student_import_batches','student_import_rows']:[]) as $table) {
            $result[$table]=$this->pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll();
        }
        return $result;
    }
    protected function insert(string $sql,array $params): int { $this->pdo->prepare($sql)->execute($params);return (int)$this->pdo->lastInsertId(); }
    protected function denied(callable $call): void
    {
        try { $call(); self::fail('Expected safe DomainException'); }
        catch (DomainException $exception) { self::assertNotSame('',$exception->getMessage()); $this->safe($exception->getMessage()); }
    }
    protected function safe(string $text): void
    {
        foreach (['1234567890123','1111111111111','2222222222222','987654321012345','FOREIGN_SECRET','PDOException','SQLSTATE','fk_student','uq_student','Stack trace','/Applications/','/private/','htdocs/app/'] as $secret) {
            self::assertStringNotContainsString($secret,$text);
        }
    }
}

final class StudentImportPDO extends PDO
{
    private int $depth=0;
    public ?string $failSql=null;
    public int $failOccurrence=1;
    public int $matchedStatements=0;
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        if ($this->failSql!==null && str_contains($query,$this->failSql) && ++$this->matchedStatements === $this->failOccurrence) { throw new PDOException('SQLSTATE injected fk_student /private/path 1234567890123'); }
        return parent::prepare($query,$options);
    }
    public function beginTransaction(): bool { $ok=$this->depth===0?parent::beginTransaction():$this->exec('SAVEPOINT import_test_'.$this->depth)!==false; ++$this->depth; return $ok; }
    public function commit(): bool { $ok=$this->depth===1?parent::commit():$this->exec('RELEASE SAVEPOINT import_test_'.($this->depth-1))!==false; --$this->depth; return $ok; }
    public function rollBack(): bool
    {
        if ($this->depth===1) { $ok=parent::rollBack(); }
        else { $ok=$this->exec('ROLLBACK TO SAVEPOINT import_test_'.($this->depth-1))!==false; $this->exec('RELEASE SAVEPOINT import_test_'.($this->depth-1)); }
        --$this->depth; return $ok;
    }
}

final class StudentImportTest extends StudentImportFixtureTestCase
{
    private const HEADER = 'student_code,national_id,prefix_th,first_name_th,last_name_th,gender_code,birth_date,grade_level_code,classroom_code,entry_date';

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    #[DataProvider('bomCases')]
    public function test_csv_preserves_values_and_source_row_numbers(string $bom): void
    {
        self::assertTrue(class_exists(CanonicalStudentCsvReader::class), 'Canonical CSV reader is missing');
        $path = $this->csv($bom . self::HEADER . "\r\nS1,,ด.ช.,\"ชื่อ,หนึ่ง\",สกุล,,,P1,,\r\nS2,,ด.ญ.,สอง,สกุล,,,P1,,\r\n");
        $rows = (new CanonicalStudentCsvReader())->read($path, filesize($path));
        self::assertCount(2, $rows);
        self::assertSame([2, 3], array_column($rows, 'row_no'));
        self::assertSame('ชื่อ,หนึ่ง', $rows[0]['first_name_th']);
        self::assertSame('', $rows[0]['national_id']);
    }

    public static function bomCases(): array { return [[''], ["\xEF\xBB\xBF"]]; }

    #[DataProvider('badCsvCases')]
    public function test_invalid_csv_is_a_safe_domain_error(string $case): void
    {
        self::assertTrue(class_exists(CanonicalStudentCsvReader::class), 'Canonical CSV reader is missing');
        $content = match ($case) {
            'empty' => '', 'missing header' => 'S1,,Mr,Name,Surname,,,P1,,',
            'wrong header' => str_replace('student_code', 'code', self::HEADER),
            'extra header' => self::HEADER . ',extra', 'header only' => self::HEADER,
            '1001 rows' => self::HEADER . "\n" . str_repeat("S1,,Mr,Name,Surname,,,P1,,\n", 1001),
            'invalid utf8' => self::HEADER . "\nS1,,Mr,\xFF,Surname,,,P1,,",
            'unterminated quote' => self::HEADER . "\nS1,,Mr,\"unclosed,Surname,,,P1,,",
            'stray quote' => self::HEADER . "\nS1,,Mr,na\"me,Surname,,,P1,,",
            'wrong field count' => self::HEADER . "\nS1,Mr,Name",
            'oversize actual' => str_repeat('x', 2097153),
            default => self::HEADER . "\nS1,,Mr,Name,Surname,,,P1,,",
        };
        $path = $this->csv($content);
        try {
            (new CanonicalStudentCsvReader())->read($case === 'missing file' ? $path . '.missing' : $path,
                $case === 'oversize declared' ? 2097153 : ($case === 'oversize actual' ? 1 : strlen($content)));
            self::fail('Invalid CSV accepted: ' . $case);
        } catch (DomainException $exception) {
            self::assertNotSame('', $exception->getMessage());
            self::assertStringNotContainsString($path, $exception->getMessage());
            self::assertStringNotContainsString('unclosed', $exception->getMessage());
        }
    }

    public static function badCsvCases(): array
    {
        return array_map(static fn (string $case): array => [$case], ['empty', 'missing header', 'wrong header', 'extra header',
            'header only', '1001 rows', 'invalid utf8', 'unterminated quote', 'stray quote', 'wrong field count', 'oversize declared', 'oversize actual', 'missing file']);
    }

    public function test_csv_accepts_1000_rows_and_downstream_rejects_embedded_controls(): void
    {
        self::assertTrue(class_exists(CanonicalStudentCsvReader::class), 'Canonical CSV reader is missing');
        $path = $this->csv(self::HEADER . "\n" . str_repeat("S1,,Mr,Name,Surname,,,P1,,\n", 1000));
        self::assertCount(1000, (new CanonicalStudentCsvReader())->read($path, filesize($path)));
        $path = $this->csv(self::HEADER . "\nS1,,Mr,\"Name\nBad\",Surname,,,P1,,");
        $row = (new CanonicalStudentCsvReader())->read($path, filesize($path))[0];
        $this->expectException(DomainException::class);
        StudentProfileRules::normalize($row['student_code'], $row['national_id'], $row['prefix_th'], $row['first_name_th'],
            $row['last_name_th'], $row['gender_code'], $row['birth_date']);
    }

    #[DataProvider('classificationCases')]
    public function test_preview_classification_is_staging_only(string $case, string $studentAction, string $enrollmentAction, ?string $error): void
    {
        $service = $this->service();
        $row = $this->row();
        if (in_array($case, ['match', 'noop', 'national code mismatch', 'national conflict', 'split identity', 'profile conflict', 'inactive student', 'enrollment grade', 'enrollment classroom', 'terminal'], true)) {
            $student = $this->makeStudent($row);
            if ($case === 'national code mismatch') { $row['student_code'] = 'OTHER'; }
            if ($case === 'national conflict') { $row['national_id'] = '1111111111111'; }
            if ($case === 'split identity') { $this->makeStudent($this->row(['student_code' => 'SECOND', 'national_id' => '1111111111111'])); $row['national_id'] = '1111111111111'; }
            if ($case === 'profile conflict') { $row['first_name_th'] = 'Changed'; }
            if ($case === 'inactive student') { $this->pdo->prepare("UPDATE students SET status = 'INACTIVE' WHERE id = ?")->execute([$student]); }
            if (in_array($case, ['noop', 'enrollment grade', 'enrollment classroom', 'terminal'], true)) {
                $enrollment = $this->makeEnrollment($student, $case === 'enrollment grade' ? $this->otherGrade : $this->grade,
                    $case === 'enrollment classroom' || $case === 'enrollment grade' ? null : $this->room);
                if ($case === 'terminal') { $this->pdo->prepare("UPDATE student_enrollments SET status = 'WITHDRAWN', exit_date = '2026-09-01' WHERE id = ?")->execute([$enrollment]); }
            }
        }
        if ($case === 'unknown grade') { $row['grade_level_code'] = 'UNKNOWN'; }
        if ($case === 'inactive grade') { $this->pdo->prepare("UPDATE grade_levels SET status = 'INACTIVE' WHERE id = ?")->execute([$this->grade]); }
        if ($case === 'foreign classroom') { $row['classroom_code'] = 'FOREIGN_SECRET_ROOM'; }
        if ($case === 'missing classroom') { $row['classroom_code'] = 'MISSING'; }
        if ($case === 'wrong year') { $row['classroom_code'] = 'NEXT'; }
        if ($case === 'wrong grade') { $row['classroom_code'] = 'GRADE2'; }
        if ($case === 'inactive classroom') { $this->pdo->prepare("UPDATE classrooms SET status = 'INACTIVE' WHERE id = ?")->execute([$this->room]); }
        if ($case === 'control character') { $row['first_name_th'] = "Name\tBad"; }
        if ($case === 'invalid national') { $row['national_id'] = '987654321012345'; }
        if ($case === 'oversize name') { $row['first_name_th'] = str_repeat('ก', 101); }
        if ($case === 'invalid date') { $row['entry_date'] = '2026-02-30'; }
        if ($case === 'date outside year') { $row['entry_date'] = '2028-05-01'; }
        $before = $this->snapshot();
        $batch = $this->preview([$row]);
        self::assertSame($before, $this->snapshot());
        $staged = $this->staging($batch);
        self::assertCount(1, $staged);
        self::assertSame($studentAction, $staged[0]['student_action']);
        self::assertSame($enrollmentAction, $staged[0]['enrollment_action']);
        self::assertSame($error, $staged[0]['error_code']);
        self::assertSame($error === null ? 0 : 1, $this->batch($batch)['error_count']);
        $this->safe((string) $staged[0]['error_message']);
        if ($error !== null) {
            $before = $this->snapshot(true);
            $this->denied(fn () => $service->apply($this->school, $this->user, $batch));
            self::assertSame($before, $this->snapshot(true));
        }
    }

    public static function classificationCases(): array
    {
        $cases = ['new' => ['CREATE','CREATE',null], 'match' => ['MATCH','CREATE',null], 'noop' => ['MATCH','NOOP',null]];
        foreach (['national code mismatch','national conflict','split identity','profile conflict','enrollment grade','enrollment classroom','terminal'] as $case) { $cases[$case] = ['NONE','NONE','CONFLICT']; }
        foreach (['inactive student','unknown grade','inactive grade','foreign classroom','missing classroom','wrong year','wrong grade','inactive classroom','control character','invalid national','oversize name','invalid date','date outside year'] as $case) { $cases[$case] = ['NONE','NONE','ERROR']; }
        return array_map(static fn (string $case, array $result): array => [$case, ...$result], array_keys($cases), array_values($cases));
    }

    #[DataProvider('duplicateFields')]
    public function test_preview_marks_duplicate_rows_as_errors(string $field): void
    {
        $this->service();
        $first = $this->row();
        $second = $this->row(['row_no' => 3, 'student_code' => 'SECOND', 'national_id' => '1111111111111']);
        $second[$field] = $first[$field];
        $before = $this->snapshot();
        $batch = $this->preview([$second, $first]);
        self::assertSame($before, $this->snapshot());
        self::assertSame([2,3], array_column($this->staging($batch), 'row_no'));
        self::assertSame(['ERROR','ERROR'], array_column($this->staging($batch), 'error_code'));
        self::assertSame(2, $this->batch($batch)['error_count']);
    }

    public static function duplicateFields(): array { return [['student_code'], ['national_id']]; }

    #[DataProvider('equivalentStudentCodes')]
    public function test_preview_rejects_codes_equivalent_under_student_unique_key(string $firstCode, string $secondCode): void
    {
        $first = $this->row(['student_code' => $firstCode, 'national_id' => null]);
        $second = $this->row(['row_no' => 3, 'student_code' => $secondCode, 'national_id' => null]);
        // Prove that this pair collides under the actual student identity constraint.
        $this->pdo->beginTransaction();
        $this->makeStudent($first);
        $this->denied(fn () => $this->makeStudent($second));
        $this->pdo->rollBack();

        $before = $this->snapshot();
        $batch = $this->preview([$first, $second, $this->row(['row_no' => 4, 'student_code' => 'DISTINCT', 'national_id' => null])]);
        self::assertSame($before, $this->snapshot());
        self::assertSame(['ERROR', 'ERROR', null], array_column($this->staging($batch), 'error_code'));
        self::assertSame(2, $this->batch($batch)['error_count']);
        self::assertSame(1, $this->batch($batch)['create_student_count']);
        $before = $this->snapshot(true);
        $this->denied(fn () => $this->service()->apply($this->school, $this->user, $batch));
        self::assertSame($before, $this->snapshot(true));
    }

    public static function equivalentStudentCodes(): array
    {
        return [
            'case' => ['CASE_DUP', 'case_dup'],
            'accent' => ['eleve', 'élève'],
            'Unicode composition' => ['é1', "e\u{0301}1"],
            'collation expansion' => ['STRASSE', 'Straße'],
        ];
    }

    public function test_code_comparison_handles_full_csv_limit_and_nonconsecutive_indexes(): void
    {
        $codes = [];
        for ($index = 0; $index < 1000; ++$index) {
            $codes[$index * 2] = 'นักเรียน-' . $index;
        }
        $repository = new App\Repositories\StudentRepository($this->pdo);
        self::assertSame([], $repository->duplicateCodeIndexes($codes));
        $codes[0] = 'DUP';
        $codes[1000] = 'dup';
        $codes[1998] = 'Dúp';
        self::assertSame([0, 1000, 1998], $repository->duplicateCodeIndexes($codes));
    }

    #[DataProvider('codeComparisonFailurePhases')]
    public function test_code_comparison_failure_preserves_business_and_staging(string $phase): void
    {
        $rows = [$this->row(), $this->row(['row_no' => 3, 'student_code' => 'SECOND', 'national_id' => null])];
        $batch = $phase === 'apply' ? $this->preview($rows) : null;
        $before = $this->snapshot(true);
        $this->pdo->failSql = 'COUNT(*) OVER';
        $this->denied(fn () => $phase === 'apply'
            ? $this->service()->apply($this->school, $this->user, $batch)
            : $this->preview($rows));
        $this->pdo->failSql = null;
        self::assertSame($before, $this->snapshot(true));
    }

    public static function codeComparisonFailurePhases(): array { return [['preview'], ['apply']]; }

    public function test_apply_creates_only_missing_entities_with_safe_audits_and_deletes_staging(): void
    {
        $service = $this->service();
        $existing = $this->row(['row_no' => 3, 'student_code' => 'MATCH', 'national_id' => '1111111111111']);
        $noop = $this->row(['row_no' => 4, 'student_code' => 'NOOP', 'national_id' => '2222222222222']);
        $this->makeStudent($existing);
        $this->makeEnrollment($this->makeStudent($noop), $this->grade, $this->room);
        $before = $this->snapshot();
        $batch = $this->preview([$noop, $this->row(), $existing]);
        self::assertSame($before, $this->snapshot());
        self::assertSame(['CREATE','MATCH','MATCH'], array_column($this->staging($batch), 'student_action'));
        $service->apply($this->school, $this->user, $batch, '127.0.0.1');
        $after = $this->snapshot();
        self::assertCount(count($before['students'])+1, $after['students']);
        self::assertCount(count($before['student_enrollments'])+2, $after['student_enrollments']);
        self::assertCount(count($before['student_classroom_placements'])+2, $after['student_classroom_placements']);
        self::assertSame('APPLIED', $this->batch($batch)['status']);
        self::assertNotNull($this->batch($batch)['applied_at']);
        self::assertSame([], $this->staging($batch));
        $audits = array_slice($after['audit_logs'], count($before['audit_logs']));
        self::assertSame(['STUDENT_CREATED','STUDENT_ENROLLMENT_CREATED','STUDENT_CLASSROOM_PLACEMENT_CHANGED',
            'STUDENT_ENROLLMENT_CREATED','STUDENT_CLASSROOM_PLACEMENT_CHANGED','STUDENT_IMPORT_APPLIED'], array_column($audits,'action'));
        foreach ($audits as $audit) {
            self::assertSame($this->school, $audit['school_id']); self::assertSame($this->user, $audit['user_id']);
            self::assertSame('127.0.0.1', $audit['ip_address']); $this->safe(json_encode($audit, JSON_THROW_ON_ERROR));
        }
        self::assertSame(['row_count'=>3,'created_students'=>1,'created_enrollments'=>2,'created_placements'=>2,'noop_rows'=>1,'source_sha256'=>hash('sha256','fixture')],
            json_decode($audits[5]['new_value'], true, 512, JSON_THROW_ON_ERROR));
        $before = $this->snapshot(true);
        $this->denied(fn () => $service->apply($this->school,$this->user,$batch));
        $this->denied(fn () => $service->cancel($this->school,$this->user,$batch));
        $this->denied(fn () => $this->preview([$this->row()]));
        self::assertSame($before, $this->snapshot(true));
    }

    public function test_noop_only_apply_is_denied_and_profiles_are_never_updated(): void
    {
        $service = $this->service(); $this->makeEnrollment($this->makeStudent($this->row()), $this->grade, $this->room);
        $batch = $this->preview([$this->row()]); $before = $this->snapshot(true);
        $this->denied(fn () => $service->apply($this->school,$this->user,$batch));
        self::assertSame($before,$this->snapshot(true));
    }

    #[DataProvider('staleCases')]
    public function test_apply_revalidates_every_decision_and_rolls_back_stale_conflicts(string $case): void
    {
        $service = $this->service();
        $row = $this->row(['row_no'=>3,'student_code'=>'STALE','national_id'=>'1111111111111']);
        if ($case === 'enrollment grade') { $row['classroom_code'] = ''; }
        $student = $this->makeStudent($row);
        if (in_array($case, ['placement','terminal','enrollment grade'], true)) { $enrollment = $this->makeEnrollment($student,$this->grade,$case === 'enrollment grade' ? null : $this->room); }
        $batch = $this->preview([$this->row(),$row]);
        match ($case) {
            'profile' => $this->pdo->prepare('UPDATE students SET first_name_th = ? WHERE id = ?')->execute(['Changed',$student]),
            'identity' => $this->pdo->prepare('UPDATE students SET national_id = ? WHERE id = ?')->execute(['3333333333333',$student]),
            'student inactive' => $this->pdo->prepare("UPDATE students SET status = 'INACTIVE' WHERE id = ?")->execute([$student]),
            'grade' => $this->pdo->prepare("UPDATE grade_levels SET status = 'INACTIVE' WHERE id = ?")->execute([$this->grade]),
            'classroom' => $this->pdo->prepare("UPDATE classrooms SET status = 'INACTIVE' WHERE id = ?")->execute([$this->room]),
            'year' => $this->pdo->prepare("UPDATE academic_years SET status = 'CLOSED' WHERE id = ?")->execute([$this->year]),
            'placement' => $this->pdo->prepare("UPDATE student_classroom_placements SET status = 'ENDED' WHERE enrollment_id = ?")->execute([$enrollment]),
            'terminal' => $this->pdo->prepare("UPDATE student_enrollments SET status = 'WITHDRAWN' WHERE id = ?")->execute([$enrollment]),
            'enrollment grade' => $this->pdo->prepare('UPDATE student_enrollments SET grade_level_id = ? WHERE id = ?')->execute([$this->otherGrade,$enrollment]),
        };
        $before = $this->snapshot(true);
        $this->denied(fn () => $service->apply($this->school,$this->user,$batch));
        self::assertSame($before,$this->snapshot(true));
    }

    public static function staleCases(): array { return array_map(static fn ($case) => [$case], ['profile','identity','student inactive','grade','classroom','year','placement','terminal','enrollment grade']); }

    #[DataProvider('failurePoints')]
    public function test_any_apply_write_failure_rolls_back_business_audit_batch_and_rows(string $sql): void
    {
        $service = $this->service(); $batch = $this->preview([$this->row()]); $before = $this->snapshot(true);
        $this->pdo->failSql = $sql;
        $this->denied(fn () => $service->apply($this->school,$this->user,$batch));
        $this->pdo->failSql = null;
        self::assertSame($before,$this->snapshot(true));
        self::assertSame('PREVIEW',$this->batch($batch)['status']); self::assertCount(1,$this->staging($batch));
    }

    public static function failurePoints(): array
    {
        return [['INSERT INTO students'],['INSERT INTO student_enrollments'],['INSERT INTO student_classroom_placements'],
            ['INSERT INTO audit_logs'],["status = 'APPLIED'"],['DELETE FROM student_import_rows']];
    }

    public function test_hash_is_scoped_to_school_and_year_and_pending_duplicate_is_rechecked_at_apply(): void
    {
        $service = $this->service(); $first = $this->preview([$this->row()]); $second = $this->preview([$this->row()]);
        $service->apply($this->school,$this->user,$first); $before = $this->snapshot(true);
        $this->denied(fn () => $service->apply($this->school,$this->user,$second)); self::assertSame($before,$this->snapshot(true));
        $id = $service->preview($this->school,$this->user,$this->nextYear,'file.csv',hash('sha256','fixture'),[$this->row(['classroom_code'=>''])]);
        self::assertSame('PREVIEW',$this->batch($id)['status']);
        $id = $service->preview($this->foreign,$this->otherUser,$this->foreignYear,'file.csv',hash('sha256','fixture'),[$this->row(['classroom_code'=>''])]);
        self::assertSame($this->foreign,(new App\Repositories\StudentImportBatchRepository($this->pdo))->findForSchool($this->foreign,$id)['school_id']);
    }

    public function test_closed_or_foreign_year_preview_is_denied_without_staging_or_business_writes(): void
    {
        $service = $this->service();
        $this->pdo->prepare("UPDATE academic_years SET status = 'CLOSED' WHERE id = ?")->execute([$this->year]);
        foreach ([$this->year,$this->foreignYear,999999999] as $year) {
            $before = $this->snapshot(true);
            $this->denied(fn () => $service->preview($this->school,$this->user,$year,'file.csv',hash('sha256','fixture'),[$this->row()]));
            self::assertSame($before,$this->snapshot(true));
        }
    }

    public function test_cancel_and_expiry_delete_only_own_preview_staging_without_audits(): void
    {
        $service = $this->service(); $batch = $this->preview([$this->row()]); $before = $this->snapshot();
        $service->cancel($this->school,$this->user,$batch);
        self::assertSame('CANCELLED',$this->batch($batch)['status']); self::assertNotNull($this->batch($batch)['cancelled_at']);
        self::assertSame([],$this->staging($batch)); self::assertSame($before,$this->snapshot());
        $expired = $this->preview([$this->row()]); $live = $this->preview([$this->row()]);
        $foreign = $service->preview($this->foreign,$this->otherUser,$this->foreignYear,'file.csv',hash('sha256','fixture'),[$this->row(['classroom_code'=>''])]);
        $this->pdo->prepare('UPDATE student_import_batches SET expires_at = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 25 HOUR) WHERE id IN (?,?)')->execute([$expired,$foreign]);
        $foreignBefore = $this->pdo->query('SELECT * FROM student_import_rows WHERE batch_id = ' . $foreign)->fetchAll();
        $service->expirePreviews($this->school);
        self::assertSame('EXPIRED',$this->batch($expired)['status']); self::assertSame([],$this->staging($expired));
        self::assertCount(1,$this->staging($live));
        self::assertSame($foreignBefore,$this->pdo->query('SELECT * FROM student_import_rows WHERE batch_id = ' . $foreign)->fetchAll());
        self::assertSame($before,$this->snapshot());
        $all = $this->snapshot(true);
        foreach ([$expired,$foreign,999999999] as $target) { $this->denied(fn () => $service->cancel($this->school,$this->user,$target)); }
        self::assertSame($all,$this->snapshot(true));
    }

    public function test_preview_expires_abandoned_rows_and_reader_has_no_database_side_effects(): void
    {
        $this->service(); $expired = $this->preview([$this->row()]);
        $this->pdo->prepare('UPDATE student_import_batches SET expires_at = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 25 HOUR) WHERE id = ?')->execute([$expired]);
        $before = $this->snapshot(true);
        $path = $this->csv(self::HEADER . "\nS1,,Mr,Name,Surname,,,P1,,");
        (new CanonicalStudentCsvReader())->read($path,filesize($path)); self::assertSame($before,$this->snapshot(true));
        $this->preview([$this->row()]); self::assertSame('EXPIRED',$this->batch($expired)['status']); self::assertSame([],$this->staging($expired));
        self::assertSame($before['students'],$this->snapshot()['students']); self::assertSame($before['audit_logs'],$this->snapshot()['audit_logs']);
    }

    public function test_source_name_preserves_valid_spaces_after_basename(): void
    {
        $id = $this->service()->preview($this->school, $this->user, $this->year, '/private/ students.csv ', hash('sha256', 'spaces'), [$this->row()]);
        self::assertSame(' students.csv ', $this->batch($id)['source_name']);
    }

    public function test_revalidation_accepts_concurrently_created_exact_identity_without_duplication(): void
    {
        $service = $this->service();
        $rows = [$this->row(), $this->row(['row_no' => 3, 'student_code' => 'SECOND', 'national_id' => '1111111111111'])];
        $batch = $this->preview($rows);
        $student = $this->makeStudent($rows[0]);
        $this->makeEnrollment($student, $this->grade, $this->room);
        $before = $this->snapshot();
        $service->apply($this->school, $this->user, $batch);
        $after = $this->snapshot();
        self::assertCount(count($before['students']) + 1, $after['students']);
        self::assertCount(count($before['student_enrollments']) + 1, $after['student_enrollments']);
        $audits = $after['audit_logs']; $summary = json_decode($audits[array_key_last($audits)]['new_value'], true);
        self::assertSame(1, $summary['noop_rows']); self::assertSame(1, $summary['created_students']);
    }

    public function test_summary_audit_failure_rolls_back_even_after_all_business_writes(): void
    {
        $service = $this->service(); $batch = $this->preview([$this->row()]); $before = $this->snapshot(true);
        $this->pdo->failSql = 'INSERT INTO audit_logs'; $this->pdo->failOccurrence = 4;
        $this->denied(fn () => $service->apply($this->school, $this->user, $batch));
        $this->pdo->failSql = null;
        self::assertSame(4, $this->pdo->matchedStatements);
        self::assertSame($before, $this->snapshot(true));
    }

    #[DataProvider('previewWriteFailures')]
    public function test_preview_staging_failure_leaves_no_partial_batch_or_rows(string $sql): void
    {
        $service = $this->service(); $before = $this->snapshot(true); $this->pdo->failSql = $sql;
        $this->denied(fn () => $this->preview([$this->row()])); $this->pdo->failSql = null;
        self::assertSame($before, $this->snapshot(true));
    }
    public static function previewWriteFailures(): array { return [['INSERT INTO student_import_rows'], ['SET row_count =']]; }

    public function test_expired_batch_cannot_apply_even_before_cleanup_is_triggered(): void
    {
        $service = $this->service(); $id = $this->preview([$this->row()]);
        $this->pdo->prepare('UPDATE student_import_batches SET expires_at=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 25 HOUR) WHERE id=?')->execute([$id]);
        $before = $this->snapshot(true);
        $this->denied(fn () => $service->apply($this->school, $this->user, $id));
        self::assertSame($before, $this->snapshot(true));
    }

    private function csv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pp5-import-');
        file_put_contents($path, $content);
        $this->files[] = $path;
        return $path;
    }
}
