<?php
declare(strict_types=1);

use App\Repositories\AcademicYearRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClassroomRepository;
use App\Repositories\GradeLevelRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\StudentRepository;
use App\Repositories\StudentEnrollmentRepository;
use App\Repositories\StudentClassroomPlacementRepository;
use App\Services\EnrollmentAdministrationService;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnrollmentAdministrationTest extends TestCase
{
    private EnrollmentDomainPDO $pdo;
    private int $school;
    private int $foreignSchool;
    private int $actor;
    private int $student;
    private int $foreignStudent;
    private int $year;
    private int $nextYear;
    private int $foreignYear;
    private int $grade;
    private int $otherGrade;
    private array $rooms;

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new EnrollmentDomainPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->school = $this->insert('schools', ['school_code' => 'enrollment-a', 'name_th' => 'School A']);
        $this->foreignSchool = $this->insert('schools', ['school_code' => 'enrollment-b', 'name_th' => 'School B']);
        $this->actor = $this->insert('users', ['username' => 'enrollment-actor', 'password_hash' => 'unused', 'display_name' => 'Actor']);
        $this->student = $this->studentFixture($this->school);
        $this->foreignStudent = $this->studentFixture($this->foreignSchool);
        $this->year = $this->insert('academic_years', ['school_id' => $this->school, 'year_be' => 2569, 'start_date' => '2026-05-01', 'end_date' => '2027-03-31']);
        $this->nextYear = $this->insert('academic_years', ['school_id' => $this->school, 'year_be' => 2570]);
        $this->foreignYear = $this->insert('academic_years', ['school_id' => $this->foreignSchool, 'year_be' => 2569]);
        $this->grade = (int) $this->pdo->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        $this->otherGrade = (int) $this->pdo->query("SELECT id FROM grade_levels WHERE code='P2'")->fetchColumn();
        $this->rooms = [
            'a' => $this->room($this->school, $this->year, $this->grade, 'A'),
            'b' => $this->room($this->school, $this->year, $this->grade, 'B'),
            'grade' => $this->room($this->school, $this->year, $this->otherGrade, 'G'),
            'year' => $this->room($this->school, $this->nextYear, $this->grade, 'Y'),
            'foreign' => $this->room($this->foreignSchool, $this->foreignYear, $this->grade, 'F'),
        ];
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->failPrepare = null; $this->pdo->failBegin = false; $this->pdo->failCommit = false; $this->pdo->onBegin = null;
            while ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
        }
    }

    #[DataProvider('openYears')]
    public function test_create_active_enrollment_with_or_without_initial_placement(string $status, bool $placed): void
    {
        $this->pdo->prepare('UPDATE academic_years SET status=? WHERE id=?')->execute([$status, $this->year]);
        $id = $this->create(['classroomId' => $placed ? $this->rooms['a'] : null]);
        $row = $this->enrollment($id);
        self::assertSame([$this->school, $this->year, $this->student, $this->grade, '2026-05-01', null, 'ACTIVE'],
            array_map(static fn ($key) => $row[$key], ['school_id','academic_year_id','student_id','grade_level_id','entry_date','exit_date','status']));
        $placements = $this->placements($id); self::assertCount($placed ? 1 : 0, $placements);
        if ($placed) { self::assertSame('ACTIVE', $placements[0]['status']); self::assertNull($placements[0]['ended_at']); self::assertNotEmpty($placements[0]['started_at']); }
        $audits = $this->audits();
        self::assertSame($placed ? ['STUDENT_ENROLLMENT_CREATED','STUDENT_CLASSROOM_PLACEMENT_CHANGED'] : ['STUDENT_ENROLLMENT_CREATED'], array_column($audits,'action'));
        $this->audit($audits[0], $id, null, ['academic_year_id'=>$this->year,'student_id'=>$this->student,'grade_level_id'=>$this->grade,'entry_date'=>'2026-05-01','status'=>'ACTIVE']);
        if ($placed) { $this->audit($audits[1], $id, ['classroom_id'=>null], ['classroom_id'=>$this->rooms['a']]); }
    }
    public static function openYears(): array { return [['DRAFT', false], ['DRAFT', true], ['ACTIVE', false], ['ACTIVE', true]]; }

    public function test_duplicate_student_year_is_denied_but_another_year_is_allowed(): void
    {
        $id = $this->create(); $before = $this->snapshot();
        $this->deny(fn () => $this->create(['gradeLevelId' => $this->otherGrade])); self::assertSame($before, $this->snapshot());
        $next = $this->create(['academicYearId' => $this->nextYear, 'entryDate' => null]);
        self::assertNotSame($id, $next); self::assertNull($this->enrollment($next)['entry_date']);
        self::assertSame([$next, $id], array_column($this->repo()->listForStudent($this->school, $this->student), 'id'));
    }

    #[DataProvider('badParents')]
    public function test_create_rejects_missing_foreign_or_inactive_parents_without_writes(string $case): void
    {
        $changes = [];
        switch ($case) {
            case 'foreign_student': $changes['studentId']=$this->foreignStudent; break;
            case 'missing_student': $changes['studentId']=0; break;
            case 'foreign_year': $changes['academicYearId']=$this->foreignYear; break;
            case 'missing_year': $changes['academicYearId']=0; break;
            case 'missing_grade': $changes['gradeLevelId']=0; break;
            case 'missing_school': $changes['schoolId']=0; break;
            case 'student': $this->pdo->prepare("UPDATE students SET status='INACTIVE' WHERE id=?")->execute([$this->student]); break;
            case 'grade': $this->pdo->prepare("UPDATE grade_levels SET status='INACTIVE' WHERE id=?")->execute([$this->grade]); break;
            case 'year': $this->pdo->prepare("UPDATE academic_years SET status='CLOSED' WHERE id=?")->execute([$this->year]); break;
            case 'school': $this->pdo->prepare("UPDATE schools SET status='SUSPENDED' WHERE id=?")->execute([$this->school]); break;
        }
        $this->service(); $before=$this->snapshot(); $this->pdo->writeAttempts=0;
        $this->deny(fn () => $this->create($changes)); self::assertSame(0,$this->pdo->writeAttempts); self::assertSame($before,$this->snapshot());
    }
    public static function badParents(): array { return array_map(static fn ($v)=>[$v], ['foreign_student','missing_student','foreign_year','missing_year','missing_grade','missing_school','student','grade','year','school']); }

    #[DataProvider('badRooms')]
    public function test_wrong_tenant_year_grade_or_inactive_new_classroom_cannot_create_or_move(string $case): void
    {
        $id=$this->fixture(true);
        $room=$case==='missing' ? 0 : $this->rooms[$case==='inactive' ? 'b' : $case];
        if ($case==='inactive') { $this->pdo->prepare("UPDATE classrooms SET status='INACTIVE' WHERE id=?")->execute([$room]); }
        $other=$this->studentFixture($this->school,'OTHER'); $service=$this->service(); $before=$this->snapshot(); $this->pdo->writeAttempts=0;
        $this->deny(fn ()=>$this->create(['studentId'=>$other,'classroomId'=>$room]));
        $this->deny(fn ()=>$service->changePlacement($this->school,$this->actor,$id,$room));
        self::assertSame(0,$this->pdo->writeAttempts); self::assertSame($before,$this->snapshot());
    }
    public static function badRooms(): array { return [['foreign'],['year'],['grade'],['inactive'],['missing']]; }

    #[DataProvider('badDates')]
    public function test_entry_and_exit_dates_are_strict_and_bounded(string $date): void
    {
        $id=$this->fixture(true); $other=$this->studentFixture($this->school,'OTHER'); $service=$this->service(); $before=$this->snapshot();
        $this->deny(fn ()=>$this->create(['studentId'=>$other,'entryDate'=>$date]));
        foreach (['TRANSFERRED_OUT','WITHDRAWN'] as $status) { $this->deny(fn ()=>$service->changeStatus($this->school,$this->actor,$id,$status,$date)); }
        self::assertSame($before,$this->snapshot());
    }
    public static function badDates(): array
    {
        return array_map(static fn ($v)=>[$v], ['', ' ', '2026-5-01', '01-05-2026', '2026-02-30', '0000-00-00', '0000-01-01', '2026-05-01 00:00:00', '2026-05-01Z', ' 2026-05-01', "2026-05-01\n", "\xFF", '2026-04-30', '2027-04-01']);
    }

    #[DataProvider('validDates')]
    public function test_dates_accept_inclusive_available_year_boundaries(?string $start, ?string $end, ?string $entry, string $exit): void
    {
        $this->pdo->prepare('UPDATE academic_years SET start_date=?,end_date=? WHERE id=?')->execute([$start,$end,$this->year]);
        $id=$this->create(['entryDate'=>$entry]);
        $this->service()->changeStatus($this->school,$this->actor,$id,'WITHDRAWN',$exit);
        self::assertSame([$entry,$exit],[$this->enrollment($id)['entry_date'],$this->enrollment($id)['exit_date']]);
    }
    public static function validDates(): array
    {
        return [['2026-05-01','2027-03-31','2026-05-01','2027-03-31'], ['2026-05-01','2027-03-31','2027-03-31','2027-03-31'],
            [null,null,'2024-02-29','2024-02-29'], ['2026-05-01',null,null,'2028-01-01'], [null,'2027-03-31',null,'2020-01-01']];
    }

    public function test_exit_before_entry_and_terminal_without_exit_are_rejected(): void
    {
        $id=$this->fixture(true); $this->pdo->prepare("UPDATE student_enrollments SET entry_date='2026-06-01' WHERE id=?")->execute([$id]);
        $service=$this->service(); $before=$this->snapshot();
        foreach (['TRANSFERRED_OUT','WITHDRAWN'] as $status) {
            foreach ([null,'','2026-05-31'] as $exit) { $this->deny(fn ()=>$service->changeStatus($this->school,$this->actor,$id,$status,$exit)); }
        }
        self::assertSame($before,$this->snapshot());
    }

    public function test_assignment_move_unassign_and_noops_preserve_history_and_one_active_row(): void
    {
        $id=$this->fixture(); $service=$this->service();
        $before=$this->snapshot(); $this->pdo->writeAttempts=0;
        $service->changePlacement($this->school,$this->actor,$id,null);
        self::assertSame(0,$this->pdo->writeAttempts); self::assertSame($before,$this->snapshot());
        $service->changePlacement($this->school,$this->actor,$id,$this->rooms['a'],'192.0.2.42');
        $first=$this->placements($id)[0]; self::assertSame('ACTIVE',$first['status']);
        $before=$this->snapshot(); $this->pdo->writeAttempts=0;
        $service->changePlacement($this->school,$this->actor,$id,$this->rooms['a']);
        self::assertSame(0,$this->pdo->writeAttempts); self::assertSame($before,$this->snapshot());
        $this->pdo->prepare("UPDATE classrooms SET status='INACTIVE' WHERE id=?")->execute([$this->rooms['a']]);
        $service->changePlacement($this->school,$this->actor,$id,$this->rooms['b'],'192.0.2.42');
        $rows=$this->placements($id); self::assertCount(2,$rows); self::assertSame(['ENDED','ACTIVE'],array_column($rows,'status'));
        self::assertSame($first['id'],$rows[0]['id']); self::assertSame($first['started_at'],$rows[0]['started_at']); self::assertNotNull($rows[0]['ended_at']);
        $service->changePlacement($this->school,$this->actor,$id,null,'192.0.2.42');
        self::assertSame(['ENDED','ENDED'],array_column($this->placements($id),'status'));
        self::assertNull($this->placementRepo()->findActiveForEnrollment($this->school,$id));
        $audits=$this->audits(); self::assertSame(array_fill(0,3,'STUDENT_CLASSROOM_PLACEMENT_CHANGED'),array_column($audits,'action'));
        foreach ([[null,$this->rooms['a']],[$this->rooms['a'],$this->rooms['b']],[$this->rooms['b'],null]] as $i=>[$old,$new]) { $this->audit($audits[$i],$id,['classroom_id'=>$old],['classroom_id'=>$new]); }
        self::assertSame('ACTIVE',$this->enrollment($id)['status']);
    }

    public function test_same_inactive_current_classroom_is_noop_only_while_year_is_open(): void
    {
        $id = $this->fixture(true);
        $service = $this->service();
        $this->pdo->prepare("UPDATE classrooms SET status='INACTIVE' WHERE id=?")->execute([$this->rooms['a']]);
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        try {
            $service->changePlacement($this->school, $this->actor, $id, $this->rooms['a']);
        } catch (DomainException) {
            self::fail('Keeping the current classroom must be a no-op even after classroom inactivation');
        }
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
        $this->pdo->prepare("UPDATE academic_years SET status='CLOSED' WHERE id=?")->execute([$this->year]);
        $before = $this->snapshot();
        $this->pdo->writeAttempts = 0;
        $this->deny(fn () => $service->changePlacement($this->school, $this->actor, $id, $this->rooms['a']));
        self::assertSame(0, $this->pdo->writeAttempts);
        self::assertSame($before, $this->snapshot());
    }

    #[DataProvider('terminalStates')]
    public function test_terminal_transition_ends_placement_and_cannot_reopen_or_change_type(string $status): void
    {
        $id=$this->fixture(true); $service=$this->service();
        $service->changeStatus($this->school,$this->actor,$id,$status,'2026-06-01','192.0.2.42');
        self::assertSame([$status,'2026-06-01'],[$this->enrollment($id)['status'],$this->enrollment($id)['exit_date']]);
        self::assertSame(['ENDED'],array_column($this->placements($id),'status')); self::assertNotNull($this->placements($id)[0]['ended_at']);
        $audits=$this->audits(); self::assertSame(['STUDENT_ENROLLMENT_STATUS_CHANGED','STUDENT_CLASSROOM_PLACEMENT_CHANGED'],array_column($audits,'action'));
        $this->audit($audits[0],$id,['status'=>'ACTIVE','exit_date'=>null],['status'=>$status,'exit_date'=>'2026-06-01']);
        $this->audit($audits[1],$id,['classroom_id'=>$this->rooms['a']],['classroom_id'=>null]);
        $before=$this->snapshot();
        foreach (['ACTIVE',$status==='WITHDRAWN' ? 'TRANSFERRED_OUT' : 'WITHDRAWN'] as $next) { $this->deny(fn ()=>$service->changeStatus($this->school,$this->actor,$id,$next,'2026-06-02')); }
        $this->deny(fn ()=>$service->changePlacement($this->school,$this->actor,$id,$this->rooms['b']));
        $this->deny(fn ()=>$service->changePlacement($this->school,$this->actor,$id,null));
        self::assertSame($before,$this->snapshot());
    }
    public static function terminalStates(): array { return [['TRANSFERRED_OUT'],['WITHDRAWN']]; }

    public function test_open_active_same_status_is_noop_but_closed_year_denies_every_mutation(): void
    {
        $id=$this->fixture(true); $service=$this->service(); $before=$this->snapshot(); $this->pdo->writeAttempts=0;
        $service->changeStatus($this->school,$this->actor,$id,'ACTIVE',null);
        self::assertSame(0,$this->pdo->writeAttempts); self::assertSame($before,$this->snapshot());
        $this->pdo->prepare("UPDATE academic_years SET status='CLOSED' WHERE id=?")->execute([$this->year]);
        $before=$this->snapshot(); $this->pdo->writeAttempts=0;
        foreach ([null,$this->rooms['a'],$this->rooms['b']] as $room) { $this->deny(fn ()=>$service->changePlacement($this->school,$this->actor,$id,$room)); }
        foreach (['ACTIVE','TRANSFERRED_OUT','WITHDRAWN'] as $status) { $this->deny(fn ()=>$service->changeStatus($this->school,$this->actor,$id,$status,'2026-06-01')); }
        self::assertSame(0,$this->pdo->writeAttempts); self::assertSame($before,$this->snapshot());
        self::assertSame($id,$this->repo()->listForStudent($this->school,$this->student)[0]['id']);
        self::assertCount(1,$this->placementRepo()->listForEnrollment($this->school,$id));
    }

    public function test_foreign_and_missing_enrollment_targets_are_indistinguishable(): void
    {
        $foreign=$this->insert('student_enrollments',['school_id'=>$this->foreignSchool,'academic_year_id'=>$this->foreignYear,'student_id'=>$this->foreignStudent,'grade_level_id'=>$this->grade]);
        $service=$this->service(); $before=$this->snapshot(); $errors=[];
        foreach ([$foreign,0] as $id) {
            $errors[]=$this->deny(fn ()=>$service->changePlacement($this->school,$this->actor,$id,$this->rooms['a']));
            $errors[]=$this->deny(fn ()=>$service->changeStatus($this->school,$this->actor,$id,'WITHDRAWN','2026-06-01'));
        }
        self::assertCount(1,array_unique($errors)); self::assertSame($before,$this->snapshot());
    }

    #[DataProvider('badStatuses')]
    public function test_invalid_enrollment_status_is_safe_and_does_not_write(string $status): void
    {
        $id=$this->fixture(true); $service=$this->service(); $before=$this->snapshot();
        $this->deny(fn ()=>$service->changeStatus($this->school,$this->actor,$id,$status,'2026-06-01')); self::assertSame($before,$this->snapshot());
    }
    public static function badStatuses(): array { return [[''],['INACTIVE'],['active'],[' ACTIVE '],['1234567890123']]; }

    public function test_repository_reads_filters_and_writes_preserve_tenant_year_grade_scope(): void
    {
        $id=$this->fixture(true); $repo=$this->repo(); $placements=$this->placementRepo();
        $next=$repo->create($this->school,$this->nextYear,$this->student,$this->otherGrade,null);
        $foreign=$repo->create($this->foreignSchool,$this->foreignYear,$this->foreignStudent,$this->grade,null);
        $foreignPlacement=$placements->create($this->foreignSchool,$this->foreignYear,$this->grade,$foreign,$this->rooms['foreign']);
        self::assertSame($id,$repo->findForSchool($this->school,$id)['id']);
        self::assertSame($id,$repo->lockForSchool($this->school,$id)['id']);
        self::assertSame($id,$repo->findForStudentYear($this->school,$this->year,$this->student)['id']);
        self::assertNull($repo->findForStudentYear($this->school,$this->foreignYear,$this->foreignStudent));
        self::assertSame([$next,$id],array_column($repo->listForStudent($this->school,$this->student),'id'));
        self::assertSame([],$repo->listForStudent($this->school,$this->foreignStudent));
        self::assertSame([],$repo->listForSchoolYear($this->school,$this->foreignYear));
        foreach ([$foreign,0] as $target) {
            self::assertNull($repo->findForSchool($this->school,$target)); self::assertNull($repo->lockForSchool($this->school,$target));
            self::assertSame([],$placements->listForEnrollment($this->school,$target));
            self::assertNull($placements->findActiveForEnrollment($this->school,$target)); self::assertNull($placements->lockActiveForEnrollment($this->school,$target));
            $before=$this->snapshot(); $repo->updateStatus($this->school,$target,'WITHDRAWN','2026-06-01'); self::assertSame($before,$this->snapshot());
        }
        $before=$this->snapshot(); $placements->end($this->school,$foreignPlacement); $placements->end($this->school,0); self::assertSame($before,$this->snapshot());
        foreach ([[],[$this->grade],[$this->grade,$this->rooms['a'],'ACTIVE','ชื่อ'],[null,null,null,'S001']] as $filters) {
            $rows=$repo->listForSchoolYear($this->school,$this->year,...$filters);
            self::assertSame([$id],array_column($rows,'id'));
            self::assertSame($this->rooms['a'],$rows[0]['classroom_id']);
            self::assertArrayNotHasKey('national_id',$rows[0]);
        }
        foreach ([[$this->otherGrade],[null,$this->rooms['foreign']],[null,$this->rooms['year']],[null,null,'WITHDRAWN'],[null,null,null,'1234567890123'],[null,null,null,'%'],[null,null,null,'_']] as $filters) {
            self::assertSame([],$repo->listForSchoolYear($this->school,$this->year,...$filters));
        }
        $current=$placements->lockActiveForEnrollment($this->school,$id);
        self::assertSame($this->rooms['a'],$current['classroom_id']);
        $placements->end($this->school,$current['id']);
        self::assertSame([],$repo->listForSchoolYear($this->school,$this->year,null,$this->rooms['a']));
        self::assertNull($repo->listForSchoolYear($this->school,$this->year)[0]['classroom_id']);
        self::assertSame('ENDED',$placements->listForEnrollment($this->school,$id)[0]['status']);
    }

    #[DataProvider('actions')]
    public function test_school_and_target_locks_follow_plan_and_pre_read_is_before_begin(string $action): void
    {
        $id=$this->fixture(true); $this->service(); $other=$this->studentFixture($this->school,'OTHER'); $this->pdo->events=[];
        $this->operate($action,$id,$other);
        $events=$this->pdo->events;
        $locks=array_values(array_filter($events,static fn ($q)=>str_contains($q,'FOR UPDATE')));
        $expected=$action==='create' ? ['FROM schools','FROM academic_years','FROM students','FROM classrooms'] :
            ($action==='move' ? ['FROM schools','FROM academic_years','FROM student_enrollments','FROM student_classroom_placements','FROM classrooms'] :
                ['FROM schools','FROM academic_years','FROM student_enrollments','FROM student_classroom_placements']);
        self::assertCount(count($expected),$locks);
        foreach ($expected as $i=>$table) { self::assertStringContainsString($table,$locks[$i]); }
        if ($action==='create') { self::assertSame('BEGIN',$events[0]); }
        else { self::assertStringContainsString('FROM student_enrollments',$events[0]); self::assertStringNotContainsString('FOR UPDATE',$events[0]); self::assertSame('BEGIN',$events[1]); }
    }
    public static function actions(): array { return [['create'],['move'],['status']]; }

    #[DataProvider('staleReads')]
    public function test_pre_read_never_authorizes_mutation_after_authoritative_rows_change(string $change): void
    {
        $id=$this->fixture(); $service=$this->service(); $afterChange=null;
        $this->pdo->onBegin=function () use ($id,$change,&$afterChange): void {
            if ($change==='year_closed') { $this->pdo->prepare("UPDATE academic_years SET status='CLOSED' WHERE id=?")->execute([$this->year]); }
            elseif ($change==='year_changed') { $this->pdo->prepare('UPDATE student_enrollments SET academic_year_id=? WHERE id=?')->execute([$this->nextYear,$id]); }
            else { $this->pdo->prepare("UPDATE student_enrollments SET status='WITHDRAWN',exit_date='2026-06-01' WHERE id=?")->execute([$id]); }
            $afterChange=$this->snapshot();
        };
        $this->deny(fn ()=>$service->changePlacement($this->school,$this->actor,$id,$this->rooms['a']));
        self::assertNotNull($afterChange); self::assertSame($afterChange,$this->snapshot());
    }
    public static function staleReads(): array { return [['year_closed'],['year_changed'],['terminal']]; }

    #[DataProvider('failures')]
    public function test_failure_injection_rolls_back_all_enrollment_placement_status_and_audit_writes(string $action,string $failure,int $nth): void
    {
        $id=$this->fixture(true); $other=$this->studentFixture($this->school,'OTHER'); $this->service(); $before=$this->snapshot();
        if ($failure==='begin') { $this->pdo->failBegin=true; }
        elseif ($failure==='commit') { $this->pdo->failCommit=true; }
        else { $this->pdo->failPrepare=$failure; $this->pdo->failNth=$nth; }
        $this->deny(fn ()=>$this->operate($action,$id,$other));
        self::assertTrue($this->pdo->failureTriggered,'Injection must fire'); self::assertSame(1,$this->pdo->depth);
        self::assertSame($before,$this->snapshot());
    }
    public static function failures(): array
    {
        $cases=[];
        foreach (['create','move','status'] as $action) {
            foreach (['begin','commit','INSERT INTO audit_logs'] as $failure) { $cases[]=[$action,$failure,1]; }
        }
        return [...$cases,
            ['create','INSERT INTO student_enrollments',1],['create','INSERT INTO student_classroom_placements',1],['create','INSERT INTO audit_logs',2],
            ['move','UPDATE student_classroom_placements',1],['move','INSERT INTO student_classroom_placements',1],
            ['status','UPDATE student_classroom_placements',1],['status','UPDATE student_enrollments',1],['status','INSERT INTO audit_logs',2],
            ['move','FROM student_enrollments',1],['status','FROM student_enrollments',2],
        ];
    }

    private function service(): EnrollmentAdministrationService
    {
        self::assertTrue(class_exists(EnrollmentAdministrationService::class),'Task 4 EnrollmentAdministrationService missing');
        return new EnrollmentAdministrationService($this->pdo,new SchoolRepository($this->pdo),new AcademicYearRepository($this->pdo),
            new StudentRepository($this->pdo),new GradeLevelRepository($this->pdo),new ClassroomRepository($this->pdo),$this->repo(),$this->placementRepo(),new AuditLogRepository($this->pdo));
    }
    private function repo(): StudentEnrollmentRepository
    {
        self::assertTrue(method_exists(StudentEnrollmentRepository::class,'create'),'Task 4 enrollment repository methods missing');
        return new StudentEnrollmentRepository($this->pdo);
    }
    private function placementRepo(): StudentClassroomPlacementRepository
    {
        self::assertTrue(class_exists(StudentClassroomPlacementRepository::class),'Task 4 placement repository missing');
        return new StudentClassroomPlacementRepository($this->pdo);
    }
    private function create(array $changes=[]): int
    {
        return $this->service()->createEnrollment(...array_replace(['schoolId'=>$this->school,'actorUserId'=>$this->actor,'academicYearId'=>$this->year,
            'studentId'=>$this->student,'gradeLevelId'=>$this->grade,'classroomId'=>null,'entryDate'=>'2026-05-01','ipAddress'=>'192.0.2.42'],$changes));
    }
    private function operate(string $action,int $id,int $other): void
    {
        match ($action) {
            'create'=>$this->create(['studentId'=>$other,'classroomId'=>$this->rooms['b']]),
            'move'=>$this->service()->changePlacement($this->school,$this->actor,$id,$this->rooms['b'],'192.0.2.42'),
            'status'=>$this->service()->changeStatus($this->school,$this->actor,$id,'WITHDRAWN','2026-06-01','192.0.2.42'),
        };
    }
    private function fixture(bool $placed=false): int
    {
        $id=$this->insert('student_enrollments',['school_id'=>$this->school,'academic_year_id'=>$this->year,'student_id'=>$this->student,'grade_level_id'=>$this->grade,'entry_date'=>'2026-05-01']);
        if ($placed) { $this->insert('student_classroom_placements',['school_id'=>$this->school,'academic_year_id'=>$this->year,'grade_level_id'=>$this->grade,'enrollment_id'=>$id,'classroom_id'=>$this->rooms['a']]); }
        return $id;
    }
    private function studentFixture(int $school,string $code='S001'): int
    {
        return $this->insert('students',['school_id'=>$school,'student_code'=>$code,'national_id'=>$code==='S001' ? '1234567890123' : null,'prefix_th'=>'ด.ช.','first_name_th'=>'ชื่อส่วนบุคคล','last_name_th'=>'นามสกุลส่วนบุคคล']);
    }
    private function room(int $school,int $year,int $grade,string $code): int
    {
        return $this->insert('classrooms',['school_id'=>$school,'academic_year_id'=>$year,'grade_level_id'=>$grade,'code'=>$code,'name_th'=>'Class '.$code]);
    }
    private function insert(string $table,array $values): int
    {
        $this->pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')')->execute(array_values($values));
        return (int)$this->pdo->lastInsertId();
    }
    private function enrollment(int $id): array { $q=$this->pdo->prepare('SELECT * FROM student_enrollments WHERE id=?'); $q->execute([$id]); return $q->fetch(); }
    private function placements(int $id): array { $q=$this->pdo->prepare('SELECT * FROM student_classroom_placements WHERE enrollment_id=? ORDER BY id'); $q->execute([$id]); return $q->fetchAll(); }
    private function audits(): array { return $this->pdo->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll(); }
    private function snapshot(): array
    {
        return [$this->pdo->query('SELECT * FROM students ORDER BY id')->fetchAll(),$this->pdo->query('SELECT * FROM student_enrollments ORDER BY id')->fetchAll(),
            $this->pdo->query('SELECT * FROM student_classroom_placements ORDER BY id')->fetchAll(),$this->audits()];
    }
    private function audit(array $row,int $id,?array $old,array $new): void
    {
        self::assertSame([$this->school,$this->actor,'student_enrollments',$id,'192.0.2.42'],[$row['school_id'],$row['user_id'],$row['entity_type'],$row['entity_id'],$row['ip_address']]);
        self::assertSame($old,$row['old_value']===null ? null : json_decode($row['old_value'],true)); self::assertSame($new,json_decode($row['new_value'],true));
        self::assertNotEmpty($row['created_at']); $this->safe(json_encode($row,JSON_UNESCAPED_UNICODE));
    }
    private function safe(string $text): void
    {
        foreach (['1234567890123','ชื่อส่วนบุคคล','นามสกุลส่วนบุคคล','SQLSTATE','PDOException','SELECT ','INSERT ','UPDATE ','Stack trace','/Applications/','private-details','password','fk_student','uq_student'] as $secret) { self::assertStringNotContainsString($secret,$text); }
    }
    private function deny(callable $operation): string
    {
        try { $operation(); } catch (DomainException $e) { self::assertNotSame('',$e->getMessage()); self::assertNull($e->getPrevious()); $this->safe($e->getMessage()); return $e->getMessage(); }
        self::fail('Expected safe DomainException');
    }
}

