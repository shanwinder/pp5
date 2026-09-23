<?php
declare(strict_types=1);

// Isolated production-layout smoke fixture: no database, authentication, or domain routes.
// php -S 127.0.0.1:18887 tests/Browser/ui-navigation.php
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/htdocs/vendor/autoload.php';

use App\Support\View;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = [
    '/assets/vendor/bootstrap-5.3.8.min.css' => ['/htdocs/assets/vendor/bootstrap-5.3.8.min.css', 'text/css'],
    '/assets/app.css' => ['/htdocs/assets/app.css', 'text/css'],
    '/assets/app.js' => ['/htdocs/assets/app.js', 'text/javascript'],
    '/checks.js' => ['/tests/Browser/ui-navigation.js', 'text/javascript'],
];
if (isset($assets[$path])) {
    [$file, $type] = $assets[$path];
    header('Content-Type: '.$type.'; charset=UTF-8');
    readfile(dirname(__DIR__, 2).$file);
    exit;
}
if (in_array($path, ['/frame', '/no-js'], true)) {
    $hostile = str_repeat('โรงเรียนภาษาไทยชื่อยาว', 8).'<script>alert("shell")</script>';
    $html = View::page('../../tests/Fixtures/views/page-content', ['message'=>'ทดสอบเมนู'], [
        'documentTitle'=>'ทดสอบเมนู ปพ.5', 'pageTitle'=>'แดชบอร์ด',
        'ui'=>['contextType'=>'SCHOOL', 'schoolName'=>$hostile, 'displayName'=>$hostile, 'csrfToken'=>'fixture-only',
            'currentKey'=>'dashboard', 'sections'=>[
                ['key'=>'overview', 'label'=>'ภาพรวม', 'items'=>[
                    ['key'=>'dashboard', 'label'=>'แดชบอร์ด', 'url'=>'#main-content', 'detail'=>null],
                ]],
                ['key'=>'teaching', 'label'=>'การเรียนการสอน', 'items'=>[
                    ['key'=>'gradebooks', 'label'=>'สมุดคะแนน', 'url'=>'#main-content', 'detail'=>null],
                ]],
            ]],
        'scripts'=>'<script src="/checks.js" defer></script>',
    ]);
    // Simulate app.js unavailable without altering production layout or its fallback markup.
    if ($path === '/no-js') { $html = str_replace('<script src="/assets/app.js" defer></script>', '', $html); }
    echo $html;
    exit;
}
if ($path !== '/') { http_response_code(404); exit; }
?>
<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PP5 navigation checks</title></head>
<body>
<h1>PP5 application shell checks</h1>
<pre id="browser-results" role="status">Running checks…</pre>
<?php foreach ([320, 390, 768, 1024, 1440] as $width): ?>
<h2><?= $width ?>px</h2><iframe title="<?= $width ?>px" src="/frame" width="<?= $width ?>" height="844"></iframe>
<?php endforeach; ?>
<h2>Without app.js</h2><iframe title="No enhancement" src="/no-js" width="320" height="844"></iframe>
<script src="/checks.js" defer></script>
</body>
</html>
