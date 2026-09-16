<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$offering = $gradebook['offering'];
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>สมุดคะแนน — ระบบ ปพ.5</title>
  <?php if ($canScore): ?>
    <meta name="htmx-config" content='{"allowEval":false,"allowScriptTags":false,"defaultSettleDelay":0,"timeout":15000}'>
    <script src="/assets/vendor/htmx-2.0.8.min.js" defer></script>
    <script src="/assets/gradebook.js" defer></script>
  <?php endif; ?>
</head>
<body>
<main class="container">
  <h1>สมุดคะแนน</h1>
  <p><a href="/dashboard">กลับแดชบอร์ด</a></p>
  <p><?= $canScore ? 'กรอกคะแนนแล้วกด Enter หรือ Tab หรือออกจากช่องเพื่อบันทึก Enter ย้ายไปแถวถัดไปในองค์ประกอบเดียวกัน' : 'แสดงข้อมูลแบบอ่านอย่างเดียว' ?> ช่องว่างหมายถึงยังไม่มีคะแนน ส่วน 0.00 คือคะแนนศูนย์ที่บันทึกแล้ว</p>
  <?php if ($canScore): ?>
    <input type="hidden" id="gradebook-csrf" name="_token" value="<?= $escape($csrfToken) ?>">
    <noscript><p>ต้องเปิดใช้งาน JavaScript เพื่อบันทึกคะแนนอัตโนมัติ</p></noscript>
  <?php endif; ?>
  <dl>
    <dt>ปีการศึกษา</dt><dd><?= $escape($offering['year_be'] . ' — ' . $offering['academic_year_status']) ?></dd>
    <dt>ห้องเรียน</dt><dd><?= $escape($offering['classroom_code'] . ' — ' . $offering['classroom_name']) ?></dd>
    <dt>รายวิชา</dt><dd><?= $escape($offering['subject_code'] . ' — ' . $offering['subject_name']) ?></dd>
    <dt>ภาคเรียน</dt><dd><?= $escape($offering['term_no']) ?></dd>
    <dt>สถานะการเปิดรายวิชา</dt><dd><?= $escape($offering['status']) ?></dd>
    <dt>คะแนนเต็มรวม</dt><dd><?= $escape($gradebook['configured_max_total']) ?></dd>
  </dl>
  <h2>ครูประจำวิชา</h2>
  <?php if ($gradebook['teachers'] === []): ?><p>ไม่มีการมอบหมายครูที่ใช้งานอยู่</p><?php endif; ?>
  <ul>
    <?php foreach ($gradebook['teachers'] as $teacher): ?>
      <li><?= $escape($teacher['display_name'] . ' — ' . $teacher['status']) ?></li>
    <?php endforeach; ?>
  </ul>
  <h2>คะแนนรายองค์ประกอบ</h2>
  <?php if ($gradebook['components'] === []): ?><p>ยังไม่มีองค์ประกอบคะแนนที่เปิดใช้งาน จึงยังไม่ถือว่าคะแนนครบ</p><?php endif; ?>
  <div class="table-responsive" style="overflow-x: auto">
    <table class="table">
      <thead><tr>
        <th scope="col">รหัสนักเรียน</th><th scope="col">ชื่อ–นามสกุล</th><th scope="col">ประเภทแถว</th>
        <?php foreach ($gradebook['components'] as $component): ?>
          <th id="<?= $escape('component-' . $component['id']) ?>" scope="col" data-component-id="<?= $escape($component['id']) ?>"><?= $escape($component['code'] . ' — ' . $component['name_th']) ?><br>เต็ม <?= $escape($component['max_score']) ?></th>
        <?php endforeach; ?>
        <th scope="col">คะแนนที่บันทึกรวม</th><th scope="col">คะแนนเต็มรวม</th><th scope="col">บันทึกแล้ว / องค์ประกอบทั้งหมด</th><th scope="col">ความครบถ้วน</th>
      </tr></thead>
      <tbody>
        <?php foreach ($gradebook['rows'] as $row): ?>
          <tr data-enrollment-id="<?= $escape($row['enrollment_id']) ?>">
            <th id="<?= $escape('student-' . $row['enrollment_id']) ?>" scope="row"><?= $escape($row['student_code']) ?></th>
            <td><?= $escape($row['display_name']) ?></td>
            <td><?= $row['row_type'] === 'CURRENT' ? 'รายชื่อปัจจุบัน' : 'ประวัติ — อ่านอย่างเดียว' ?> (<?= $escape($row['enrollment_status']) ?>)</td>
            <?php foreach ($gradebook['components'] as $component): ?>
              <td data-component-id="<?= $escape($component['id']) ?>"><?php if ($canScore && $row['row_type'] === 'CURRENT'): ?>
                <?= \App\Support\View::render('gradebook/score-cell', [
                    'offeringId' => $offering['id'], 'componentId' => $component['id'], 'enrollmentId' => $row['enrollment_id'],
                    'score' => $row['scores'][$component['id']],
                ]) ?>
              <?php else: ?><?= $row['scores'][$component['id']] === null ? '' : $escape($row['scores'][$component['id']]) ?><?php endif; ?></td>
            <?php endforeach; ?>
            <?= \App\Support\View::render('gradebook/row-summary', ['offeringId' => $offering['id'], 'row' => $row]) ?>
          </tr>
        <?php endforeach; ?>
        <?php if ($gradebook['rows'] === []): ?><tr><td colspan="<?= $escape(7 + $gradebook['active_component_count']) ?>">ไม่มีรายชื่อนักเรียนหรือประวัติคะแนนในรายวิชานี้</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
