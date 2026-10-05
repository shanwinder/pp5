<?php
use App\Support\View;
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$legacyQuery = http_build_query(['academic_year_id' => $workspace['academicYear']['id'],
    'grade_level_id' => $workspace['gradeLevel']['id'], 'classroom_id' => $workspace['classroom']['id'],
    'workspace_classroom_id' => $workspace['classroom']['id']]);
?>
<div class="pp5-admin-page pp5-student-workspace">
  <section class="pp5-student-taskbar" aria-labelledby="student-workspace-heading">
    <div>
      <p class="pp5-section-kicker">งานนักเรียน / <?= $escape($workspace['classroom']['name']) ?></p>
      <h2 id="student-workspace-heading">รายชื่อนักเรียน <span><?= $escape(count($students)) ?> คน</span></h2>
      <p>เลือก “จัดการ” ที่แถวนักเรียนเพื่อดูสถานะและงานที่ทำได้ โดยไม่เสียตำแหน่งในรายชื่อ</p>
      <p class="pp5-student-context-status">ปีการศึกษา <?= View::render('ui/status', ['status' => $workspace['academicYear']['status'], 'kind' => 'academic-year']) ?> · ห้องเรียน <?= View::render('ui/status', ['status' => $workspace['classroom']['status'], 'kind' => 'entity']) ?></p>
    </div>
    <div class="pp5-student-top-actions">
      <?php if ($canAdd): ?><a class="btn btn-primary" href="/academic/enrollments/create?<?= $escape(http_build_query(['academic_year_id' => $workspace['academicYear']['id'], 'grade_level_id' => $workspace['gradeLevel']['id']])) ?>">เพิ่มนักเรียนเข้าปีนี้</a><?php endif; ?>
      <?php if ($canImport): ?><a class="btn btn-outline-secondary" href="/academic/student-import">นำเข้านักเรียนจาก CSV</a><?php endif; ?>
    </div>
  </section>
  <?php if (!$openYear): ?>
    <p class="pp5-alert pp5-alert--info">ปีการศึกษานี้ปิดแล้ว แสดงรายชื่อที่คงอยู่ในปีนั้นเพื่ออ่านเท่านั้น</p>
  <?php elseif ($workspace['classroom']['status'] !== 'ACTIVE'): ?>
    <p class="pp5-alert pp5-alert--info">ห้องนี้ปิดใช้งานแล้ว ยังดูรายชื่อเดิมได้ ผู้มีสิทธิ์สามารถย้ายนักเรียนไปห้องที่เปิดใช้งานหรือเปลี่ยนสถานะได้</p>
  <?php endif; ?>
  <div class="pp5-roster-heading">
    <div><p class="pp5-section-kicker">รายชื่อปัจจุบัน</p><h2 id="student-roster-heading">นักเรียนใน <?= $escape($workspace['classroom']['name']) ?></h2></div>
    <a href="/academic/enrollments?<?= $escape($legacyQuery) ?>">ตัวกรองและรายชื่อเพิ่มเติม</a>
  </div>
  <p class="pp5-roster-note">แสดงนักเรียนที่กำลังเรียนและยังจัดอยู่ในห้องนี้ของปีการศึกษาที่เลือก ประวัติการย้ายห้องและการย้ายออกอยู่ในข้อมูลนักเรียน</p>
  <div class="pp5-table-scroll pp5-roster-scroll" role="region" aria-label="นักเรียนในห้องนี้" tabindex="0">
    <table class="table pp5-table" id="classroom-roster" aria-describedby="student-roster-heading">
      <caption>นักเรียน · <?= $escape($workspace['classroom']['name']) ?> · ปีการศึกษา <?= $escape($workspace['academicYear']['year_be']) ?></caption>
      <thead><tr><th scope="col">รหัสนักเรียน</th><th scope="col">ชื่อ–สกุล</th><th scope="col">สถานะ</th><th scope="col">การจัดการ</th></tr></thead>
      <tbody>
      <?php foreach ($students as $student): ?>
        <tr class="pp5-roster-row">
          <td><?= $escape($student['code']) ?></td>
          <th scope="row"><?= $escape($student['name']) ?></th>
          <td><?= View::render('ui/status', ['status' => $student['status'], 'kind' => 'enrollment']) ?></td>
          <td>
            <details class="pp5-student-detail" data-student-detail>
              <summary aria-label="<?= $escape('จัดการ ' . $student['name']) ?>">จัดการ <span aria-hidden="true">▾</span></summary>
              <div class="pp5-student-detail-panel">
                <p class="pp5-section-kicker">นักเรียนที่เลือก</p>
                <h3><?= $escape($student['name']) ?></h3>
                <p>รหัส <?= $escape($student['code']) ?> · <?= $escape($workspace['classroom']['name']) ?></p>
                <p>สถานะ <?= View::render('ui/status', ['status' => $student['status'], 'kind' => 'enrollment']) ?></p>
                <div class="pp5-student-detail-actions">
                  <a href="/students/<?= $escape($student['studentId']) ?>" aria-label="<?= $escape('ดูข้อมูลและประวัติ ' . $student['name']) ?>">ดูข้อมูลและประวัติ</a>
                  <?php if ($canManage): ?>
                    <a href="/academic/enrollments/<?= $escape($student['enrollmentId']) ?>/edit#move-classroom" aria-label="<?= $escape('ย้ายห้อง ' . $student['name']) ?>">ย้ายห้อง</a>
                    <a href="/academic/enrollments/<?= $escape($student['enrollmentId']) ?>/edit#student-status" aria-label="<?= $escape('ย้ายออก / ลาออก ' . $student['name']) ?>">ย้ายออก / ลาออก</a>
                  <?php endif; ?>
                </div>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($students === []): ?><p class="pp5-empty-state">ยังไม่มีนักเรียนที่กำลังเรียนและจัดอยู่ในห้องนี้</p><?php endif; ?>
  <?php if ($canAdd || $canImport): ?><p class="pp5-help">การเพิ่มและนำเข้าใช้ขั้นตอนเดิม โปรดตรวจสอบปีการศึกษาและเลือกห้องให้ตรงกับงานนี้ก่อนบันทึก</p><?php endif; ?>
</div>
