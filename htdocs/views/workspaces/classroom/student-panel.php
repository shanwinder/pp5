<?php
use App\Support\View;
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$roomId = $roster['workspace']['classroom']['id'];
$enrollmentId = $student['enrollmentId'];
$base = '/hx/workspaces/classrooms/' . $roomId . '/students/' . $enrollmentId;
$mutable = $roster['canManage'] && $enrollment['status'] === 'ACTIVE';
?>
<section class="card pp5-student-context-card" aria-labelledby="student-context-heading">
  <div class="card-status-top bg-blue"></div>
  <div class="card-header d-flex justify-content-between align-items-start gap-2">
    <div><div class="subheader">นักเรียนที่เลือก</div><h3 class="card-title mb-0" id="student-context-heading" tabindex="-1"><?= $escape($student['name']) ?></h3></div>
    <button class="btn btn-outline-secondary btn-sm" type="button" data-student-close>ปิดข้อมูล</button>
  </div>
  <div class="card-body">
    <?php if ($error !== null): ?><div class="alert alert-danger" id="student-workflow-error" role="alert" tabindex="-1" data-workflow-focus><?= $escape($error) ?></div><?php endif; ?>
    <dl class="row mb-3 pp5-student-facts">
      <dt class="col-sm-4">รหัสนักเรียน</dt><dd class="col-sm-8"><?= $escape($student['code']) ?></dd>
      <dt class="col-sm-4">สถานะปัจจุบัน</dt><dd class="col-sm-8"><?= View::render('ui/status', ['status' => $enrollment['status'], 'kind' => 'enrollment']) ?></dd>
      <dt class="col-sm-4">ห้องปัจจุบัน</dt><dd class="col-sm-8"><?= $escape($enrollment['classroom_name']) ?></dd>
      <dt class="col-sm-4">ปีการศึกษา</dt><dd class="col-sm-8"><?= $escape($enrollment['year_be']) ?></dd>
      <dt class="col-sm-4">ระดับชั้น</dt><dd class="col-sm-8"><?= $escape($enrollment['grade_level_name']) ?></dd>
      <dt class="col-sm-4">วันที่เริ่มเรียน</dt><dd class="col-sm-8"><?= $escape($enrollment['entry_date'] ?? 'ไม่ระบุ') ?></dd>
    </dl>
    <a href="/students/<?= $escape($student['studentId']) ?>">ดูข้อมูลและประวัติทั้งหมด</a>

    <section class="mt-4" aria-labelledby="student-placement-history-heading">
      <h4 class="h4 mb-2" id="student-placement-history-heading">ประวัติห้องเรียน</h4>
      <?php if ($placementHistory === []): ?><p class="text-secondary">ยังไม่มีประวัติห้องเรียน</p><?php else: ?>
        <ul class="list-unstyled mb-0 pp5-student-history">
          <?php foreach ($placementHistory as $placement): ?>
            <li><strong><?= $escape($placement['classroom_name']) ?></strong> · <?= $escape($placement['started_at']) ?> – <?= $escape($placement['ended_at'] ?? 'ปัจจุบัน') ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if (count($enrollmentHistory) > 1): ?>
      <section class="mt-4" aria-labelledby="student-enrollment-history-heading">
        <h4 class="h4 mb-2" id="student-enrollment-history-heading">ประวัติปีการศึกษา</h4>
        <ul class="list-unstyled mb-0 pp5-student-history">
          <?php foreach ($enrollmentHistory as $past): if ((int) $past['id'] === $enrollmentId) { continue; } ?>
            <li><strong>ปี <?= $escape($past['year_be']) ?> · <?= $escape($past['grade_level_name']) ?></strong>
              · <?= View::render('ui/status', ['status' => $past['status'], 'kind' => 'enrollment']) ?>
              · <?= $escape($past['entry_date'] ?? 'ไม่ระบุวันเริ่ม') ?> – <?= $escape($past['exit_date'] ?? 'ไม่ระบุวันสิ้นสุด') ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($mutable): ?>
      <div class="row g-3 mt-1">
        <section class="col-lg-6" aria-labelledby="student-move-heading">
          <div class="card h-100 pp5-student-action-card pp5-student-action-card--move"><div class="card-body">
            <h4 class="h4" id="student-move-heading">ย้ายห้อง</h4>
            <p class="text-secondary">ห้องปัจจุบัน: <?= $escape($enrollment['classroom_name']) ?></p>
            <?php if ($rooms === []): ?><p class="text-secondary mb-0">ไม่มีห้องเรียนที่เปิดใช้งานในปีและระดับชั้นเดียวกัน</p><?php else: ?>
              <form method="post" action="<?= $escape($base . '/placement') ?>" hx-post="<?= $escape($base . '/placement') ?>" hx-target="#student-workspace-content" hx-swap="outerHTML"<?= $error !== null ? ' aria-describedby="student-workflow-error"' : '' ?>>
                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                <label class="form-label" for="student-target-room">ย้ายไป</label>
                <select class="form-select mb-3" id="student-target-room" name="classroom_id" required>
                  <option value="">เลือกห้องเรียน</option>
                  <?php foreach ($rooms as $room): ?><option value="<?= $escape($room['id']) ?>"><?= $escape($room['name_th']) ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">ยืนยันย้ายห้อง</button>
              </form>
            <?php endif; ?>
          </div></div>
        </section>
        <section class="col-lg-6" aria-labelledby="student-status-heading">
          <div class="card h-100 pp5-student-action-card pp5-student-action-card--status"><div class="card-body">
            <h4 class="h4" id="student-status-heading">เปลี่ยนสถานะ</h4>
            <p class="text-secondary">การย้ายออกหรือลาออกจะสิ้นสุดการลงทะเบียนและการจัดห้องในปีนี้</p>
            <form method="post" action="<?= $escape($base . '/status') ?>" hx-post="<?= $escape($base . '/status') ?>" hx-target="#student-workspace-content" hx-swap="outerHTML" hx-confirm="ยืนยันการสิ้นสุดการลงทะเบียนในปีนี้? ไม่สามารถเปิดกลับได้" data-confirm="ยืนยันการสิ้นสุดการลงทะเบียนในปีนี้? ไม่สามารถเปิดกลับได้"<?= $error !== null ? ' aria-describedby="student-workflow-error"' : '' ?>>
              <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
              <label class="form-label" for="student-new-status">สถานะใหม่</label>
              <select class="form-select mb-3" id="student-new-status" name="status" required><option value="">เลือกสถานะ</option><option value="TRANSFERRED_OUT">ย้ายออก</option><option value="WITHDRAWN">ลาออก</option></select>
              <label class="form-label" for="student-exit-date">วันที่สิ้นสุดการลงทะเบียน</label>
              <input class="form-control mb-3" id="student-exit-date" type="date" name="exit_date" required>
              <button class="btn btn-danger" type="submit">ยืนยันเปลี่ยนสถานะ</button>
            </form>
          </div></div>
        </section>
      </div>
    <?php elseif (!$roster['openYear']): ?><p class="alert alert-info mt-3 mb-0">ปีการศึกษานี้ปิดแล้ว แสดงข้อมูลเพื่ออ่านเท่านั้น</p><?php endif; ?>
  </div>
</section>
