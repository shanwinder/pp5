<?php
declare(strict_types=1);

// Synthetic production-view fixture; no database, session, or business routes.
// php -S 127.0.0.1:18894 tests/Browser/ui-student-workflow.php
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
require dirname(__DIR__, 2).'/htdocs/vendor/autoload.php';
use App\Support\View;
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$assets=[
    '/assets/vendor/bootstrap-5.3.8.min.css'=>['/htdocs/assets/vendor/bootstrap-5.3.8.min.css','text/css'],
    '/assets/app.css'=>['/htdocs/assets/app.css','text/css'],
    '/assets/app.js'=>['/htdocs/assets/app.js','text/javascript'],
    '/checks.js'=>['/tests/Browser/ui-student-workflow.js','text/javascript'],
];
if (isset($assets[$path])) {
    [$file,$type]=$assets[$path]; header('Content-Type: '.$type.'; charset=UTF-8'); readfile(dirname(__DIR__,2).$file); exit;
}
$pages=['students/index'=>'รายชื่อนักเรียน','students/create'=>'เพิ่มนักเรียน','students/edit'=>'แก้ไขข้อมูลนักเรียน',
    'students/show'=>'รายละเอียดนักเรียน','academic/enrollments/index'=>'การลงทะเบียนนักเรียน',
    'academic/enrollments/create'=>'เพิ่มการลงทะเบียน','academic/enrollments/edit'=>'รายละเอียดการลงทะเบียน',
    'academic/student-import/index'=>'นำเข้านักเรียน','academic/student-import/preview'=>'ตรวจสอบการนำเข้านักเรียน'];
