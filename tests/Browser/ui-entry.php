<?php
declare(strict_types=1);

// Isolated production-view fixture; no database/session or real business operations.
// php -S 127.0.0.1:18889 tests/Browser/ui-entry.php
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
require dirname(__DIR__, 2).'/htdocs/vendor/autoload.php';
use App\Support\View;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = [
    '/assets/vendor/bootstrap-5.3.8.min.css'=>['/htdocs/assets/vendor/bootstrap-5.3.8.min.css', 'text/css'],
    '/assets/vendor/tabler/tabler-1.6.1.min.css'=>['/htdocs/assets/vendor/tabler/tabler-1.6.1.min.css', 'text/css'],
    '/assets/tabler-app.css'=>['/htdocs/assets/tabler-app.css', 'text/css'],
    '/assets/app-compat.css'=>['/htdocs/assets/app-compat.css', 'text/css'],
    '/assets/app.css'=>['/htdocs/assets/app.css', 'text/css'],
    '/assets/app.js'=>['/htdocs/assets/app.js', 'text/javascript'],
    '/checks.js'=>['/tests/Browser/ui-entry.js', 'text/javascript'],
];
if (isset($assets[$path])) {
    [$file, $type] = $assets[$path]; header('Content-Type: '.$type.'; charset=UTF-8');
    readfile(dirname(__DIR__, 2).$file); exit;
}
if ($path === '/frame') {
    $page = $_GET['page'] ?? 'login';
    $hostile = str_repeat('โรงเรียนภาษาไทยชื่อยาว', 8).'<script>alert("entry")</script>';
    $offerings = [];
    foreach (['DRAFT', 'ACTIVE', 'CLOSED'] as $i=>$status) {
        $offerings[] = ['id'=>$i+1, 'classroom_id'=>$i+1, 'year_be'=>2569-$i, 'classroom_code'=>'ป.1/1', 'classroom_name'=>'ห้องเรียนภาษาไทย',
            'subject_code'=>'ว101', 'subject_name'=>$hostile, 'term_no'=>1, 'academic_year_status'=>$status,
            'status'=>$status === 'CLOSED' ? 'INACTIVE' : 'ACTIVE'];
    }
    $work = array_map(static function (array $offering): array {
        $offering['isHistorical'] = $offering['academic_year_status'] === 'CLOSED' || $offering['status'] !== 'ACTIVE';
        $offering['canScore'] = !$offering['isHistorical'];
        $offering['canSetupScoreStructure'] = !$offering['isHistorical'];
        return $offering;
    }, $offerings);
    if ($page === 'multiple') {
        $second = $work[0]; $second['id'] = 4; $second['term_no'] = 2; $work[] = $second;
        $other = $work[0]; $other['id'] = 5; $other['classroom_id'] = 5; $other['classroom_code'] = 'ป.1/2'; $work[] = $other;
    }
    if ($page === 'readonly') { foreach ($work as &$item) { $item['canScore'] = false; $item['canSetupScoreStructure'] = false; } unset($item); }
    if ($page === 'historical') { $work = [$work[2]]; }
    if ($page === 'empty') { $work = []; }
    $ui = ['contextType'=>'SCHOOL', 'schoolName'=>$hostile, 'displayName'=>$hostile, 'csrfToken'=>'fixture-only',
        'currentKey'=>$page === 'dashboard' ? 'dashboard' : 'gradebooks', 'gradebooks'=>$page === 'empty' ? [] : $offerings,
        'sections'=>[
            ['key'=>'overview','label'=>'ภาพรวม','items'=>[['key'=>'dashboard','label'=>'แดชบอร์ด','url'=>'/dashboard','detail'=>null]]],
            ['key'=>'teaching','label'=>'การเรียนการสอน','items'=>[['key'=>'gradebooks','label'=>'สมุดคะแนน','url'=>'/gradebooks','detail'=>null]]],
            ['key'=>'students','label'=>'นักเรียน','items'=>[['key'=>'students','label'=>'รายชื่อนักเรียน','url'=>'/students','detail'=>null]]],
        ]];
    $ui['classroomWorkspaces'] = $page === 'empty' ? [] : [['id'=>1, 'year_be'=>2569, 'code'=>'ป.1/1', 'name'=>$hostile, 'url'=>'/workspaces/classrooms/1']];
    $context = ['scripts'=>'<script src="/checks.js" defer></script>'];
    $html = match ($page) {
        'login' => View::page('auth/login', ['error'=>'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 'csrfToken'=>'fixture-only'],
            $context + ['layout'=>'guest', 'pageTitle'=>'เข้าสู่ระบบ ปพ.5', 'bodyClass'=>'pp5-guest']),
        '403', '404' => str_replace('</body>', '<script src="/checks.js" defer></script></body>', View::error((int) $page)),
        'dashboard' => View::page('dashboard/index', ['ui'=>$ui], $context + ['pageTitle'=>'แดชบอร์ด', 'ui'=>$ui]),
        'gradebooks', 'multiple', 'readonly', 'historical', 'empty' => View::page('gradebook/index', ['offerings'=>$work], $context + ['pageTitle'=>'งานสอนของฉัน', 'ui'=>$ui]),
        default => '',
    };
    echo $html; exit;
}
if ($path !== '/') { http_response_code(404); exit; }
?>
<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PP5 entry checks</title></head>
<body>
<h1>PP5 entry surface checks</h1><pre id="browser-results" role="status">Running checks…</pre>
<?php foreach (['login', '403', '404', 'dashboard', 'gradebooks', 'multiple', 'readonly', 'historical', 'empty'] as $page): ?>
<?php foreach ([390, 768, 1024, 1440] as $width): ?>
<iframe title="<?= $page ?> <?= $width ?>" src="/frame?page=<?= $page ?>" width="<?= $width ?>" height="844"></iframe>
<?php endforeach; endforeach; ?>
<script src="/checks.js" defer></script>
</body></html>
