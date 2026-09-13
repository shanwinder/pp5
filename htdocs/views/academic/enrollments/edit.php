<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>การลงทะเบียน — PP5</title></head>
<body>
<h1>รายละเอียดการลงทะเบียน</h1>
<?php if ($canView): ?><p><a href="/academic/enrollments">กลับรายการลงทะเบียน</a></p><?php endif; ?>
<?php if ($error !== null): ?><p role="alert"><?= $escape($error) ?></p><?php endif; ?>
<?php if ($target !== null): ?>
<dl>
    <dt>นักเรียน</dt><dd><?= $escape($target['student_code'].' '.implode(' ',[$target['prefix_th'],$target['first_name_th'],$target['last_name_th']])) ?></dd>
    <dt>ปีการศึกษา</dt><dd><?= $escape($target['year_be']) ?> (<?= $escape($target['academic_year_status']) ?>)</dd>
    <dt>ระดับชั้น</dt><dd><?= $escape($target['grade_level_name']) ?></dd>
    <dt>วันที่เริ่มลงทะเบียน</dt><dd><?= $escape($target['entry_date'] ?? 'ไม่ระบุ') ?></dd>
    <dt>วันที่สิ้นสุด</dt><dd><?= $escape($target['exit_date'] ?? 'ไม่ระบุ') ?></dd>
    <dt>สถานะ</dt><dd><?= $escape($target['status']) ?></dd>
    <dt>ห้องปัจจุบัน</dt><dd><?= $escape($target['classroom_name'] ?? 'ยังไม่จัดห้อง') ?></dd>
</dl>
<h2>ประวัติห้องเรียน</h2>
<table><thead><tr><th>ห้องเรียน</th><th>สถานะ</th><th>เริ่ม</th><th>สิ้นสุด</th></tr></thead><tbody>
<?php foreach ($history as $placement): ?><tr><td><?= $escape($placement['classroom_name']) ?></td><td><?= $escape($placement['status']) ?></td><td><?= $escape($placement['started_at']) ?></td><td><?= $escape($placement['ended_at'] ?? 'ยังไม่สิ้นสุด') ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php if ($history === []): ?><p>ยังไม่มีประวัติห้องเรียน</p><?php endif; ?>
<?php if ($mutable): ?>
<form method="post" action="/academic/enrollments/<?= $escape($target['id']) ?>/placement">
    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
    <label>ห้องเรียน <select name="classroom_id"><option value="">ไม่จัดห้อง / ยกเลิกการจัดห้องปัจจุบัน</option>
        <?php if ($target['classroom_id'] !== null && !in_array($target['classroom_id'], array_column($classrooms, 'id'), true)): ?><option value="<?= $escape($target['classroom_id']) ?>" selected><?= $escape($target['classroom_name']) ?> (ห้องปัจจุบันปิดใช้งาน)</option><?php endif; ?>
        <?php foreach ($classrooms as $room): ?><option value="<?= $escape($room['id']) ?>"<?= $target['classroom_id'] === $room['id'] ? ' selected' : '' ?>><?= $escape($room['name_th']) ?></option><?php endforeach; ?>
    </select></label>
    <button type="submit">บันทึกห้องเรียน</button>
</form>
<form method="post" action="/academic/enrollments/<?= $escape($target['id']) ?>/status">
    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
    <label>สถานะใหม่ <select name="status"><option value="TRANSFERRED_OUT">ย้ายออก</option><option value="WITHDRAWN">ถอนทะเบียน</option></select></label>
    <label>วันที่สิ้นสุด <input type="date" name="exit_date" required></label>
    <button type="submit">สิ้นสุดการลงทะเบียน</button>
</form>
<?php else: ?><p>ประวัติการลงทะเบียนนี้แสดงเพื่ออ่านเท่านั้น</p><?php endif; ?>
<?php endif; ?>
</body></html>
