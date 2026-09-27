<?php
declare(strict_types=1);

// Production overview with synthetic display data only, served by ui-cross-screen.php.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/htdocs/vendor/autoload.php';

use App\Support\View;

$page = $_GET['page'] ?? 'limited';
$roster = str_starts_with($page, 'roster-');
$subjectsPage = str_starts_with($page, 'subjects-');
$admin = in_array($page, ['admin', 'roster-normal', 'subjects-normal', 'subjects-empty', 'subjects-readonly'], true);
$workspace = [
    'school' => ['id' => 1, 'name' => 'โรงเรียนทดสอบงานชั้นเรียน'],
    'classroom' => ['id' => 1, 'code' => 'P4-1', 'name' => 'ป.4/1', 'status' => 'ACTIVE'],
    'academicYear' => ['id' => 1, 'year_be' => 2569, 'status' => 'ACTIVE'],
    'gradeLevel' => ['id' => 4, 'name' => 'ประถมศึกษาปีที่ 4'],
    'capabilities' => ['overview' => true, 'students' => $admin, 'subjects' => $admin, 'teaching' => $admin, 'scores' => $page !== 'empty'],
    'links' => $admin ? [
        ['key' => 'students', 'label' => 'ดูนักเรียนในห้องนี้', 'url' => '/workspaces/classrooms/1/students'],
        ['key' => 'subjects', 'label' => 'ดูรายวิชาและครูในห้องนี้', 'url' => '/workspaces/classrooms/1/subjects'],
    ] : [['key' => 'subjects', 'label' => 'ดูรายวิชาและครูในห้องนี้', 'url' => '/workspaces/classrooms/1/subjects']],
    'gradebooks' => $page === 'empty' ? [] : [
        ['id' => 1, 'subject_code' => 'ว14101', 'subject_name' => str_repeat('วิทยาศาสตร์และเทคโนโลยี ', 6) . '<script>fixture</script>', 'term_no' => 1, 'status' => 'ACTIVE'],
    ],
];
// A legitimate empty classroom has student read authority but no accessible Gradebook.
if ($page === 'empty') {
    $workspace['capabilities']['students'] = true;
    $workspace['links'] = [['key' => 'students', 'label' => 'ดูนักเรียนในห้องนี้',
        'url' => '/workspaces/classrooms/1/students']];
}
$workspace['switchTargets'] = [['id' => 1, 'year_be' => 2569, 'code' => 'P4-1', 'name' => 'ป.4/1', 'url' => '/workspaces/classrooms/1']];
if ($admin) {
    for ($i = 2; $i <= 24; ++$i) {
        $workspace['switchTargets'][] = ['id' => $i, 'year_be' => 2568, 'code' => 'P4-' . $i,
            'name' => str_repeat('ห้องเรียนชื่อยาว ', 5), 'url' => '/workspaces/classrooms/' . $i];
    }
}
if ($roster && !$admin) {
    $workspace['capabilities'] = ['overview' => true, 'students' => true, 'subjects' => false, 'teaching' => false, 'scores' => false];
    $workspace['links'] = [['key' => 'students', 'label' => 'ดูนักเรียนในห้องนี้', 'url' => '/workspaces/classrooms/1/students']];
}
if ($page === 'subjects-readonly') { $workspace['academicYear']['status'] = 'CLOSED'; }
$currentKey = $roster ? 'workspaces.classrooms.students' : ($subjectsPage ? 'workspaces.classrooms.subjects' : 'workspaces.classrooms');
$workspace['navigation'] = App\Support\ClassroomWorkspaceNavigation::items($workspace, $currentKey);
$students = [];
if ($roster && $page !== 'roster-empty') {
    for ($i = 1; $i <= 8; ++$i) {
        $students[] = ['studentId' => $i, 'enrollmentId' => $i, 'code' => sprintf('S%03d', $i),
            'name' => 'ด.ญ. ' . str_repeat('ชื่อยาวเพื่อทดสอบ ', $i === 1 ? 5 : 1) . 'นามสกุล <script>fixture</script>', 'status' => 'ACTIVE'];
    }
}

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
$offerings = $page === 'subjects-empty' ? [] : [[
    'id' => 1, 'subjectId' => 1, 'code' => 'ว14101',
    'name' => str_repeat('วิทยาศาสตร์และเทคโนโลยี ', 4) . '<script>fixture</script>',
    'term' => 1, 'status' => 'ACTIVE',
    'teachers' => [['id' => 1, 'name' => str_repeat('ครูชื่อยาว ', 5) . '<script>fixture</script>']],
    'teacherNamesVisible' => true, 'canOpenGradebook' => true,
    'canSetup' => $admin, 'canEdit' => $admin && $page !== 'subjects-readonly',
    'canAssign' => $admin && $page !== 'subjects-readonly',
]];
if ($page === 'subjects-normal') { $offerings[] = array_replace($offerings[0], ['id' => 2, 'term' => 2, 'teachers' => [], 'canOpenGradebook' => false]); }
echo View::page($roster ? 'workspaces/classroom/students' : ($subjectsPage ? 'workspaces/classroom/subjects' : 'workspaces/classroom/overview'),
    ['workspace' => $workspace, 'students' => $students, 'openYear' => $page !== 'subjects-readonly', 'canManage' => $admin, 'canAdd' => $admin, 'canImport' => $admin,
        'offerings' => $offerings, 'teacherChoices' => $admin ? [['user_role_assignment_id' => 1, 'display_name' => 'ครูทดสอบ']] : [],
        'canOpenOffering' => $admin && $page !== 'subjects-readonly', 'canManageAssignment' => $admin, 'canViewAll' => $admin, 'csrfToken' => 'synthetic-only'], [
    'documentTitle' => 'งานชั้นเรียน — ระบบ ปพ.5', 'pageTitle' => ($roster ? 'นักเรียน' : ($subjectsPage ? 'รายวิชาและครู' : 'งานชั้นเรียน')) . ' · ป.4/1',
    'ui' => ['contextType' => 'SCHOOL', 'schoolName' => $workspace['school']['name'], 'displayName' => 'ผู้ใช้ทดสอบ',
        'workspace' => $workspace, 'csrfToken' => 'synthetic-only', 'currentKey' => $currentKey, 'sections' => $sections],
]);
