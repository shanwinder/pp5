<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$offering = $gradebook['offering'];
$guidance = ($canScore
    ? 'คลิกช่องคะแนนแล้วพิมพ์เพื่อแทนค่า จากนั้นกดลูกศรเพื่อบันทึกและย้ายช่อง · ดับเบิลคลิก, Enter หรือ F2 เพื่อแก้ไขค่าเดิม โดยลูกศรซ้ายขวาจะย้ายเคอร์เซอร์ · Enter/Shift+Enter ย้ายลง/ขึ้น · Tab/Shift+Tab ย้ายขวา/ซ้ายและออกจากตารางได้ · Escape ในช่องแก้ไขยกเลิกโดยไม่บันทึก'
    : 'แสดงข้อมูลแบบอ่านอย่างเดียว')
    . ' · ลากเลือกช่วง แล้วกด Ctrl+C หรือ Cmd+C เพื่อคัดลอก'
    . ($canScore ? ' · Ctrl+V หรือ Cmd+V เพื่อวางจากมุมซ้ายบน เลือกหลายช่องเพื่อวางรูปแบบซ้ำให้เต็มช่วง ช่วงที่เลือกต้องไม่เล็กกว่าต้นทาง · ช่องว่างที่วางจะล้างคะแนน · Delete/Backspace เพื่อล้างช่วงที่เลือก' : '')
    . ' · Escape นอกช่องแก้ไขล้างช่วงที่เลือก · ช่องว่างคือยังไม่มีคะแนน ส่วน 0.00 คือศูนย์ที่บันทึกแล้ว';
?>
<div class="pp5-gradebook-page">
  <?= App\Support\View::render('gradebook/context', [
      'gradebook' => $gradebook, 'canScore' => $canScore, 'canManageComponents' => $canManageComponents,
      'workspace' => $workspace ?? null, 'subjectsUrl' => $subjectsUrl ?? null, 'guidance' => $guidance,
  ]) ?>
  <p id="gradebook-guidance" class="visually-hidden"><?= $guidance ?></p>
  <p id="gradebook-range-status" class="visually-hidden" role="status" aria-live="polite"></p>
  <?php if ($canScore): ?>
    <p id="gradebook-batch-status" class="pp5-gradebook-feedback small mb-2" role="status" aria-live="polite" aria-atomic="true">พร้อมกรอกคะแนน</p>
    <input type="hidden" id="gradebook-csrf" name="_token" value="<?= $escape($csrfToken) ?>">
    <noscript><p>ต้องเปิดใช้งาน JavaScript เพื่อบันทึกคะแนนอัตโนมัติ</p></noscript>
  <?php endif; ?>
  <?php if ($gradebook['components'] === []): ?><p>ยังไม่มีองค์ประกอบคะแนนที่เปิดใช้งาน จึงยังไม่ถือว่าคะแนนครบ</p><?php endif; ?>
  <?php
    $gridBootstrap = [
      'offeringId' => (int) $offering['id'], 'canScore' => (bool) $canScore,
      'batchLimit' => \App\Services\GradebookScoreService::MAX_BATCH_CELLS,
      'components' => array_map(static fn (array $component): array => [
        'id' => (int) $component['id'], 'name' => $component['name_th'], 'max' => $component['max_score'],
      ], $gradebook['components']),
      'rows' => array_map(static fn (array $row): array => [
        'enrollmentId' => (int) $row['enrollment_id'], 'studentCode' => (string) $row['student_code'],
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
        <th class="pp5-gradebook-number" scope="col">ลำดับ</th><th scope="col">เลขประจำตัว</th><th class="pp5-gradebook-identity" scope="col">ชื่อ-สกุล</th>
        <?php foreach ($gradebook['components'] as $component): ?>
          <th id="<?= $escape('component-' . $component['id']) ?>" scope="col" data-component-id="<?= $escape($component['id']) ?>"><strong><?= $escape($component['name_th']) ?></strong><br>เต็ม <?= $escape($component['max_score']) ?><br><small class="pp5-gradebook-code">รหัส <?= $escape($component['code']) ?></small></th>
        <?php endforeach; ?>
        <th class="pp5-gradebook-summary" scope="col">คะแนนที่บันทึก</th>
      </tr></thead>
      <tbody>
        <?php foreach ($gradebook['rows'] as $rowIndex => $row): ?>
          <tr class="<?= $row['row_type'] === 'HISTORICAL' ? 'pp5-historical' : 'pp5-current' ?>" data-enrollment-id="<?= $escape($row['enrollment_id']) ?>" data-row-type="<?= $escape($row['row_type']) ?>" data-enrollment-status="<?= $escape($row['enrollment_status']) ?>">
            <td class="pp5-gradebook-number"><?= (int) $rowIndex + 1 ?></td>
            <td><?= $escape($row['student_code']) ?></td>
            <th id="<?= $escape('student-' . $row['enrollment_id']) ?>" class="pp5-gradebook-identity" scope="row"><?= $escape($row['display_name']) ?><?php if ($row['row_type'] === 'HISTORICAL'): ?><span class="visually-hidden"> · แถวประวัติ อ่านอย่างเดียว</span><?php endif; ?></th>
            <?php foreach ($gradebook['components'] as $columnIndex => $component): ?>
              <td headers="<?= $escape('student-' . $row['enrollment_id'] . ' component-' . $component['id']) ?>" data-grid-score-cell data-grid-row="<?= (int) $rowIndex ?>" data-grid-column="<?= (int) $columnIndex ?>" data-grid-editable="<?= $canScore && $row['row_type'] === 'CURRENT' ? 'true' : 'false' ?>" data-component-id="<?= $escape($component['id']) ?>" data-enrollment-id="<?= $escape($row['enrollment_id']) ?>"><?php if ($canScore && $row['row_type'] === 'CURRENT'): ?>
                <?= \App\Support\View::render('gradebook/score-cell', [
                    'offeringId' => $offering['id'], 'componentId' => $component['id'], 'enrollmentId' => $row['enrollment_id'],
                    'score' => $row['scores'][$component['id']],
                ]) ?>
              <?php else: ?><span data-grid-value><?= $row['scores'][$component['id']] === null ? '' : $escape($row['scores'][$component['id']]) ?></span><?php endif; ?></td>
            <?php endforeach; ?>
            <?= \App\Support\View::render('gradebook/row-summary', ['offeringId' => $offering['id'], 'row' => $row]) ?>
          </tr>
        <?php endforeach; ?>
        <?php if ($gradebook['rows'] === []): ?><tr><td colspan="<?= $escape(4 + $gradebook['active_component_count']) ?>">ไม่มีรายชื่อนักเรียนหรือประวัติคะแนนในรายวิชานี้</td></tr><?php endif; ?>
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
