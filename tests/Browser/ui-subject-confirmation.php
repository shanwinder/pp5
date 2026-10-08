<?php
declare(strict_types=1);

// Isolated browser check for the page script when HTMX is unavailable.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/assets/app.js' || $path === '/assets/subject-workspace.js') {
    header('Content-Type: text/javascript; charset=UTF-8');
    readfile(dirname(__DIR__, 2) . '/htdocs' . $path);
    exit;
}
if ($path === '/submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'POST received: ' . ($_POST['status'] ?? 'missing');
    exit;
}
if ($path !== '/') { http_response_code(404); exit; }
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><title>Subject confirmation fallback</title>
<script src="/assets/app.js" defer></script><script src="/assets/subject-workspace.js" defer></script></head>
<body><main class="pp5-subject-workspace"><h1>ทดสอบการยืนยันเมื่อ HTMX โหลดไม่ได้</h1>
<div id="subject-context"><form method="post" action="/submit" hx-post="/submit" hx-confirm="ยืนยันหยุดมอบหมายครูทดสอบ? ประวัติยังคงอยู่" data-confirm="ยืนยันหยุดมอบหมายครูทดสอบ? ประวัติยังคงอยู่">
<input type="hidden" name="status" value="INACTIVE"><button type="submit">หยุดมอบหมายครูทดสอบ</button></form></div>
</main></body></html>
