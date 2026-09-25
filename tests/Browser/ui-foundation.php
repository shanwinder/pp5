<?php
declare(strict_types=1);

// Isolated CSS/shell fixture; no database, application session, or business routes.
// Run: php -S 127.0.0.1:18886 tests/Browser/ui-foundation.php
// Open http://127.0.0.1:18886/ for automatic viewport and keyboard checks.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/htdocs/vendor/autoload.php';

use App\Support\View;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = [
    '/assets/vendor/bootstrap-5.3.8.min.css' => ['/htdocs/assets/vendor/bootstrap-5.3.8.min.css', 'text/css'],
    '/assets/app.css' => ['/htdocs/assets/app.css', 'text/css'],
    '/assets/app.js' => ['/htdocs/assets/app.js', 'text/javascript'],
    '/browser-tests.js' => ['/tests/Browser/ui-foundation.js', 'text/javascript'],
];
if (isset($assets[$path])) {
    [$file, $type] = $assets[$path];
    header('Content-Type: ' . $type . '; charset=UTF-8');
    readfile(dirname(__DIR__, 2) . $file);
    exit;
}
if (in_array($path, ['/frame', '/no-js'], true)) {
    echo View::page('../../tests/Fixtures/views/ui-foundation', [], [
        'layout' => $path === '/no-js' ? 'guest' : 'app',
        'documentTitle' => 'ทดสอบพื้นฐาน UI ปพ.5',
        'scripts' => '<script src="/browser-tests.js" defer></script>',
    ]);
    exit;
}
if ($path !== '/') { http_response_code(404); exit; }
?>
<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PP5 UI foundation checks</title></head>
<body>
<h1>PP5 UI foundation checks</h1>
<pre id="browser-results" role="status">Running checks…</pre>
<?php foreach ([390, 768, 1024, 1440] as $width): ?>
<h2><?= $width ?>px</h2>
<iframe title="<?= $width ?>px test" src="/frame" width="<?= $width ?>" height="844"></iframe>
<?php endforeach; ?>
<h2>Navigation without app.js</h2>
<iframe title="No enhancement" src="/no-js" width="390" height="844"></iframe>
<script src="/browser-tests.js" defer></script>
</body>
</html>
