<?php
declare(strict_types=1);

// Production overview with synthetic display data only, served by ui-cross-screen.php.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/htdocs/vendor/autoload.php';

use App\Support\View;

$page = $_GET['page'] ?? 'limited';
$admin = $page === 'admin';
$workspace = [
    'school' => ['id' => 1, 'name' => 'โรงเรียนทดสอบงานชั้นเรียน'],
    'classroom' => ['id' => 1, 'code' => 'P4-1', 'name' => 'ป.4/1', 'status' => 'ACTIVE'],
    'academicYear' => ['id' => 1, 'year_be' => 2569, 'status' => 'ACTIVE'],
    'gradeLevel' => ['id' => 4, 'name' => 'ประถมศึกษาปีที่ 4'],
    'capabilities' => ['overview' => true, 'students' => $admin, 'subjects' => $admin, 'teaching' => $admin, 'scores' => $page !== 'empty'],
    'links' => $admin ? [
        ['key' => 'students', 'label' => 'ดูนักเรียนในห้องนี้', 'url' => '/academic/enrollments?academic_year_id=1&grade_level_id=4&classroom_id=1&workspace_classroom_id=1'],
        ['key' => 'subjects', 'label' => 'ดูรายวิชาในปีการศึกษานี้', 'url' => '/academic/offerings?academic_year_id=1&workspace_classroom_id=1'],
        ['key' => 'teaching', 'label' => 'ดูครูผู้สอนในปีการศึกษานี้', 'url' => '/academic/teaching-assignments?academic_year_id=1&workspace_classroom_id=1'],
    ] : [],
    'gradebooks' => $page === 'empty' ? [] : [
        ['id' => 1, 'subject_code' => 'ว14101', 'subject_name' => str_repeat('วิทยาศาสตร์และเทคโนโลยี ', 6) . '<script>fixture</script>', 'term_no' => 1, 'status' => 'ACTIVE'],
    ],
];
// A legitimate empty classroom has student read authority but no accessible Gradebook.
if ($page === 'empty') {
    $workspace['capabilities']['students'] = true;
    $workspace['links'] = [['key' => 'students', 'label' => 'ดูนักเรียนในห้องนี้',
        'url' => '/academic/enrollments?workspace_classroom_id=1']];
}
$workspace['switchTargets'] = [['id' => 1, 'year_be' => 2569, 'code' => 'P4-1', 'name' => 'ป.4/1', 'url' => '/workspaces/classrooms/1']];
if ($admin) {
    for ($i = 2; $i <= 24; ++$i) {
        $workspace['switchTargets'][] = ['id' => $i, 'year_be' => 2568, 'code' => 'P4-' . $i,
            'name' => str_repeat('ห้องเรียนชื่อยาว ', 5), 'url' => '/workspaces/classrooms/' . $i];
    }
}
$workspace['navigation'] = App\Support\ClassroomWorkspaceNavigation::items($workspace, 'workspaces.classrooms');
$sections = [
    ['key' => 'overview', 'label' => 'ภาพรวม', 'items' => [
        ['key' => 'dashboard', 'label' => 'แดชบอร์ด', 'url' => '/dashboard', 'detail' => null],
    ]],
];
if ($page !== 'empty') {
    $sections[] = ['key' => 'teaching', 'label' => 'การเรียนการสอน', 'items' => [
        ['key' => 'gradebooks', 'label' => 'สมุดคะแนน', 'url' => '/gradebooks', 'detail' => null],
    ]];
}
echo View::page('workspaces/classroom/overview', ['workspace' => $workspace], [
    'documentTitle' => 'งานชั้นเรียน — ระบบ ปพ.5', 'pageTitle' => 'งานชั้นเรียน · ป.4/1',
    'ui' => ['contextType' => 'SCHOOL', 'schoolName' => $workspace['school']['name'], 'displayName' => 'ผู้ใช้ทดสอบ',
        'workspace' => $workspace, 'csrfToken' => 'synthetic-only', 'currentKey' => 'workspaces.classrooms', 'sections' => $sections],
]);
