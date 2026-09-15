<?php
declare(strict_types=1);

use App\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/StudentImportTest.php';

final class StudentImportHttpTest extends StudentImportFixtureTestCase
{
    public function test_upload_accessor_is_raw_and_existing_constructor_remains_compatible(): void
    {
        self::assertTrue(method_exists(Request::class, 'file'), 'Request upload accessor is missing');
        $old = new Request('GET', '/', [], [], []);
        self::assertSame('fallback', $old->file('student_file', 'fallback'));
        foreach ([['name' => ['malformed']], new stdClass(), 'raw', 42] as $raw) {
            $request = new Request('POST', '/', [], [], [], ['student_file' => $raw]);
            self::assertSame($raw, $request->file('student_file'));
        }
    }

    public function test_from_globals_preserves_files(): void
    {
        self::assertTrue(method_exists(Request::class, 'file'), 'Request upload accessor is missing');
        $saved = [$_GET, $_POST, $_SERVER, $_FILES];
        try {
            $_GET = []; $_POST = []; $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/academic/student-import/preview'];
            $_FILES = ['student_file' => ['name' => 'students.csv', 'error' => UPLOAD_ERR_OK]];
            self::assertSame($_FILES['student_file'], Request::fromGlobals()->file('student_file'));
        } finally { [$_GET, $_POST, $_SERVER, $_FILES] = $saved; }
    }
    #[DataProvider('importRoutes')]
    public function test_routes_enforce_session_context_and_direct_permission_grant_revoke(string $method, string $suffix): void
    {
        $batch = $this->preview([$this->row()]);
        $path = '/academic/student-import' . str_replace('{id}', (string)$batch, $suffix);
        $session = $_SESSION;
        $post = ['_token'=>$session['csrf_token'],'academic_year_id'=>$this->year];
        $upload = ['student_file'=>$this->upload()];
        $_SESSION=[];
        self::assertEquals(App\Http\Response::redirect('/login'),$this->request($method,$path,$post,[],$upload));
        $_SESSION=$session; $_SESSION['context_type']='SYSTEM';
        $before=$this->snapshot(true);
        self::assertSame(403,$this->request($method,$path,$post,[],$upload)->status()); self::assertSame($before,$this->snapshot(true));
        $_SESSION=$session;
        $this->pdo->prepare("UPDATE user_role_assignments SET role_id = (SELECT id FROM roles WHERE code = 'VIEWER') WHERE user_id = ?")->execute([$this->user]);
        self::assertSame(403,$this->request($method,$path,$post,[],$upload)->status()); self::assertSame($before,$this->snapshot(true));
        $this->pdo->exec("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='VIEWER' AND p.code='STUDENT_IMPORT'");
        $this->pdo->beginTransaction();
        self::assertSame($method==='GET'?200:302,$this->request($method,$path,$post,[],$upload)->status());
        $this->pdo->rollBack();
        $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='VIEWER' AND p.code='STUDENT_IMPORT'");
        self::assertSame(403,$this->request($method,$path,$post,[],$upload)->status()); self::assertSame($before,$this->snapshot(true));
    }

    public static function importRoutes(): array
    {
        return [['GET',''],['POST','/preview'],['GET','/{id}'],['POST','/{id}/apply'],['POST','/{id}/cancel']];
    }

    #[DataProvider('badTokens')]
    public function test_csrf_rejects_before_upload_validation_expiry_staging_or_business_writes(mixed $token): void
    {
        $batch=$this->preview([$this->row()]);
        $this->pdo->prepare('UPDATE student_import_batches SET expires_at=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 25 HOUR) WHERE id=?')->execute([$batch]);
        foreach (['/preview','/'.$batch.'/apply','/'.$batch.'/cancel'] as $suffix) {
            $before=$this->snapshot(true);
            $response=$this->request('POST','/academic/student-import'.$suffix,['_token'=>$token,'academic_year_id'=>new stdClass()],[],['student_file'=>new stdClass()]);
            self::assertSame(419,$response->status()); self::assertSame($before,$this->snapshot(true)); $this->safe($response->body());
        }
    }
    public static function badTokens(): array { return [[null],['wrong'],[[]],[new stdClass()]]; }

