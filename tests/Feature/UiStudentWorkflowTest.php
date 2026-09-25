<?php
declare(strict_types=1);

use App\Application;
use App\Http\{Request, Response};
use App\Support\{StatusLabel, View};
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__.'/StudentImportTest.php';

final class UiStudentWorkflowTest extends StudentImportFixtureTestCase
{
    private const ID_MARKER = '0000000000000';
    private const HOSTILE = 'ชื่อไทยยาวสำหรับทดสอบ<script>alert("ui")</script>';
    private int $student;
    private int $enrollment;
    private int $previewId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = $this->makeStudent($this->row(['national_id'=>self::ID_MARKER, 'first_name_th'=>self::HOSTILE]));
        $this->enrollment = $this->makeEnrollment($this->student, $this->grade, $this->room);
        $this->pdo->prepare('UPDATE schools SET name_th=? WHERE id=?')->execute([self::HOSTILE, $this->school]);
        $this->pdo->prepare('UPDATE classrooms SET name_th=? WHERE id=?')->execute([self::HOSTILE, $this->room]);
        $this->previewId = $this->service()->preview($this->school, $this->user, $this->year,
            '<img onerror="bad">.csv', hash('sha256', 'ui-preview'), [$this->row(['student_code'=>'NEW', 'national_id'=>null])]);
    }

    public static function pages(): array
    {
        return [['/students'], ['/students/create'], ['/students/{student}'], ['/students/{student}/edit'],
            ['/academic/enrollments'], ['/academic/enrollments/create'], ['/academic/enrollments/{enrollment}/edit'],
            ['/academic/student-import'], ['/academic/student-import/{batch}']];
    }

    #[DataProvider('pages')]
    public function testPagesHaveSharedShellAccessibleFieldsAndEscapedIdentity(string $path): void
    {
        $path = $this->resolve($path);
        $response = $this->request('GET', $path, [], ['academic_year_id'=>(string)$this->year, 'grade_level_id'=>(string)$this->grade]);
        self::assertSame(200, $response->status());
        $x = $this->assertPage($response);
        self::assertStringContainsString(htmlspecialchars(self::HOSTILE, ENT_QUOTES, 'UTF-8'), $response->body());
        self::assertStringNotContainsString(self::HOSTILE, $response->body());
        self::assertStringNotContainsString('กลับแดชบอร์ด', $response->body());
        foreach ($x->query('//main//input[not(@type="hidden")]|//main//select') as $control) {
            $id = $control->getAttribute('id');
            self::assertNotSame('', $id);
            self::assertSame(1, $x->query('//*[@id="'.$id.'"]')->length);
            self::assertSame(1, $x->query('//label[@for="'.$id.'"]')->length);
        }
        foreach ($x->query('//main//table') as $table) {
            self::assertSame(1, $x->query('ancestor::*[contains(@class,"pp5-table-scroll") and @role="region" and @aria-label and @tabindex="0"]', $table)->length);
            self::assertSame(0, $x->query('.//thead//th[not(@scope="col")]', $table)->length);
            self::assertGreaterThan(0, $x->query('.//tbody//th[@scope="row"]', $table)->length);
        }
        foreach ($x->query('//form[@method="post"]') as $form) {
            self::assertSame($_SESSION['csrf_token'], $x->evaluate('string(.//input[@name="_token"]/@value)', $form));
        }
        if ($path !== '/students/'.$this->student.'/edit') { self::assertStringNotContainsString(self::ID_MARKER, $response->body()); }
        self::assertStringNotContainsString('FOREIGN_SECRET', $response->body());
    }

    public function testStudentSearchRetainsOnlySafeNormalizedQueryAndDetailKeepsSeparateHistories(): void
    {
        foreach (['S1', '<b>ค้นหา</b>', str_repeat('ก',100)] as $query) {
            $x = $this->assertPage($this->request('GET','/students',[],['q'=>"\u{3000}".$query."\u{00A0}"]));
            self::assertSame($query,$x->evaluate('string(//main//input[@name="q"]/@value)'));
        }
        foreach ([self::ID_MARKER, 'id '.self::ID_MARKER, "\u{3000}"] as $query) {
            $r = $this->request('GET','/students',[],['q'=>$query]);
            self::assertSame('', $this->xpath($r->body())->evaluate('string(//main//input[@name="q"]/@value)'));
            self::assertStringNotContainsString(self::ID_MARKER,$r->body());
        }
        $x = $this->assertPage($this->request('GET','/students/'.$this->student));
        self::assertStringContainsString('*********0000', $x->document->textContent);
        self::assertSame(1,$x->query('//h2[text()="ประวัติการลงทะเบียน"]')->length);
        self::assertSame(1,$x->query('//section[contains(@class,"enrollment-history")]//h4[text()="ประวัติการจัดห้องเรียน"]')->length);
        self::assertSame(2,$x->query('//section[contains(@class,"enrollment-history")]//*[@data-status="ACTIVE"]')->length);
    }

    public function testEnrollmentFiltersAndEligibleCreateChoicesStayScopedAndSelected(): void
    {
        $filters = ['academic_year_id'=>(string)$this->year,'grade_level_id'=>(string)$this->grade,
            'classroom_id'=>(string)$this->room,'status'=>'ACTIVE','q'=>'S1'];
        $x = $this->assertPage($this->request('GET','/academic/enrollments',[],$filters));
        foreach ($filters as $name=>$value) {
            self::assertSame($value,$x->evaluate('string(//main//'.($name==='q'?'input[@name="q"]/@value':'select[@name="'.$name.'"]/option[@selected]/@value').')'));
        }
        self::assertSame(1,$x->query('//main//tbody/tr')->length);
        self::assertSame((string)$this->year,$this->xpath($this->request('GET','/academic/enrollments')->body())->evaluate('string(//select[@name="academic_year_id"]/option[@selected]/@value)'));
        $this->pdo->prepare("UPDATE academic_years SET status='CLOSED' WHERE id=?")->execute([$this->nextYear]);
        $r=$this->request('GET','/academic/enrollments/create',[],$filters);
        $x=$this->assertPage($r);
        foreach ([$this->nextYear,$this->foreignYear] as $id) { self::assertSame(0,$x->query('//select[@name="academic_year_id"]/option[@value="'.$id.'"]')->length); }
        self::assertStringContainsString('ห้องเรียนเริ่มต้น',$r->body());
        self::assertStringContainsString('วันที่เข้าเรียน',$r->body());
        self::assertStringNotContainsString(self::ID_MARKER,$r->body());
        self::assertSame(404,$this->request('GET','/academic/enrollments',[],['academic_year_id'=>$this->foreignYear])->status());
    }

    public static function lifecycle(): array
    {
        return [['DRAFT','ACTIVE',true],['ACTIVE','ACTIVE',true],['CLOSED','ACTIVE',false],
            ['ACTIVE','TRANSFERRED_OUT',false],['ACTIVE','WITHDRAWN',false]];
    }

    #[DataProvider('lifecycle')]
    public function testEnrollmentSectionsPreserveMutableRuleAndHistory(string $year, string $state, bool $mutable): void
    {
        $this->pdo->prepare('UPDATE academic_years SET status=? WHERE id=?')->execute([$year,$this->year]);
        $this->pdo->prepare('UPDATE student_enrollments SET status=?, exit_date=? WHERE id=?')->execute([$state,$state==='ACTIVE'?null:'2026-06-01',$this->enrollment]);
        $x=$this->assertPage($this->request('GET','/academic/enrollments/'.$this->enrollment.'/edit'));
        foreach (['สถานะการลงทะเบียน','การจัดห้องเรียน','ประวัติการจัดห้องเรียน'] as $heading) {
            self::assertSame(1,$x->query('//main//h2[text()="'.$heading.'"]')->length);
        }
        self::assertSame($mutable?2:0,$x->query('//main//form[@method="post"]')->length);
        self::assertSame(1,$x->query('//main//table//tbody/tr')->length);
        if ($mutable) {
            self::assertSame(1,$x->query('//section[h2[text()="สถานะการลงทะเบียน"]]//form[@data-confirm and contains(@action,"/status")]')->length);
            self::assertSame(1,$x->query('//section[h2[text()="การจัดห้องเรียน"]]//form[not(@data-confirm) and contains(@action,"/placement")]')->length);
        } else {
            self::assertStringContainsString('อ่านเท่านั้น',$x->document->textContent);
        }
    }

    public function testLivePermissionRevocationHidesActionsAndDirectRequestsRemainDenied(): void
    {
        $this->assertPage($this->request('GET','/students'));
        self::assertStringContainsString('/students/create',$this->request('GET','/students')->body());
        self::assertStringContainsString('data-confirm=',$this->request('GET','/students/'.$this->student.'/edit')->body());
        $this->revoke('STUDENT_MANAGE');
        foreach (['/students','/students/'.$this->student] as $path) {
            $x=$this->assertPage($this->request('GET',$path));
            self::assertSame(0,$x->query('//main//a[starts-with(@href,"/students/") and (contains(@href,"/edit") or @href="/students/create")]')->length);
        }
        foreach (['/students/create','/students/'.$this->student.'/edit'] as $path) { self::assertSame(403,$this->request('GET',$path)->status()); }
        self::assertSame(403,$this->request('POST','/students/'.$this->student.'/status',['_token'=>$_SESSION['csrf_token'],'status'=>'INACTIVE'])->status());
        $this->revoke('ENROLLMENT_MANAGE');
        $x=$this->assertPage($this->request('GET','/academic/enrollments'));
        self::assertSame(0,$x->query('//main//a[contains(@href,"/edit") or contains(@href,"/create")]')->length);
        self::assertSame(403,$this->request('GET','/academic/enrollments/'.$this->enrollment.'/edit')->status());
        self::assertSame(403,$this->request('POST','/academic/enrollments/'.$this->enrollment.'/placement',['_token'=>$_SESSION['csrf_token']])->status());
        $this->revoke('STUDENT_IMPORT');
        self::assertSame(0,$this->assertPage($this->request('GET','/students'))->query('//a[@href="/academic/student-import"]')->length);
        self::assertSame(403,$this->request('GET','/academic/student-import/'.$this->previewId)->status());
        self::assertSame(403,$this->request('POST','/academic/student-import/'.$this->previewId.'/apply',['_token'=>$_SESSION['csrf_token']])->status());
        $this->revoke('STUDENT_VIEW');
        self::assertSame(403,$this->request('GET','/students')->status());
        self::assertSame(403,$this->request('GET','/academic/enrollments')->status());
    }

    public static function errors(): array
    {
        return [['/students'],['/students/{student}'],['/academic/enrollments'],['/academic/student-import/preview']];
    }

    #[DataProvider('errors')]
    public function testValidationErrorsRemainSafeShellWithoutReflectingIdentity(string $path): void
    {
        $before=$this->snapshot(true);
        $r=$this->request('POST',$this->resolve($path),['_token'=>$_SESSION['csrf_token'],'national_id'=>self::ID_MARKER,'first_name_th'=>'PRIVATE_IDENTITY_MARKER']);
        self::assertSame(422,$r->status()); $x=$this->assertPage($r);
        self::assertSame(1,$x->query('//main//*[@role="alert"]')->length);
        foreach ([self::ID_MARKER,'PRIVATE_IDENTITY_MARKER','SQLSTATE','/Applications/','Stack trace'] as $secret) { self::assertStringNotContainsString($secret,$r->body()); }
        self::assertSame($before,$this->snapshot(true));
    }

    public function testImportStepsRowDecisionsAndConfirmationAreExplicitAndPiiSafe(): void
    {
        foreach (['/academic/student-import','/academic/student-import/'.$this->previewId] as $path) {
            $x=$this->assertPage($this->request('GET',$path));
            self::assertSame(['อัปโหลด','ตรวจสอบ','ยืนยัน'],array_map(static fn($n)=>trim($n->textContent),iterator_to_array($x->query('//ol[@aria-label="ขั้นตอนนำเข้า"]/li'))));
        }
        $r=$this->request('GET','/academic/student-import/'.$this->previewId); $x=$this->xpath($r->body());
        self::assertStringContainsString('&lt;img onerror=&quot;bad&quot;&gt;.csv',$r->body());
        self::assertSame(2,$x->query('//tbody//*[@data-status="CREATE"]')->length);
        foreach (['apply','cancel'] as $action) { self::assertSame(1,$x->query('//main//form[@data-confirm and @action="/academic/student-import/'.$this->previewId.'/'.$action.'"]')->length); }
        $this->pdo->prepare('UPDATE student_import_rows SET national_id=?,error_code=?,error_message=? WHERE batch_id=?')->execute([self::ID_MARKER,'CONFLICT',self::HOSTILE,$this->previewId]);
        $this->pdo->prepare('UPDATE student_import_batches SET error_count=1 WHERE id=?')->execute([$this->previewId]);
        $r=$this->request('GET','/academic/student-import/'.$this->previewId); $x=$this->assertPage($r);
        self::assertSame(1,$x->query('//tbody//*[@data-status="CONFLICT"]')->length);
        self::assertStringContainsString(htmlspecialchars(self::HOSTILE,ENT_QUOTES,'UTF-8'),$r->body());
        self::assertStringNotContainsString(self::ID_MARKER,$r->body());
        self::assertSame(0,$x->query('//form[contains(@action,"/apply")]')->length);
    }

    public static function batchStates(): array { return [['APPLIED'],['CANCELLED'],['EXPIRED'],['NOOP']]; }

    #[DataProvider('batchStates')]
    public function testImportLifecycleKeepsUnavailableActionsAbsent(string $state): void
    {
        if ($state==='APPLIED') { $this->service()->apply($this->school,$this->user,$this->previewId); }
        elseif ($state==='CANCELLED') { $this->service()->cancel($this->school,$this->user,$this->previewId); }
        elseif ($state==='EXPIRED') { $this->pdo->prepare('UPDATE student_import_batches SET expires_at=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 25 HOUR) WHERE id=?')->execute([$this->previewId]); }
        else {
            $this->previewId = $this->service()->preview($this->school,$this->user,$this->year,'noop.csv',hash('sha256','noop'),
                [$this->row(['national_id'=>self::ID_MARKER,'first_name_th'=>self::HOSTILE])]);
            self::assertSame(1,$this->batch($this->previewId)['noop_count']);
        }
        $x=$this->assertPage($this->request('GET','/academic/student-import/'.$this->previewId));
        self::assertSame(0,$x->query('//main//form[contains(@action,"/apply")]')->length);
        self::assertSame($state==='NOOP'?1:0,$x->query('//main//form[contains(@action,"/cancel")]')->length);
        if ($state!=='NOOP') { self::assertSame([],$this->staging($this->previewId)); }
        $before=$this->snapshot(true);
        self::assertSame(422,$this->request('POST','/academic/student-import/'.$this->previewId.'/apply',['_token'=>$_SESSION['csrf_token']])->status());
        self::assertSame($before,$this->snapshot(true));
    }

    public function testCsrfAndForeignTargetsRemainUnchanged(): void
    {
        $before=$this->snapshot(true);
        foreach (['/students/'.$this->student.'/status','/academic/enrollments/'.$this->enrollment.'/status','/academic/student-import/'.$this->previewId.'/apply'] as $path) {
            self::assertSame(419,$this->request('POST',$path,['_token'=>'bad'])->status());
        }
        self::assertSame($before,$this->snapshot(true));
        $foreignBatch=$this->service()->preview($this->foreign,$this->otherUser,$this->foreignYear,'foreign.csv',hash('sha256','foreign'),[$this->row(['national_id'=>null,'classroom_code'=>''])]);
        self::assertSame(404,$this->request('GET','/academic/student-import/'.$foreignBatch)->status());
        $before=$this->snapshot(true);
        self::assertSame(422,$this->request('POST','/academic/student-import/'.$foreignBatch.'/apply',['_token'=>$_SESSION['csrf_token']])->status());
        self::assertSame($before,$this->snapshot(true));
    }

    public function testRoutineFormsDoNotConfirmAndUploadIsCsvOnly(): void
    {
        foreach (['/students/create','/academic/enrollments/create','/academic/student-import'] as $path) {
            $x=$this->assertPage($this->request('GET',$path));
            self::assertSame(0,$x->query('//form[@data-confirm]')->length);
        }
        $this->pdo->prepare("UPDATE students SET status='INACTIVE' WHERE id=?")->execute([$this->student]);
        $x=$this->assertPage($this->request('GET','/students/'.$this->student.'/edit'));
        self::assertSame(0,$x->query('//form[@data-confirm]')->length);
        $r=$this->request('GET','/academic/student-import');
        self::assertStringContainsString('canonical CSV เท่านั้น',$r->body());
        self::assertSame(1,$this->xpath($r->body())->query('//form[@method="post" and @enctype="multipart/form-data"]//input[@name="student_file" and @type="file" and @accept=".csv" and @required]')->length);
    }

    public function testUnknownStatusIsEscapedAndEnrollmentLabelsUseExistingCodes(): void
    {
        self::assertSame('กำลังเรียน',StatusLabel::text('ACTIVE','enrollment'));
        self::assertSame('ลาออก',StatusLabel::text('WITHDRAWN','enrollment'));
        $html=View::render('ui/status',['status'=>'<script>unknown</script>','kind'=>'import-action']);
        self::assertStringContainsString('&lt;script&gt;unknown&lt;/script&gt;',$html);
        self::assertStringNotContainsString('<script>',$html);
    }

    private function assertPage(Response $r): DOMXPath
    {
        $x=$this->xpath($r->body());
        foreach (['//html','//head','//body','//main','//h1','//*[@class="pp5-shell"]','//script[@src="/assets/app.js" and @defer]'] as $selector) { self::assertSame(1,$x->query($selector)->length,$selector); }
        self::assertSame(1,substr_count($r->body(),'<!doctype html>'));
        self::assertSame(0,$x->query('//script[not(@src)]|//*[@onclick or @style]|//link[starts-with(@href,"http")]')->length);
        return $x;
    }
    private function xpath(string $html): DOMXPath { $d=new DOMDocument(); @$d->loadHTML('<?xml encoding="UTF-8">'.$html); return new DOMXPath($d); }
    private function resolve(string $path): string { return strtr($path,['{student}'=>(string)$this->student,'{enrollment}'=>(string)$this->enrollment,'{batch}'=>(string)$this->previewId]); }
    private function revoke(string $permission): void { $this->pdo->prepare("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='SCHOOL_ADMIN' AND p.code=?")->execute([$permission]); }
    private function request(string $method,string $path,array $post=[],array $query=[]): Response { return (new Application($this->pdo))->handle(new Request($method,$path,$query,$post,['REMOTE_ADDR'=>'127.0.0.1'])); }
}
