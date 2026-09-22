<?php
declare(strict_types=1);

// Isolated browser fixture, with no database or application session.
// Run: php -S 127.0.0.1:18887 tests/Browser/gradebook-autosave.php
// Open http://127.0.0.1:18887/ ; all browser assertions run automatically.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/htdocs/vendor/autoload.php';

use App\Support\View;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = [
    '/assets/vendor/htmx-2.0.8.min.js' => '/htdocs/assets/vendor/htmx-2.0.8.min.js',
    '/assets/gradebook.js' => '/htdocs/assets/gradebook.js',
    '/browser-tests.js' => '/tests/Browser/gradebook-autosave.js',
];
if (isset($assets[$path])) {
    header('Content-Type: text/javascript; charset=UTF-8'); readfile(dirname(__DIR__, 2) . $assets[$path]); exit;
}
if (preg_match('~^/hx/gradebook/1/components/(10|11)/enrollments/(1|2|3)/score$~', $path, $ids)) {
    usleep(150000); // Make focus changes and queued requests observable.
    $score = $_POST['score'] ?? '';
    if ($score === 'login') { echo '<!doctype html><html><body>Login page</body></html>'; exit; }
    if ($score === 'csrf') { http_response_code(419); echo 'CSRF token mismatch'; exit; }
    if ($score === 'failure') { http_response_code(500); echo 'Internal Server Error'; exit; }
    $normalized = ['' => '', '0' => '0.00', '5' => '5.00', '6' => '6.00', '01.50' => '1.50', '12.5' => '12.50'];
    if (!array_key_exists($score, $normalized)) {
        http_response_code(422); echo View::render('gradebook/score-error', ['message' => 'คะแนนไม่ถูกต้องหรือเกินคะแนนเต็ม']); exit;
    }
    header('X-Gradebook-Saved: 1');
    echo View::render('gradebook/score-cell', ['offeringId' => 1, 'componentId' => (int) $ids[1], 'enrollmentId' => (int) $ids[2], 'score' => $normalized[$score], 'saved' => true]);
    // Deliberately recognizable server summary. Business totals are covered by real DB HTTP tests.
    echo View::render('gradebook/row-summary', ['offeringId' => 1, 'outOfBand' => true, 'row' => [
        'enrollment_id' => (int) $ids[2], 'entered_score_total' => '19.25', 'configured_max_total' => '35.50',
        'entered_component_count' => 1, 'active_component_count' => 2, 'complete' => false,
    ]]);
    exit;
}
if ($path !== '/') { http_response_code(404); exit; }
$rows = [];
foreach ([1, 2, 3, 4] as $id) {
    $rows[] = ['enrollment_id' => $id, 'student_code' => 'STUDENT-' . $id, 'display_name' => 'นักเรียนทดสอบ ' . $id,
        'enrollment_status' => 'ACTIVE', 'row_type' => $id === 4 ? 'HISTORICAL' : 'CURRENT', 'scores' => [10 => null, 11 => null],
        'entered_score_total' => '0.00', 'configured_max_total' => '35.50', 'entered_component_count' => 0, 'active_component_count' => 2, 'complete' => false];
}
$html = View::render('gradebook/view', ['canScore' => true, 'csrfToken' => 'browser-fixture-token', 'gradebook' => [
    'offering' => ['id' => 1, 'year_be' => 2569, 'academic_year_status' => 'ACTIVE', 'classroom_code' => 'ROOM', 'classroom_name' => 'ห้องทดสอบ',
        'subject_code' => 'SUBJECT', 'subject_name' => 'วิชาทดสอบ', 'term_no' => 1, 'status' => 'ACTIVE'],
    'teachers' => [], 'components' => [['id' => 10, 'code' => 'WORK', 'name_th' => 'งาน', 'max_score' => '15.50'],
        ['id' => 11, 'code' => 'EXAM', 'name_th' => 'สอบ', 'max_score' => '20.00']],
    'configured_max_total' => '35.50', 'active_component_count' => 2, 'rows' => $rows,
]]);
echo str_replace('</body>', '<pre id="browser-results" role="status">Running browser checks…</pre><script src="/browser-tests.js" defer></script></body>', $html);
