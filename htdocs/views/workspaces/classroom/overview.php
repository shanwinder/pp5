<?php
use App\Support\View;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$base = '/workspaces/classrooms/' . (int) $workspace['classroom']['id'];
?>
<div class="pp5-admin-page pp5-classroom-overview">
  <section aria-labelledby="workspace-overview" class="pp5-workspace-intro">
    <div>
      <p class="pp5-section-kicker">พื้นที่ทำงานของห้องนี้</p>
      <h2 id="workspace-overview">เริ่มงานจากสิ่งที่ต้องดูแล</h2>
      <p>เลือกงานที่ต้องทำใน <?= $escape($workspace['classroom']['name']) ?> แล้วกลับมาดูภาพรวมได้ทุกเมื่อ</p>
    </div>
    <div class="pp5-workspace-state" aria-label="สถานะบริบท">
      <span>ปีการศึกษา <?= View::render('ui/status', ['status' => $workspace['academicYear']['status'], 'kind' => 'academic-year']) ?></span>
      <span>ห้องเรียน <?= View::render('ui/status', ['status' => $workspace['classroom']['status'], 'kind' => 'entity']) ?></span>
    </div>
  </section>

  <div class="pp5-task-zones">
    <?php if ($workspace['capabilities']['students']): ?>
    <section class="pp5-task-zone pp5-task-zone--students" aria-labelledby="workspace-students-zone">
      <p class="pp5-task-zone-label">01 / นักเรียน</p>
      <h2 id="workspace-students-zone">ดูแลรายชื่อนักเรียน</h2>
      <p class="pp5-task-zone-stat"><?= $studentCount === null ? 'รายชื่อในห้อง' : $escape($studentCount) . ' คน' ?></p>
      <p>ดูสถานะ เลือกนักเรียน และเปิดการจัดการจากรายชื่อในห้อง</p>
      <a class="btn btn-primary" href="<?= $escape($base) ?>/students">จัดการนักเรียน</a>
    </section>
    <?php endif; ?>

    <?php if ($workspace['capabilities']['subjects'] || $workspace['capabilities']['teaching'] || $workspace['capabilities']['scores']): ?>
    <section class="pp5-task-zone pp5-task-zone--subjects" aria-labelledby="workspace-subjects-zone">
      <p class="pp5-task-zone-label">02 / รายวิชาและครู</p>
      <h2 id="workspace-subjects-zone">งานสอนของห้องนี้</h2>
      <p class="pp5-task-zone-stat"><?= $offeringCount === null ? 'รายการที่เข้าถึงได้' : $escape($offeringCount) . ' รายการ' ?></p>
      <p>รายวิชาที่เปิดสอนในแต่ละภาคเรียนและครูผู้สอนที่คุณเข้าถึงได้</p>
      <a class="pp5-task-zone-action" href="<?= $escape($base) ?>/subjects">ดูรายวิชาและครู <span aria-hidden="true">→</span></a>
    </section>
    <?php endif; ?>

    <?php if ($workspace['capabilities']['scores']): ?>
    <section class="pp5-task-zone pp5-task-zone--scores" aria-labelledby="workspace-score-zone">
      <p class="pp5-task-zone-label">03 / คะแนน</p>
      <h2 id="workspace-score-zone">สมุดคะแนนที่เปิดได้</h2>
      <p class="pp5-task-zone-stat"><?= $escape(count($workspace['gradebooks'])) ?> รายการ</p>
      <?php if ($configuredCount !== null): ?><p><?= $escape($configuredCount) ?> รายการมีหัวข้อคะแนนที่ใช้งานอยู่</p><?php endif; ?>
      <a class="pp5-task-zone-action" href="#workspace-scores">เลือกสมุดคะแนน <span aria-hidden="true">↓</span></a>
    </section>
    <?php endif; ?>
  </div>

  <?php if ($workspace['capabilities']['scores']): ?>
  <section id="workspace-scores" aria-labelledby="workspace-scores-heading" class="pp5-workspace-data">
    <div class="pp5-workspace-data-heading">
      <div><p class="pp5-section-kicker">เลือกงานคะแนน</p><h2 id="workspace-scores-heading">สมุดคะแนนที่คุณเข้าถึงได้</h2></div>
      <p>เลือกแถวเพื่อเปิดสมุดคะแนนของรายวิชาและภาคเรียน</p>
    </div>
    <?php if ($workspace['gradebooks'] === []): ?>
      <p class="pp5-empty-state">ยังไม่มีสมุดคะแนนที่คุณเข้าถึงได้ในห้องนี้</p>
    <?php else: ?>
      <div class="pp5-table-scroll" role="region" aria-label="สมุดคะแนนในห้องนี้" tabindex="0">
        <table class="table pp5-table">
          <caption>รายวิชาที่คุณเปิดดูคะแนนได้ในห้องนี้</caption>
          <thead><tr><th scope="col">รายวิชา</th><th scope="col">ภาคเรียน</th><th scope="col">สถานะรายวิชา</th><th scope="col">การทำงาน</th></tr></thead>
          <tbody>
          <?php foreach ($workspace['gradebooks'] as $gradebook): ?>
            <tr>
              <th scope="row"><?= $escape($gradebook['subject_code']) ?><br><?= $escape($gradebook['subject_name']) ?></th>
              <td><?= $escape($gradebook['term_no']) ?></td>
              <td><?= View::render('ui/status', ['status' => $gradebook['status'], 'kind' => 'entity']) ?></td>
              <td><a class="btn btn-outline-secondary" href="/gradebook/<?= (int) $gradebook['id'] ?>" aria-label="เปิดสมุดคะแนน <?= $escape($gradebook['subject_name']) ?> ภาคเรียน <?= $escape($gradebook['term_no']) ?>">เปิดสมุดคะแนน</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
