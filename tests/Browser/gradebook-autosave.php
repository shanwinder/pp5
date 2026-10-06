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
    '/assets/gradebook-grid.css' => '/htdocs/assets/gradebook-grid.css',
    '/assets/vendor/tabulator/tabulator.min.js' => '/htdocs/assets/vendor/tabulator/tabulator.min.js',
    '/assets/vendor/tabulator/tabulator.min.css' => '/htdocs/assets/vendor/tabulator/tabulator.min.css',
    '/assets/app.js' => '/htdocs/assets/app.js',
    '/assets/vendor/tabler/tabler-1.6.1.min.css' => '/htdocs/assets/vendor/tabler/tabler-1.6.1.min.css',
    '/assets/tabler-app.css' => '/htdocs/assets/tabler-app.css',
    '/assets/app-compat.css' => '/htdocs/assets/app-compat.css',
    '/assets/app.css' => '/htdocs/assets/app.css',
    '/assets/vendor/bootstrap-5.3.8.min.css' => '/htdocs/assets/vendor/bootstrap-5.3.8.min.css',
    '/layout-tests.js' => '/tests/Browser/gradebook-layout.js',
    '/grid-tests.js' => '/tests/Browser/gradebook-tabulator.js',
    '/grid-error-tests.js' => '/tests/Browser/gradebook-tabulator-errors.js',
    '/grid-race-tests.js' => '/tests/Browser/gradebook-tabulator-races.js',
    '/grid-workload-tests.js' => '/tests/Browser/gradebook-tabulator-workload.js',
    '/grid-parity-tests.js' => '/tests/Browser/gradebook-spreadsheet-parity.js',
    '/grid-direct-tests.js' => '/tests/Browser/gradebook-direct-entry.js',
    '/grid-visual-tests.js' => '/tests/Browser/gradebook-visual-stability.js',
    '/grid-golden-tests.js' => '/tests/Browser/gradebook-golden-journeys.js',
];
if (isset($assets[$path])) {
    header('Content-Type: '.(str_ends_with($path,'.css') ? 'text/css' : 'text/javascript').'; charset=UTF-8');
    $assetPath = dirname(__DIR__, 2) . $assets[$path];
    if ($path === '/assets/gradebook.js') {
        header('Cache-Control: no-store');
        // Fixture-only diagnostics expose private coordination state without changing production bytes.
        $source = file_get_contents($assetPath);
        $source = str_replace('  let table;', '  window.__gradebookTiming = { snapshot: () => ({ pendingSingles: state.pendingSingles.size, editing: Boolean(state.editing), pendingBatch: state.pendingBatch, uncertain: state.uncertain }), readyRejections: [] };' . "\n" . '  let table;', $source);
        $readyStart = strpos($source, '  const readyForBatch = () => {');
        $readyEnd = $readyStart === false ? false : strpos($source, '  const parseTsv =', $readyStart);
        if ($readyStart === false || $readyEnd === false) { http_response_code(500); exit; }
        $ready = substr($source, $readyStart, $readyEnd - $readyStart);
        $ready = str_replace('return false;',
            'window.__gradebookTiming.readyRejections.push({ pendingSingles: state.pendingSingles.size, editing: Boolean(state.editing) }); return false;',
            $ready);
        $source = substr($source, 0, $readyStart).$ready.substr($source, $readyEnd);
        echo $source; exit;
    }
    readfile($assetPath); exit;
}
if ($path === '/hx/gradebook/1/scores/batch') {
    usleep(150000);
    $matrix = json_decode($_POST['batch'] ?? '{}', true);
    $first = $matrix['values'][0][0] ?? '';
    if ($first === 'login') { echo '<html><body>Login</body></html>'; exit; }
    if ($first === 'unmarked') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['committed'=>true,'offering_id'=>1,'row_count'=>1,'column_count'=>1,'targeted_count'=>1,
            'changed_count'=>1,'cells'=>[['enrollment_id'=>1,'component_id'=>10,'score'=>'5.00','changed'=>true]],
            'rows'=>[['enrollment_id'=>1,'entered_score_total'=>'987.65','configured_max_total'=>'432.10',
                'entered_component_count'=>1,'active_component_count'=>2,'complete'=>false]]]); exit;
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    if (in_array($first, ['csrf','revoked','failure','refresh'], true)) {
        http_response_code(['csrf'=>419,'revoked'=>422,'failure'=>500,'refresh'=>409][$first]);
        echo json_encode(['committed'=>$first==='refresh','message'=>$first==='revoked' ? 'สิทธิ์ถูกเพิกถอน' : 'ทดสอบการปฏิเสธ / โหลดหน้าใหม่เพื่อตรวจสอบคะแนน'], JSON_UNESCAPED_UNICODE); exit;
    }
    $cells=[]; $rows=[];
    foreach ($matrix['enrollment_ids'] as $r=>$enrollment) {
        foreach ($matrix['component_ids'] as $c=>$component) {
            $value=$matrix['values'][$r][$c];
            if ($value !== '' && (!preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/', $value) || (float)$value > 20)) {
                http_response_code(422);
                echo json_encode(['committed'=>false,'message'=>'นักเรียนทดสอบ '.$enrollment.' · หัวข้อคะแนน '.$component.' คะแนนไม่ถูกต้องหรือเกินคะแนนเต็ม',
                    'location'=>['row'=>$r+1,'column'=>$c+1,'enrollment_id'=>$enrollment,'component_id'=>$component]],JSON_UNESCAPED_UNICODE); exit;
            }
            // Synthetic normalization only. Domain arithmetic is tested against MySQL in PHPUnit.
            $cells[]=['enrollment_id'=>$enrollment,'component_id'=>$component,'score'=>$value===''?null:number_format((float)$value,2,'.',''),'changed'=>true];
        }
        $rows[]=['enrollment_id'=>$enrollment,'entered_score_total'=>'987.65','configured_max_total'=>'432.10',
            'entered_component_count'=>2,'active_component_count'=>2,'complete'=>true];
    }
    header('X-Gradebook-Batch-Saved: 1');
    echo json_encode(['committed'=>true,'offering_id'=>1,'targeted_count'=>count($cells),'changed_count'=>count($cells),
        'row_count'=>count($rows),'column_count'=>count($matrix['component_ids']),'cells'=>$cells,'rows'=>$rows]); exit;
}
if (preg_match('~^/hx/gradebook/1/components/(10|11|12|13)/enrollments/(1|2|3|4)/score$~', $path, $ids)) {
    usleep(150000); // Make focus changes and queued requests observable.
    $score = $_POST['score'] ?? '';
    if ($score === 'login') { echo '<!doctype html><html><body>Login page</body></html>'; exit; }
    if ($score === 'unmarked') { echo View::render('gradebook/score-cell', ['offeringId'=>1,'componentId'=>(int)$ids[1],
        'enrollmentId'=>(int)$ids[2],'score'=>'5.00','saved'=>true]); exit; }
    if ($score === 'csrf') { http_response_code(419); echo 'CSRF token mismatch'; exit; }
    if ($score === 'revoked') { http_response_code(403); echo 'Score permission revoked'; exit; }
    if ($score === 'failure') { http_response_code(500); echo 'Internal Server Error'; exit; }
    if ($score === 'refresh') { http_response_code(409); echo View::render('gradebook/score-error', ['message'=>'บันทึกแล้วแต่โหลดผลล่าสุดไม่สำเร็จ']); exit; }
    if ($score === 'malformed') { header('X-Gradebook-Saved: 1'); echo '<div>missing score and summary</div>'; exit; }
    $normalized = ['' => '', '0' => '0.00', '5' => '5.00', '5.00' => '5.00', '6' => '6.00', '6.00' => '6.00', '7'=>'7.00', '8'=>'8.00',
        '1.50' => '1.50', '0.00' => '0.00', '01.50' => '1.50', '12.5' => '12.50', '12.50' => '12.50'];
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
if (!in_array($path, ['/', '/frame', '/matrix'], true)) { http_response_code(404); exit; }
if ($path === '/matrix') {
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><title>Gradebook layout checks</title></head><body><h1>Gradebook layout checks</h1><pre id="browser-results">Running…</pre>';
    foreach (['editable','active','error','readonly','setup','empty-setup','inactive-setup','error-setup','closed-setup','empty','no-components','nojs'] as $mode) {
        foreach ([390,768,1024,1440] as $width) {
            echo '<iframe title="'.$mode.' '.$width.'" src="/frame?mode='.$mode.'" width="'.$width.'" height="900"'.($mode === 'nojs' ? ' sandbox="allow-same-origin"' : '').'></iframe>';
        }
    }
    echo '<script src="/layout-tests.js" defer></script></body></html>'; exit;
}
$mode = $_GET['mode'] ?? 'editable';
$canScore = in_array($mode, ['editable','direct','visual','golden','active','error','empty','no-components','nojs','large','workload','stress'], true);
$long = $path === '/frame' ? str_repeat('นักเรียนภาษาไทยชื่อยาว', 4).'<script>hostile</script>' : 'นักเรียนทดสอบ';
$fixtureScores = [1 => [10 => null, 11 => '0.00'], 2 => [10 => '1.50', 11 => '6.00'],
    3 => [10 => '5.00', 11 => null], 4 => [10 => '7.25', 11 => null]];
if (in_array($mode, ['direct', 'visual', 'golden'], true)) {
    $fixtureScores[2][10] = '12.50';
    foreach ($fixtureScores as &$scores) { $scores[12] = null; }
    unset($scores);
}
if (in_array($mode, ['visual', 'golden'], true)) {
    foreach ($fixtureScores as &$scores) { $scores[13] = null; }
    unset($scores);
}
$rows = [];
foreach ([1, 2, 3, 4] as $id) {
    $rows[] = ['enrollment_id' => $id, 'student_code' => 'STUDENT-' . $id, 'display_name' => $long.' '.$id,
        'enrollment_status' => 'ACTIVE', 'row_type' => $id === 4 && $mode !== 'visual' ? 'HISTORICAL' : 'CURRENT', 'scores' => $fixtureScores[$id],
        'entered_score_total' => '0.00', 'configured_max_total' => '35.50', 'entered_component_count' => 1, 'active_component_count' => 2, 'complete' => false];
}
if ($mode === 'large') {
    $rows = [];
    for ($id = 1; $id <= 2001; $id++) {
        $rows[] = ['enrollment_id' => $id, 'student_code' => 'STUDENT-' . $id, 'display_name' => 'นักเรียนทดสอบ ' . $id,
            'enrollment_status' => 'ACTIVE', 'row_type' => 'CURRENT', 'scores' => [10 => null, 11 => null],
            'entered_score_total' => '0.00', 'configured_max_total' => '35.50', 'entered_component_count' => 0,
            'active_component_count' => 2, 'complete' => false];
    }
}
$offering = ['id' => 1, 'year_be' => 2569, 'academic_year_status' => $mode === 'closed-setup' ? 'CLOSED' : 'ACTIVE',
    'classroom_code' => 'ROOM', 'classroom_name' => 'ห้องทดสอบ', 'subject_code' => 'SUBJECT', 'subject_name' => 'วิชาทดสอบ', 'term_no' => 1, 'status' => 'ACTIVE'];
$components = [['id' => 10, 'code' => 'WORK', 'name_th' => $path === '/frame' ? str_repeat('หัวข้อคะแนนภาษาไทย',4) : 'งาน', 'max_score' => '15.50', 'sort_order'=>0, 'status'=>'ACTIVE'],
    ['id' => 11, 'code' => 'EXAM', 'name_th' => 'สอบ', 'max_score' => '20.00', 'sort_order'=>1, 'status'=>'ACTIVE']];
if (in_array($mode, ['direct', 'visual', 'golden'], true)) $components[] = ['id'=>12,'code'=>'THIRD','name_th'=>'หัวข้อที่สาม','max_score'=>'20.00','sort_order'=>2,'status'=>'ACTIVE'];
if (in_array($mode, ['visual', 'golden'], true)) $components[] = ['id'=>13,'code'=>'FOURTH','name_th'=>'หัวข้อที่สี่','max_score'=>'20.00','sort_order'=>3,'status'=>'ACTIVE'];
if ($mode === 'no-components') {
    $components = [];
    foreach ($rows as &$row) {
        $row['scores'] = [];
        $row['entered_score_total'] = '0.00';
        $row['configured_max_total'] = '0.00';
        $row['entered_component_count'] = 0;
        $row['active_component_count'] = 0;
        $row['complete'] = false;
    }
    unset($row);
}
if (in_array($mode, ['workload','stress'], true)) {
    $rowCount = $mode === 'stress' ? 100 : 35;
    $columnCount = $mode === 'stress' ? 40 : 20;
    $components = [];
    for ($c = 0; $c < $columnCount; $c++) {
        $components[] = ['id'=>1000+$c,'code'=>'C'.$c,'name_th'=>'หัวข้อคะแนน '.$c,
            'max_score'=>'20.00','sort_order'=>$c,'status'=>'ACTIVE'];
    }
    $rows = [];
    for ($id = 1; $id <= $rowCount; $id++) {
        $rows[] = ['enrollment_id'=>$id,'student_code'=>'STUDENT-'.$id,'display_name'=>'นักเรียนทดสอบ '.$id,
            'enrollment_status'=>'ACTIVE','row_type'=>'CURRENT',
            'scores'=>array_fill_keys(array_column($components,'id'),null),
            'entered_score_total'=>'0.00','configured_max_total'=>number_format($columnCount*20,2,'.',''),
            'entered_component_count'=>0,'active_component_count'=>$columnCount,'complete'=>false];
    }
}
$setupComponents = $mode === 'empty-setup' ? [] : $components;
if ($mode === 'inactive-setup') { $setupComponents[1]['status'] = 'INACTIVE'; }
$activeSetupCount = count(array_filter($setupComponents, static fn (array $item): bool => $item['status'] === 'ACTIVE'));
$setupTotal = $activeSetupCount === 0 ? '0.00' : ($activeSetupCount === 1 ? '15.50' : '35.50');
$gradebook = ['offering'=>$offering,'teachers'=>[], 'components'=>$components,
    'configured_max_total'=>in_array($mode,['workload','stress'],true) ? number_format(count($components)*20,2,'.','') : ($mode === 'no-components' ? '0.00' : '35.50'),
    'active_component_count'=>count($components),'rows'=>$mode === 'empty' ? [] : $rows];
$ui = ['contextType'=>'SCHOOL','schoolName'=>'โรงเรียนข้อมูลสังเคราะห์','displayName'=>'ผู้ใช้ทดสอบ','csrfToken'=>'browser-fixture-token',
    'currentKey'=>'gradebooks','permissions'=>[], 'sections'=>[['key'=>'teaching','label'=>'การเรียนการสอน','items'=>[
        ['key'=>'gradebooks','label'=>'สมุดคะแนน','url'=>'/gradebooks','detail'=>null]]]]];
$isSetup = in_array($mode, ['setup','empty-setup','inactive-setup','error-setup','closed-setup'], true);
$testScript = [
    'errors'=>'/grid-error-tests.js', 'races'=>'/grid-race-tests.js',
    'workload'=>'/grid-workload-tests.js', 'parity'=>'/grid-parity-tests.js', 'golden'=>'/grid-golden-tests.js',
    'direct'=>'/grid-direct-tests.js', 'visual'=>'/grid-visual-tests.js',
][$_GET['tests'] ?? ''] ?? '/grid-tests.js';
if (isset($assets[$testScript])) $testScript .= '?v='.filemtime(dirname(__DIR__, 2).$assets[$testScript]);
$html = View::page($isSetup ? 'gradebook/setup' : 'gradebook/view', [
    'canScore'=>$canScore,'canManageComponents'=>true,'csrfToken'=>'browser-fixture-token','gradebook'=>$gradebook,
    'offering'=>$offering, 'components'=>$setupComponents, 'canMutate'=>$isSetup && $mode !== 'closed-setup',
    'summary'=>['active_count'=>$activeSetupCount, 'inactive_count'=>count($setupComponents)-$activeSetupCount, 'active_max_total'=>$setupTotal],
    'historyIds'=>[10=>true], 'workspace'=>null, 'error'=>$mode === 'error-setup' ? 'คะแนนเต็มต้องมากกว่า 0' : null,
], ['ui'=>$ui, 'pageTitle'=>$isSetup ? 'การเก็บคะแนน' : 'สมุดคะแนน',
    'headAssets'=>View::render($canScore ? 'gradebook/scoring-assets' : 'gradebook/selection-assets'),
    'scripts'=>$path === '/' ? '<pre id="browser-results" role="status">Running browser checks…</pre><script src="'.$testScript.'" defer></script>' : '',
]);
if (($_GET['tests'] ?? '') === 'golden') $html = str_replace('/assets/gradebook.js?v=', '/assets/gradebook.js?fixture=timing&v=', $html);
echo $html;