    #[DataProvider('badUploads')]
    public function test_malformed_upload_is_friendly_and_has_no_side_effects(string $field, mixed $value): void
    {
        $upload=$this->upload();
        if ($field==='upload') { $upload=$value; } else { $upload[$field]=$value; }
        $before=$this->snapshot(true);
        $response=$this->request('POST','/academic/student-import/preview',['_token'=>$_SESSION['csrf_token'],'academic_year_id'=>$this->year],[],['student_file'=>$upload]);
        self::assertSame(422,$response->status()); $this->safe($response->body()); self::assertSame($before,$this->snapshot(true));
    }
    public static function badUploads(): array
    {
        return [['upload',null],['upload',new stdClass()],['upload','file'],['upload',[]],['error',UPLOAD_ERR_PARTIAL],['error',UPLOAD_ERR_NO_FILE],
            ['error','0'],['error',[]],['error',new stdClass()],['tmp_name',[]],['tmp_name',new stdClass()],['tmp_name','/missing/private.csv'],
            ['name',[]],['name',new stdClass()],['name',"bad\n.csv"],['name',"bad\xFF.csv"],['name',str_repeat('ก',191)],
            ['size',[]],['size',new stdClass()],['size',false],['size',0],['size',-1],['size','1.5'],['size',2097153]];
    }

    #[DataProvider('badYearIds')]
    public function test_malformed_year_never_causes_type_error_or_writes(mixed $year): void
    {
        $before=$this->snapshot(true);
        $response=$this->request('POST','/academic/student-import/preview',['_token'=>$_SESSION['csrf_token'],'academic_year_id'=>$year],[],['student_file'=>$this->upload()]);
        self::assertSame(422,$response->status()); self::assertSame($before,$this->snapshot(true)); $this->safe($response->body());
    }
    public static function badYearIds(): array { return [[[]],[new stdClass()],[null],['0'],['-1'],['1.5']]; }

    public function test_upload_preview_apply_flow_escapes_html_hides_national_id_and_attributes_session_actor(): void
    {
        $index=$this->request('GET','/academic/student-import'); self::assertSame(200,$index->status());
        foreach (['multipart/form-data','name="academic_year_id"','name="student_file"','name="_token"'] as $marker) { self::assertStringContainsString($marker,$index->body()); }
        $row=$this->row(['student_code'=>'<script>code</script>','first_name_th'=>'<img src=x onerror="alert(1)">']);
        $file=$this->upload([$row]); $file['name']='/private/path/<b>students</b>.csv'; $file['size']=(string)$file['size']; $file['type']='application/x-unknown';
        $forged=['school_id'=>$this->foreign,'user_id'=>$this->otherUser,'actor_user_id'=>$this->otherUser,'role'=>'SYSTEM_ADMIN','context_type'=>'SYSTEM'];
        $before=$this->snapshot();
        $response=$this->request('POST','/academic/student-import/preview',['_token'=>$_SESSION['csrf_token'],'academic_year_id'=>$this->year]+$forged,$forged,['student_file'=>$file]);
        self::assertSame(302,$response->status()); self::assertSame($before,$this->snapshot());
        $batches=$this->snapshot(true)['student_import_batches']; $batch=$batches[array_key_last($batches)];
        self::assertSame($this->school,$batch['school_id']); self::assertSame($this->user,$batch['created_by']);
        self::assertSame('b>.csv',$batch['source_name']);
        $id=$batch['id'];
        $preview=$this->request('GET','/academic/student-import/'.$id,[],$forged);
        self::assertSame(200,$preview->status()); $this->safe($preview->body());
        self::assertStringContainsString('&lt;script&gt;code&lt;/script&gt;',$preview->body());
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;',$preview->body());
        self::assertStringNotContainsString('<script>',$preview->body()); self::assertStringNotContainsString('<img',$preview->body());
        self::assertStringContainsString('/'.$id.'/apply',$preview->body()); self::assertStringContainsString('/'.$id.'/cancel',$preview->body());
        self::assertSame(302,$this->request('POST','/academic/student-import/'.$id.'/apply',['_token'=>$_SESSION['csrf_token']]+$forged)->status());
        self::assertSame('APPLIED',$this->batch($id)['status']); self::assertSame([],$this->staging($id));
        foreach ($this->snapshot()['audit_logs'] as $audit) { $this->safe(json_encode($audit,JSON_THROW_ON_ERROR)); self::assertSame($this->user,$audit['user_id']); }
        $done=$this->request('GET','/academic/student-import/'.$id); self::assertSame(200,$done->status());
        self::assertStringNotContainsString('/apply',$done->body()); self::assertStringNotContainsString('/cancel',$done->body());
    }

