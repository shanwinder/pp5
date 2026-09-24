<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<div class="pp5-admin-page">
<?php if ($canView): ?><p><a class="btn btn-outline-secondary" href="/academic/enrollments">กลับรายการลงทะเบียน</a></p><?php endif; ?>
<?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= $escape($error) ?></p><?php endif; ?>
<?php if ($target !== null): ?>
<dl class="pp5-surface">
    <dt>นักเรียน</dt><dd><?= $escape($target['student_code'].' '.implode(' ',[$target['prefix_th'],$target['first_name_th'],$target['last_name_th']])) ?></dd>
    <dt>ปีการศึกษา</dt><dd><?= $escape($target['year_be']) ?> (<?= App\Support\View::render('ui/status', ['status'=>$target['academic_year_status'], 'kind'=>'academic-year']) ?>)</dd>
    <dt>ระดับชั้น</dt><dd><?= $escape($target['grade_level_name']) ?></dd>
    <dt>วันที่เริ่มลงทะเบียน</dt><dd><?= $escape($target['entry_date'] ?? 'ไม่ระบุ') ?></dd>
    <dt>วันที่สิ้นสุด</dt><dd><?= $escape($target['exit_date'] ?? 'ไม่ระบุ') ?></dd>
    <dt>สถานะ</dt><dd><?= App\Support\View::render('ui/status', ['status'=>$target['status'], 'kind'=>'enrollment']) ?></dd>
    <dt>ห้องปัจจุบัน</dt><dd><?= $escape($target['classroom_name'] ?? 'ยังไม่จัดห้อง') ?></dd>
</dl>
<section class="pp5-surface pp5-sensitive">
<h2>สถานะการลงทะเบียน</h2>
<p>การย้ายออกหรือลาออกเป็นการสิ้นสุดการลงทะเบียน และสิ้นสุดการจัดห้องเรียนปัจจุบัน ไม่มีการเปิดกลับในหน้านี้</p>
<?php if ($mutable): ?>
<form class="pp5-form" method="post" action="/academic/enrollments/<?= $escape($target['id']) ?>/status" data-confirm="ยืนยันการสิ้นสุดการลงทะเบียนหรือไม่? การย้ายออกหรือลาออกจะสิ้นสุดการจัดห้องเรียนปัจจุบัน และไม่สามารถเปิดการลงทะเบียนนี้กลับมาได้">
    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-edit-2">สถานะใหม่ <select class="form-select" id="academic-enrollments-edit-2" name="status" required><option value="TRANSFERRED_OUT">ย้ายออก</option><option value="WITHDRAWN">ลาออก</option></select></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-edit-3">วันที่สิ้นสุด <input class="form-control" id="academic-enrollments-edit-3" type="date" name="exit_date" required></label></div>
    <button class="btn btn-danger" type="submit">สิ้นสุดการลงทะเบียน</button>
</form>
<?php else: ?><p>ประวัติการลงทะเบียนนี้แสดงเพื่ออ่านเท่านั้น</p><?php endif; ?>
</section>
<section class="pp5-surface">
<h2>การจัดห้องเรียน</h2>
<?php if ($mutable): ?>
<p>เลือกห้องเพื่อจัดหรือย้ายห้องเรียน เลือกไม่จัดห้องเพื่อยกเลิกการจัดห้องปัจจุบัน โดยไม่เปลี่ยนสถานะการลงทะเบียน</p>
<form class="pp5-form" method="post" action="/academic/enrollments/<?= $escape($target['id']) ?>/placement">
    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-edit-1">ห้องเรียน <select class="form-select" id="academic-enrollments-edit-1" name="classroom_id"><option value="">ไม่จัดห้อง / ยกเลิกการจัดห้องปัจจุบัน</option>
        <?php if ($target['classroom_id'] !== null && !in_array($target['classroom_id'], array_column($classrooms, 'id'), true)): ?><option value="<?= $escape($target['classroom_id']) ?>" selected><?= $escape($target['classroom_name']) ?> (ห้องปัจจุบันปิดใช้งาน)</option><?php endif; ?>
        <?php foreach ($classrooms as $room): ?><option value="<?= $escape($room['id']) ?>"<?= $target['classroom_id'] === $room['id'] ? ' selected' : '' ?>><?= $escape($room['name_th']) ?></option><?php endforeach; ?>
    </select></label></div>
    <button class="btn btn-primary" type="submit">บันทึกห้องเรียน</button>
</form>
<?php else: ?><p>การจัดห้องเรียนแสดงเพื่ออ่านเท่านั้น</p><?php endif; ?>
</section>
<section>
<h2>ประวัติการจัดห้องเรียน</h2>
<div class="pp5-table-scroll" role="region" aria-label="ประวัติการจัดห้องเรียน" tabindex="0"><table class="table pp5-table"><thead><tr><th scope="col">ห้องเรียน</th><th scope="col">สถานะ</th><th scope="col">เริ่ม</th><th scope="col">สิ้นสุด</th></tr></thead><tbody>
<?php foreach ($history as $placement): ?><tr><th scope="row"><?= $escape($placement['classroom_name']) ?></th><td><?= App\Support\View::render('ui/status', ['status'=>$placement['status'], 'kind'=>'placement']) ?></td><td><?= $escape($placement['started_at']) ?></td><td><?= $escape($placement['ended_at'] ?? 'ยังไม่สิ้นสุด') ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($history === []): ?><p class="pp5-empty-state">ยังไม่มีประวัติห้องเรียน</p><?php endif; ?>
</section>
<?php endif; ?>
</div>
