<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$offering = $gradebook['offering'];
?>
<div class="pp5-gradebook-page">
  <div class="pp5-actions">
    <a class="btn btn-outline-secondary" href="/gradebooks">กลับงานสอนของฉัน</a>
    <?php if ($canManageComponents): ?><a class="btn btn-outline-secondary" href="/gradebook/<?= $escape($offering['id']) ?>/setup">ตั้งค่าการเก็บคะแนน</a><?php endif; ?>
    <span class="pp5-badge"><?= $canScore ? 'แก้ไขคะแนนได้' : 'อ่านอย่างเดียว' ?></span>
  </div>
  <p id="gradebook-guidance"><?= $canScore ? 'คลิกช่องคะแนนแล้วพิมพ์เพื่อแทนค่า จากนั้นกดลูกศรเพื่อบันทึกและย้ายช่อง · ดับเบิลคลิก, Enter หรือ F2 เพื่อแก้ไขค่าเดิม โดยลูกศรซ้ายขวาจะย้ายเคอร์เซอร์ · Enter/Shift+Enter ย้ายขึ้นลง · Tab/Shift+Tab ย้ายซ้ายขวาและออกจากตารางได้' : 'แสดงข้อมูลแบบอ่านอย่างเดียว' ?> · ลากเลือกช่วง แล้วกด Ctrl+C หรือ Cmd+C เพื่อคัดลอก<?= $canScore ? ' · Ctrl+V หรือ Cmd+V เพื่อวาง · Delete/Backspace เพื่อล้างช่วงที่เลือก · ใช้แผงด้านล่างเพื่อใส่คะแนนช่วง' : '' ?> · Escape ล้างช่วงที่เลือก</p>
  <p id="gradebook-range-status" class="visually-hidden" role="status" aria-live="polite"></p>
  <p>ช่องว่างหมายถึงยังไม่มีคะแนน ส่วน 0.00 คือคะแนนศูนย์ที่บันทึกแล้ว</p>
  <?php if ($canScore): ?>
    <p id="gradebook-batch-status" role="status" aria-live="polite" aria-atomic="true">วางคะแนนด้วย Ctrl+V หรือ Cmd+V เริ่มจากมุมซ้ายบนของช่วงที่เลือก · ช่องว่างในตารางที่วางจะล้างคะแนน</p>
    <input type="hidden" id="gradebook-csrf" name="_token" value="<?= $escape($csrfToken) ?>">
    <noscript><p>ต้องเปิดใช้งาน JavaScript เพื่อบันทึกคะแนนอัตโนมัติ</p></noscript>
  <?php endif; ?>
  <?= App\Support\View::render('gradebook/metadata', ['offering'=>$offering]) ?>
  <p class="pp5-gradebook-total">คะแนนเต็มรวมที่ใช้งานอยู่ <strong><?= $escape($gradebook['configured_max_total']) ?></strong></p>
  <h2>คะแนนรายหัวข้อ</h2>
  <?php if ($gradebook['components'] === []): ?><p>ยังไม่มีองค์ประกอบคะแนนที่เปิดใช้งาน จึงยังไม่ถือว่าคะแนนครบ</p><?php endif; ?>
  <?php if ($canScore): ?>
    <form id="gradebook-range-actions" class="pp5-gradebook-range-actions" aria-label="ใส่คะแนนในช่วงที่เลือก" hidden>
      <p id="gradebook-range-summary">ยังไม่ได้เลือกช่วงคะแนน</p>
      <label for="gradebook-fill-value">คะแนนสำหรับช่วงที่เลือก</label>
      <div class="pp5-gradebook-range-controls">
        <input id="gradebook-fill-value" class="form-control" type="text" inputmode="decimal" autocomplete="off" aria-describedby="gradebook-range-summary">
        <button id="gradebook-fill-submit" class="btn btn-primary" type="submit" disabled>ใส่คะแนนให้ช่วงที่เลือก</button>
        <button id="gradebook-clear-submit" class="btn btn-outline-secondary" type="button" disabled>ล้างคะแนนในช่วงที่เลือก</button>
      </div>
    </form>
  <?php endif; ?>
  <?php
    $gridBootstrap = [
      'offeringId' => (int) $offering['id'], 'canScore' => (bool) $canScore,
      'batchLimit' => \App\Services\GradebookScoreService::MAX_BATCH_CELLS,
      'components' => array_map(static fn (array $component): array => [
        'id' => (int) $component['id'], 'name' => $component['name_th'], 'max' => $component['max_score'],
      ], $gradebook['components']),
      'rows' => array_map(static fn (array $row): array => [
        'enrollmentId' => (int) $row['enrollment_id'], 'studentCode' => $row['student_code'],
        'name' => $row['display_name'], 'rowType' => $row['row_type'], 'status' => $row['enrollment_status'],
        'scores' => $row['scores'], 'total' => $row['entered_score_total'],
        'max' => $row['configured_max_total'], 'entered' => $row['entered_component_count'],
        'componentCount' => $row['active_component_count'], 'complete' => (bool) $row['complete'],
      ], $gradebook['rows']),
    ];
  ?>
  <script id="gradebook-grid-data" type="application/json"><?= json_encode($gridBootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) ?></script>
  <div id="gradebook-tabulator" class="pp5-gradebook-tabulator" role="region" aria-label="ตารางคะแนน" aria-describedby="gradebook-guidance" tabindex="0" hidden></div>
  <div class="pp5-table-scroll pp5-gradebook" data-offering-id="<?= $escape($offering['id']) ?>"<?php if ($canScore): ?> data-batch-url="/hx/gradebook/<?= $escape($offering['id']) ?>/scores/batch" data-batch-limit="<?= \App\Services\GradebookScoreService::MAX_BATCH_CELLS ?>"<?php endif; ?> role="region" aria-label="คะแนนรายองค์ประกอบ" aria-describedby="gradebook-guidance" tabindex="0">
    <table class="table pp5-table">
      <caption>คะแนนของรายชื่อปัจจุบันและประวัติการลงทะเบียน</caption>
      <thead><tr>
        <th class="pp5-gradebook-identity" scope="col">นักเรียน</th><th scope="col">ประเภทแถว</th>
        <?php foreach ($gradebook['components'] as $component): ?>
          <th id="<?= $escape('component-' . $component['id']) ?>" scope="col" data-component-id="<?= $escape($component['id']) ?>"><strong><?= $escape($component['name_th']) ?></strong><br>เต็ม <?= $escape($component['max_score']) ?><br><small class="pp5-gradebook-code">รหัส <?= $escape($component['code']) ?></small></th>
        <?php endforeach; ?>
        <th class="pp5-gradebook-summary" scope="col">คะแนนที่บันทึกรวม</th><th scope="col">คะแนนเต็มรวม</th><th scope="col">บันทึกแล้ว / องค์ประกอบทั้งหมด</th><th scope="col">ความครบถ้วน</th>
      </tr></thead>
      <tbody>
        <?php foreach ($gradebook['rows'] as $rowIndex => $row): ?>
          <tr class="<?= $row['row_type'] === 'HISTORICAL' ? 'pp5-historical' : 'pp5-current' ?>" data-enrollment-id="<?= $escape($row['enrollment_id']) ?>">
            <th id="<?= $escape('student-' . $row['enrollment_id']) ?>" class="pp5-gradebook-identity" scope="row"><span><?= $escape($row['student_code']) ?></span><span><?= $escape($row['display_name']) ?></span></th>
            <td><?= $row['row_type'] === 'CURRENT' ? 'รายชื่อปัจจุบัน' : 'ประวัติ — อ่านอย่างเดียว' ?> (<?= App\Support\View::render('ui/status', ['status'=>$row['enrollment_status'], 'kind'=>'enrollment']) ?>)</td>
            <?php foreach ($gradebook['components'] as $columnIndex => $component): ?>
              <td data-grid-score-cell data-grid-row="<?= (int) $rowIndex ?>" data-grid-column="<?= (int) $columnIndex ?>" data-grid-editable="<?= $canScore && $row['row_type'] === 'CURRENT' ? 'true' : 'false' ?>" data-component-id="<?= $escape($component['id']) ?>" data-enrollment-id="<?= $escape($row['enrollment_id']) ?>"><?php if ($canScore && $row['row_type'] === 'CURRENT'): ?>
                <?= \App\Support\View::render('gradebook/score-cell', [
                    'offeringId' => $offering['id'], 'componentId' => $component['id'], 'enrollmentId' => $row['enrollment_id'],
                    'score' => $row['scores'][$component['id']],
                ]) ?>
              <?php else: ?><span data-grid-value><?= $row['scores'][$component['id']] === null ? '' : $escape($row['scores'][$component['id']]) ?></span><?php endif; ?></td>
            <?php endforeach; ?>
            <?= \App\Support\View::render('gradebook/row-summary', ['offeringId' => $offering['id'], 'row' => $row]) ?>
          </tr>
        <?php endforeach; ?>
        <?php if ($gradebook['rows'] === []): ?><tr><td colspan="<?= $escape(6 + $gradebook['active_component_count']) ?>">ไม่มีรายชื่อนักเรียนหรือประวัติคะแนนในรายวิชานี้</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <details class="pp5-gradebook-teachers"><summary>ครูประจำวิชา</summary>
    <?php if ($gradebook['teachers'] === []): ?><p>ไม่มีการมอบหมายครูที่ใช้งานอยู่</p><?php endif; ?>
    <ul>
      <?php foreach ($gradebook['teachers'] as $teacher): ?>
        <li><?= $escape($teacher['display_name']) ?> — <?= App\Support\View::render('ui/status', ['status'=>$teacher['status'], 'kind'=>'assignment']) ?></li>
      <?php endforeach; ?>
    </ul>
  </details>
</div>