    public function test_apply_controls_absent_for_errors_noops_cancelled_and_expired_batches(): void
    {
        foreach (['error','noop','cancelled','expired'] as $state) {
            $this->pdo->beginTransaction();
            if ($state==='noop') { $this->makeEnrollment($this->makeStudent($this->row()),$this->grade,$this->room); }
            $id=$this->preview([$this->row($state==='error'?['grade_level_code'=>'missing']:[])]);
            if ($state==='cancelled') { self::assertSame(302,$this->request('POST','/academic/student-import/'.$id.'/cancel',['_token'=>$_SESSION['csrf_token']])->status()); }
            if ($state==='expired') { $this->pdo->prepare('UPDATE student_import_batches SET expires_at=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 25 HOUR) WHERE id=?')->execute([$id]); }
            $response=$this->request('GET','/academic/student-import/'.$id);
            self::assertSame(200,$response->status()); self::assertStringNotContainsString('/'.$id.'/apply',$response->body()); $this->safe($response->body());
            if (in_array($state,['cancelled','expired'],true)) { self::assertStringNotContainsString('/'.$id.'/cancel',$response->body()); self::assertSame([],$this->staging($id)); }
            $this->pdo->rollBack();
        }
    }

    public function test_index_cleans_only_session_school_expired_previews(): void
    {
        $id=$this->preview([$this->row()]);
        $this->pdo->prepare('UPDATE student_import_batches SET expires_at=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 25 HOUR) WHERE id=?')->execute([$id]);
        $before=$this->snapshot();
        self::assertSame(200,$this->request('GET','/academic/student-import')->status());
        self::assertSame('EXPIRED',$this->batch($id)['status']); self::assertSame([],$this->staging($id)); self::assertSame($before,$this->snapshot());
    }

    protected function request(string $method,string $path,array $post=[],array $query=[],array $files=[]): App\Http\Response
    {
        return (new App\Application($this->pdo))->handle(new Request($method,$path,$query,$post,['REMOTE_ADDR'=>'127.0.0.1'],$files));
    }
    private function upload(?array $rows=null): array
    {
        $path=tempnam(sys_get_temp_dir(),'pp5-upload-'); $this->files[]=$path;
        $stream=fopen($path,'w');
        $fields=['student_code','national_id','prefix_th','first_name_th','last_name_th','gender_code','birth_date','grade_level_code','classroom_code','entry_date'];
        fputcsv($stream,$fields,',','"','');
        foreach ($rows??[$this->row()] as $row) { fputcsv($stream,array_map(static fn ($f)=>$row[$f],$fields),',','"',''); }
        fclose($stream);
        return ['error'=>UPLOAD_ERR_OK,'name'=>'students.csv','tmp_name'=>$path,'size'=>filesize($path),'type'=>'text/csv'];
    }
}
