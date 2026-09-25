<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$offering = $gradebook['offering'];
?>
<div class="pp5-gradebook-page">
  <div class="pp5-actions">
    <a class="btn btn-outline-secondary" href="/gradebooks">กลับรายการสมุดคะแนน</a>
    <?php if ($canManageComponents): ?><a class="btn btn-outline-secondary" href="/gradebook/<?= $escape($offering['id']) ?>/setup">ตั้งค่าโครงสร้างคะแนน</a><?php endif; ?>
    <span class="pp5-badge"><?= $canScore ? 'แก้ไขคะแนนได้' : 'อ่านอย่างเดียว' ?></span>
  </div>
  <p id="gradebook-guidance"><?= $canScore ? 'พิมพ์คะแนนแล้วออกจากช่องเพื่อบันทึก · Enter ไปยังนักเรียนคนถัดไปในหัวข้อคะแนนเดิม · Tab/Shift+Tab ใช้งานตามปกติ' : 'แสดงข้อมูลแบบอ่านอย่างเดียว' ?></p>
  <p>ช่องว่างหมายถึงยังไม่มีคะแนน ส่วน 0.00 คือคะแนนศูนย์ที่บันทึกแล้ว</p>
  <?php if ($canScore): ?>
    <input type="hidden" id="gradebook-csrf" name="_token" value="<?= $escape($csrfToken) ?>">
    <noscript><p>ต้องเปิดใช้งาน JavaScript เพื่อบันทึกคะแนนอัตโนมัติ</p></noscript>
  <?php endif; ?>
  <?= App\Support\View::render('gradebook/metadata', ['offering'=>$offering]) ?>
  <p>คะแนนเต็มรวม <?= $escape($gradebook['configured_max_total']) ?></p>
  <h2>ครูประจำวิชา</h2>
  <?php if ($gradebook['teachers'] === []): ?><p>ไม่มีการมอบหมายครูที่ใช้งานอยู่</p><?php endif; ?>
  <ul>
    <?php foreach ($gradebook['teachers'] as $teacher): ?>
      <li><?= $escape($teacher['display_name']) ?> — <?= App\Support\View::render('ui/status', ['status'=>$teacher['status'], 'kind'=>'assignment']) ?></li>
    <?php endforeach; ?>
  </ul>
  <h2>คะแนนรายองค์ประกอบ</h2>
  <?php if ($gradebook['components'] === []): ?><p>ยังไม่มีองค์ประกอบคะแนนที่เปิดใช้งาน จึงยังไม่ถือว่าคะแนนครบ</p><?php endif; ?>
  <div class="pp5-table-scroll pp5-gradebook" role="region" aria-label="คะแนนรายองค์ประกอบ" aria-describedby="gradebook-guidance" tabindex="0">
    <table class="table pp5-table">
      <caption>คะแนนของรายชื่อปัจจุบันและประวัติการลงทะเบียน</caption>
      <thead><tr>
        <th class="pp5-gradebook-identity" scope="col">นักเรียน</th><th scope="col">ประเภทแถว</th>
        <?php foreach ($gradebook['components'] as $component): ?>
          <th id="<?= $escape('component-' . $component['id']) ?>" scope="col" data-component-id="<?= $escape($component['id']) ?>"><?= $escape($component['code'] . ' — ' . $component['name_th']) ?><br>เต็ม <?= $escape($component['max_score']) ?></th>
        <?php endforeach; ?>
        <th class="pp5-gradebook-summary" scope="col">คะแนนที่บันทึกรวม</th><th scope="col">คะแนนเต็มรวม</th><th scope="col">บันทึกแล้ว / องค์ประกอบทั้งหมด</th><th scope="col">ความครบถ้วน</th>
      </tr></thead>
      <tbody>
        <?php foreach ($gradebook['rows'] as $row): ?>
          <tr class="<?= $row['row_type'] === 'HISTORICAL' ? 'pp5-historical' : 'pp5-current' ?>" data-enrollment-id="<?= $escape($row['enrollment_id']) ?>">
            <th id="<?= $escape('student-' . $row['enrollment_id']) ?>" class="pp5-gradebook-identity" scope="row"><span><?= $escape($row['student_code']) ?></span><span><?= $escape($row['display_name']) ?></span></th>
            <td><?= $row['row_type'] === 'CURRENT' ? 'รายชื่อปัจจุบัน' : 'ประวัติ — อ่านอย่างเดียว' ?> (<?= App\Support\View::render('ui/status', ['status'=>$row['enrollment_status'], 'kind'=>'enrollment']) ?>)</td>
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
        <?php if ($gradebook['rows'] === []): ?><tr><td colspan="<?= $escape(6 + $gradebook['active_component_count']) ?>">ไม่มีรายชื่อนักเรียนหรือประวัติคะแนนในรายวิชานี้</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