if ($path==='/frame') {
    $page=$_GET['page']??''; if (!isset($pages[$page])) { http_response_code(404); exit; }
    $variant=$_GET['variant']??'normal';
    $hostile=str_repeat('ชื่อภาษาไทยยาวสำหรับทดสอบ',8).'<script>alert("workflow")</script>';
    $year=['id'=>1,'year_be'=>2569,'status'=>'ACTIVE'];
    $grade=['id'=>1,'name_th'=>'ประถมศึกษาปีที่ 1'];
    $room=['id'=>1,'name_th'=>$hostile];
    // Never put raw national ID into browser fixture data or output, including the edit surface.
    $student=['id'=>1,'student_code'=>'TEST-01','national_id'=>null,'prefix_th'=>'ด.ช.',
        'first_name_th'=>$hostile,'last_name_th'=>'ข้อมูลสังเคราะห์','gender_code'=>null,'birth_date'=>null,'status'=>'ACTIVE'];
    $enrollment=['id'=>1,'student_id'=>1,'academic_year_id'=>1,'year_be'=>2569,'academic_year_status'=>'ACTIVE',
        'grade_level_id'=>1,'grade_level_name'=>$grade['name_th'],'classroom_id'=>1,'classroom_name'=>$hostile,
        'entry_date'=>'2026-05-01','exit_date'=>null]+$student;
    $placements=[['classroom_name'=>$hostile,'status'=>'ENDED','started_at'=>'2026-05-01','ended_at'=>'2026-06-01'],
        ['classroom_name'=>$hostile,'status'=>'ACTIVE','started_at'=>'2026-06-01','ended_at'=>null]];
    if ($variant==='closed') { $enrollment['academic_year_status']='CLOSED'; }
    if ($variant==='terminal') { $enrollment['status']='TRANSFERRED_OUT'; }
    $batch=['id'=>1,'source_name'=>$hostile.'.csv','status'=>'PREVIEW','row_count'=>2,'create_student_count'=>1,
        'create_enrollment_count'=>1,'noop_count'=>1,'error_count'=>0,'expires_at'=>'2026-09-25 12:00:00','is_expired'=>false];
    $rows=[['row_no'=>2,'student_code'=>'NEW','prefix_th'=>'ด.ช.','first_name_th'=>$hostile,'last_name_th'=>'ทดสอบ',
        'grade_level_code'=>'P1','classroom_code'=>'P1-1','student_action'=>'CREATE','enrollment_action'=>'CREATE','error_code'=>null,'error_message'=>null],
        ['row_no'=>3,'student_code'=>'MATCH','prefix_th'=>'ด.ช.','first_name_th'=>$hostile,'last_name_th'=>'ทดสอบ',
        'grade_level_code'=>'P1','classroom_code'=>'P1-1','student_action'=>'MATCH','enrollment_action'=>'NOOP','error_code'=>null,'error_message'=>null]];
    if ($variant==='noop') { $batch['create_student_count']=0; $batch['create_enrollment_count']=0; $batch['noop_count']=2; $rows[0]=array_replace($rows[0],['student_action'=>'MATCH','enrollment_action'=>'NOOP']); }
    if ($variant==='error') { $batch['error_count']=1; $rows[0]=array_replace($rows[0],['student_action'=>'NONE','enrollment_action'=>'NONE','error_code'=>'CONFLICT','error_message'=>$hostile]); }
    if (in_array($variant,['APPLIED','CANCELLED','EXPIRED'],true)) { $batch['status']=$variant; $rows=[]; }
    $key=str_starts_with($page,'students/')?'students':(str_contains($page,'student-import')?'student-import':'enrollments');
    $ui=['contextType'=>'SCHOOL','schoolName'=>$hostile,'displayName'=>'ผู้ดูแลทดสอบ','csrfToken'=>'fixture-only',
        'currentKey'=>$key,'permissions'=>[],'sections'=>[['key'=>'students','label'=>'นักเรียน','items'=>[
            ['key'=>'students','label'=>'รายชื่อนักเรียน','url'=>'/students','detail'=>null],
            ['key'=>'enrollments','label'=>'การลงทะเบียน','url'=>'/academic/enrollments','detail'=>null],
            ['key'=>'student-import','label'=>'นำเข้านักเรียน','url'=>'/academic/student-import','detail'=>null],
        ]]]];
    $data=['csrfToken'=>'fixture-only','error'=>null,'canView'=>true,'canManage'=>true,'canManageEnrollment'=>true,
        'search'=>'TEST','students'=>[$student],'target'=>str_starts_with($page,'students/')?$student:$enrollment,
        'maskedNationalId'=>null,'history'=>$page==='students/show'?[array_replace($enrollment,['placements'=>$placements])]:$placements,
        'enrollments'=>[$enrollment],'years'=>[$year],'grades'=>[$grade],'classrooms'=>[$room],
        'year'=>$year,'grade'=>$grade,'yearId'=>1,'gradeId'=>1,'classroomId'=>1,'status'=>'ACTIVE',
        'mutable'=>!in_array($variant,['closed','terminal'],true),'batch'=>$batch,'rows'=>$rows];
    echo View::page($page,$data,['ui'=>$ui,'pageTitle'=>$pages[$page],'scripts'=>'<script src="/checks.js" defer></script>']); exit;
}
if ($path!=='/') { http_response_code(404); exit; }
$cases=array_map(static fn($page)=>[$page,'normal'],array_keys($pages));
foreach (['closed','terminal'] as $variant) { $cases[]=['academic/enrollments/edit',$variant]; }
foreach (['noop','error','APPLIED','CANCELLED','EXPIRED'] as $variant) { $cases[]=['academic/student-import/preview',$variant]; }
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><title>PP5 student workflow checks</title></head><body>
<h1>PP5 student workflow checks</h1><pre id="browser-results" role="status">Running…</pre>
<?php foreach ($cases as [$page,$variant]): foreach ([390,768,1024,1440] as $width): ?>
<iframe title="<?= $page.' '.$variant.' '.$width ?>" src="/frame?page=<?= urlencode($page) ?>&amp;variant=<?= $variant ?>" width="<?= $width ?>" height="844"></iframe>
<?php endforeach; endforeach; ?>
<script src="/checks.js" defer></script></body></html>
