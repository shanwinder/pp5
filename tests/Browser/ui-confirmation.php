<?php
declare(strict_types=1);

// Isolated production view and native POST receiver; no database, session or business writes.
// php -S 127.0.0.1:18891 tests/Browser/ui-confirmation.php
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
require dirname(__DIR__, 2).'/htdocs/vendor/autoload.php';

use App\Support\View;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (in_array($path, ['/assets/app.js', '/checks.js'], true)) {
    header('Content-Type: text/javascript; charset=UTF-8');
    readfile(dirname(__DIR__, 2).($path === '/checks.js' ? '/tests/Browser/ui-confirmation.js' : '/htdocs/assets/app.js'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><title>POST receipt</title><pre id="receipt">'.htmlspecialchars(
        json_encode(['method'=>$_SERVER['REQUEST_METHOD'], 'path'=>$path, 'fields'=>$_POST], JSON_UNESCAPED_UNICODE),
        ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'
    ).'</pre>';
    exit;
}
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><title>PP5 confirmation checks</title>
<?php if ($path === '/frame'): ?><script src="/assets/app.js" defer></script><?php endif; ?>
<script src="/checks.js" defer></script></head><body>
<?php if ($path === '/'): ?>
<h1>PP5 confirmation checks</h1><pre id="browser-results">Running…</pre>
<iframe title="Enhanced" src="/frame"></iframe><iframe title="Without app.js" src="/no-js"></iframe>
<?php else: ?>
<?= View::render('system/schools/index', [
    'permissions'=>['SYSTEM_SCHOOL_CREATE'=>true], 'canChangeStatus'=>true, 'error'=>null,
    'csrfToken'=>'fixture-only', 'schools'=>[['id'=>1,'school_code'=>'TEST','name_th'=>'<script>hostile</script>','status'=>'ACTIVE']],
]) ?>
<!-- An unmarked danger button must not opt an ordinary form into confirmation. -->
<form id="ordinary" method="post" action="/ordinary"><input name="value" value="unchanged"><button class="btn btn-danger" type="submit">Save</button></form>
<iframe name="receipt" title="Native POST receipt" src="about:blank"></iframe>
<?php endif; ?>
</body></html>