/** Real PDO operations remain inside the fixture transaction; failures can follow earlier writes. */
final class EnrollmentDomainPDO extends PDO
{
    public int $depth=0;
    public int $writeAttempts=0;
    public array $events=[];
    public ?string $failPrepare=null;
    public int $failNth=1;
    public bool $failBegin=false;
    public bool $failCommit=false;
    public bool $failureTriggered=false;
    public ?Closure $onBegin=null;

    public function beginTransaction(): bool
    {
        if ($this->onBegin!==null) { $callback=$this->onBegin; $this->onBegin=null; $callback(); }
        $this->events[]='BEGIN';
        if ($this->failBegin) { $this->failBegin=false; $this->fail(); }
        $ok=$this->depth===0 ? parent::beginTransaction() : $this->exec('SAVEPOINT enrollment_domain_'.$this->depth)!==false;
        ++$this->depth; return $ok;
    }
    public function commit(): bool
    {
        if ($this->failCommit) { $this->failCommit=false; $this->fail(); }
        $ok=$this->depth===1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT enrollment_domain_'.($this->depth-1))!==false;
        --$this->depth; return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth===1) { $ok=parent::rollBack(); }
        else { $ok=$this->exec('ROLLBACK TO SAVEPOINT enrollment_domain_'.($this->depth-1))!==false; $this->exec('RELEASE SAVEPOINT enrollment_domain_'.($this->depth-1)); }
        --$this->depth; return $ok;
    }
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        $this->events[]=$query;
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i',$query)) { ++$this->writeAttempts; }
        if ($this->failPrepare!==null && str_contains($query,$this->failPrepare) && --$this->failNth===0) { $this->failPrepare=null; $this->fail(); }
        return parent::prepare($query,$options);
    }
    private function fail(): never
    {
        $this->failureTriggered=true;
        throw new PDOException('SQLSTATE private-details 1234567890123 SELECT /Applications/MAMP/secret.php');
    }
}
