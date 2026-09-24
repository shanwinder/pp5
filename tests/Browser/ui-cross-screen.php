<?php
declare(strict_types=1);

// Reuse isolated M6 production-view fixtures. No database or application session.
// php -S 127.0.0.1:18895 tests/Browser/ui-cross-screen.php
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = dirname(__DIR__, 2);
$assets = ['/assets/app.css'=>'htdocs/assets/app.css', '/assets/app.js'=>'htdocs/assets/app.js',
    '/assets/gradebook.js'=>'htdocs/assets/gradebook.js', '/assets/vendor/bootstrap-5.3.8.min.css'=>'htdocs/assets/vendor/bootstrap-5.3.8.min.css',
    '/assets/vendor/htmx-2.0.8.min.js'=>'htdocs/assets/vendor/htmx-2.0.8.min.js', '/matrix.js'=>'tests/Browser/ui-cross-screen.js'];
if (isset($assets[$path])) {
    header('Content-Type: '.(str_ends_with($path, '.css') ? 'text/css' : 'text/javascript').'; charset=UTF-8');
    readfile($root.'/'.$assets[$path]); exit;
}
// This matrix owns focus/scroll checks, so suppress the older fixtures' self-checks.
if ($path === '/checks.js') { header('Content-Type: text/javascript'); exit; }
if (str_starts_with($path, '/hx/gradebook/')) { require __DIR__.'/gradebook-autosave.php'; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Synthetic POST receipt: '.$path; exit;
}
if ($path === '/frame') {
    $fixtures = ['entry'=>'ui-entry.php', 'admin'=>'ui-administration.php', 'student'=>'ui-student-workflow.php', 'gradebook'=>'gradebook-autosave.php'];
    $fixture = $fixtures[$_GET['fixture'] ?? ''] ?? null;
    if ($fixture === null) { http_response_code(404); exit; }
    require __DIR__.'/'.$fixture; exit;
}
if ($path !== '/') { http_response_code(404); exit; }
$cases = [
    'login'=>['entry','login'], 'dashboard'=>['entry','dashboard'],
    'system schools'=>['admin','system/schools/index'], 'school users'=>['admin','admin/users/edit'],
    'offerings'=>['admin','academic/offerings/index'], 'academic form'=>['admin','academic/offerings/create'],
    'teaching assignment'=>['admin','academic/teaching-assignments/index'],
    'students'=>['student','students/index'], 'student detail'=>['student','students/show'],
    'enrollment edit'=>['student','academic/enrollments/edit'], 'import preview'=>['student','academic/student-import/preview'],
    'gradebook landing'=>['entry','gradebooks'], 'gradebook'=>['gradebook','editable'], 'gradebook setup'=>['gradebook','setup'],
    '403'=>['entry','403'], '404'=>['entry','404'],
];
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PP5 cross-screen matrix</title></head><body>
<h1>Task 8 cross-screen matrix</h1><pre id="browser-results" role="status">Running…</pre>
<p>Production templates with synthetic data only. Focus checks are scripted; native keyboard traversal is verified separately.</p>
<?php foreach ($cases as $name=>[$fixture,$page]): foreach ([[1440,900],[1024,768],[768,1024],[390,844]] as [$width,$height]): ?>
<iframe title="<?= $name.' '.$width.'x'.$height ?>" src="/frame?<?= htmlspecialchars(http_build_query(['fixture'=>$fixture, $fixture==='gradebook'?'mode':'page'=>$page, 'variant'=>'normal']), ENT_QUOTES, 'UTF-8') ?>" width="<?= $width ?>" height="<?= $height ?>"></iframe>
<?php endforeach; endforeach; ?>
<iframe title="no-JS navigation 390x844" src="/frame?fixture=student&amp;page=students/index&amp;variant=normal" width="390" height="844" sandbox="allow-same-origin allow-forms"></iframe>
<script src="/matrix.js" defer></script></body></html>
