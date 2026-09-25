<?php
declare(strict_types=1);

// Isolated production templates, synthetic data only; no database, session or writes.
// php -S 127.0.0.1:18890 tests/Browser/ui-administration.php
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
require dirname(__DIR__, 2).'/htdocs/vendor/autoload.php';
use App\Support\View;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = [
    '/assets/vendor/bootstrap-5.3.8.min.css'=>['/htdocs/assets/vendor/bootstrap-5.3.8.min.css', 'text/css'],
    '/assets/app.css'=>['/htdocs/assets/app.css', 'text/css'],
    '/assets/app.js'=>['/htdocs/assets/app.js', 'text/javascript'],
    '/checks.js'=>['/tests/Browser/ui-administration.js', 'text/javascript'],
];
if (isset($assets[$path])) {
    [$file, $type] = $assets[$path]; header('Content-Type: '.$type.'; charset=UTF-8');
    readfile(dirname(__DIR__, 2).$file); exit;
}
$pages = ['system/schools/index'=>'จัดการโรงเรียน', 'system/schools/create'=>'เพิ่มโรงเรียน',
    'admin/users/index'=>'ผู้ใช้งาน', 'admin/users/create'=>'สร้างผู้ใช้', 'admin/users/edit'=>'จัดการผู้ใช้'];
foreach (['years'=>'ปีการศึกษา', 'classrooms'=>'ห้องเรียน', 'subjects'=>'รายวิชา', 'offerings'=>'การเปิดรายวิชา'] as $area=>$label) {
    foreach (['index'=>'', 'create'=>'เพิ่ม', 'edit'=>'รายละเอียด'] as $action=>$prefix) { $pages['academic/'.$area.'/'.$action] = $prefix.$label; }
}
$pages['academic/teaching-assignments/index'] = 'การมอบหมายครูประจำวิชา';
if ($path === '/frame') {
    $page = $_GET['page'] ?? '';
    if (!isset($pages[$page])) { http_response_code(404); exit; }
    $hostile = str_repeat('ชื่อภาษาไทยยาวสำหรับทดสอบ', 8).'<script>alert("admin")</script>';
    $permissions = array_fill_keys(['SYSTEM_SCHOOL_VIEW','SYSTEM_SCHOOL_CREATE','SYSTEM_SCHOOL_STATUS_MANAGE',
        'SCHOOL_USER_VIEW','SCHOOL_USER_CREATE','SCHOOL_USER_UPDATE','SCHOOL_MEMBERSHIP_STATUS_MANAGE',
        'SCHOOL_ROLE_MANAGE','SCHOOL_PASSWORD_RESET','ACADEMIC_SETUP_VIEW','ACADEMIC_YEAR_MANAGE',
        'CLASSROOM_MANAGE','SUBJECT_MANAGE','SUBJECT_OFFERING_MANAGE','TEACHING_ASSIGNMENT_MANAGE'], true);
    $year = ['id'=>1,'year_be'=>2569,'status'=>'DRAFT','start_date'=>'2026-05-16','end_date'=>'2027-03-31'];
    $entity = ['id'=>1,'user_id'=>1,'user_role_assignment_id'=>1,'academic_year_id'=>1,'year_be'=>2569,
        'status'=>'ACTIVE','academic_year_status'=>'DRAFT','grade_level_id'=>1,'grade_level_name'=>'ประถมศึกษาปีที่ 1',
        'code'=>'ป.1/1','name_th'=>$hostile,'school_code'=>'SCHOOL-01','username'=>'teacher01','display_name'=>$hostile,
        'email'=>'teacher@example.test','role_codes'=>['SUBJECT_TEACHER'],'classroom_id'=>1,'classroom_code'=>'ป.1/1',
        'classroom_name'=>$hostile,'classroom_status'=>'ACTIVE','subject_id'=>1,'subject_code'=>'ว101',
        'subject_name'=>$hostile,'subject_status'=>'ACTIVE','term_no'=>1,'offering_status'=>'ACTIVE',
        'teacher_display_name'=>$hostile];
    $ui = ['contextType'=>str_starts_with($page,'system/')?'SYSTEM':'SCHOOL','schoolName'=>$hostile,
        'displayName'=>'ผู้ดูแลทดสอบ','csrfToken'=>'fixture-only','currentKey'=>'current','permissions'=>$permissions,
        'sections'=>[['key'=>'current','label'=>'การจัดการ','items'=>[['key'=>'current','label'=>$pages[$page],'url'=>'#main-content','detail'=>null]]]]];
    $data = ['permissions'=>$permissions,'csrfToken'=>'fixture-only','error'=>null,'values'=>[],
        'target'=>str_contains($page,'years/')?$year:$entity,'years'=>[$year,array_replace($year,['id'=>2,'year_be'=>2568,'status'=>'CLOSED'])],
        'selectedYear'=>$year,'canCreate'=>true,'canChangeStatus'=>true,'canManageYears'=>true,'canManageGradebookComponents'=>true,
        'schools'=>[$entity],'members'=>[$entity],'classrooms'=>[$entity],'subjects'=>[$entity],'offerings'=>[$entity],
        'teachers'=>[$entity],'assignments'=>[$entity],'grades'=>[['id'=>1,'name_th'=>'ประถมศึกษาปีที่ 1']],
        'roles'=>[['code'=>'SUBJECT_TEACHER','name_th'=>'ครูประจำวิชา'],['code'=>'VIEWER','name_th'=>'ผู้ดูข้อมูล']],
        'roleCodes'=>['SUBJECT_TEACHER']];
    echo View::page($page, $data, ['ui'=>$ui,'pageTitle'=>$pages[$page],
        'scripts'=>'<script src="/checks.js" defer></script>']); exit;
}
if ($path !== '/') { http_response_code(404); exit; }
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><title>PP5 administration checks</title></head><body>
<h1>PP5 administration checks</h1><pre id="browser-results" role="status">Running…</pre>
<?php foreach ($pages as $page=>$title): foreach ([390,768,1024,1440] as $width): ?>
<iframe title="<?= $page ?> <?= $width ?>" src="/frame?page=<?= urlencode($page) ?>" width="<?= $width ?>" height="844"></iframe>
<?php endforeach; endforeach; ?>
<script src="/checks.js" defer></script></body></html>
