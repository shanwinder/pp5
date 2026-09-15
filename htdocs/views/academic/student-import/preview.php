<?php $escape = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>
<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ตรวจสอบการนำเข้า — PP5</title></head>
<body>
<h1>ตรวจสอบการนำเข้านักเรียน</h1>
<p><a href="/academic/student-import">กลับหน้านำเข้า</a></p>
<?php if ($error !== null): ?><p role="alert"><?= $escape($error) ?></p><?php endif; ?>
<?php if ($batch !== null): ?>
<dl>
    <dt>ไฟล์</dt><dd><?= $escape($batch['source_name']) ?></dd>
    <dt>สถานะ</dt><dd><?= $escape($batch['status']) ?></dd>
    <dt>จำนวนแถว</dt><dd><?= $escape($batch['row_count']) ?></dd>
    <dt>นักเรียนใหม่</dt><dd><?= $escape($batch['create_student_count']) ?></dd>
    <dt>ลงทะเบียนใหม่</dt><dd><?= $escape($batch['create_enrollment_count']) ?></dd>
    <dt>ข้อมูลตรงกัน ไม่ต้องเปลี่ยน</dt><dd><?= $escape($batch['noop_count']) ?></dd>
    <dt>แถวที่ต้องแก้ไข</dt><dd><?= $escape($batch['error_count']) ?></dd>
    <dt>หมดอายุ</dt><dd><?= $escape($batch['expires_at']) ?></dd>
</dl>
<?php if ($rows !== []): ?>
<table><thead><tr><th>แถว</th><th>รหัสนักเรียน</th><th>ชื่อ–นามสกุล</th><th>ระดับชั้น</th><th>ห้อง</th><th>นักเรียน</th><th>การลงทะเบียน</th><th>ข้อผิดพลาด</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= $escape($row['row_no']) ?></td><td><?= $escape($row['student_code']) ?></td>
<td><?= $escape(implode(' ', [$row['prefix_th'], $row['first_name_th'], $row['last_name_th']])) ?></td>
<td><?= $escape($row['grade_level_code']) ?></td><td><?= $escape($row['classroom_code']) ?></td>
<td><?= $escape($row['student_action']) ?></td><td><?= $escape($row['enrollment_action']) ?></td><td><?= $escape($row['error_message']) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
<?php if ($batch['status'] === 'PREVIEW' && !$batch['is_expired']): ?>
<?php if ($batch['error_count'] === 0 && ($batch['create_student_count'] > 0 || $batch['create_enrollment_count'] > 0)): ?>
<form method="post" action="/academic/student-import/<?= $escape($batch['id']) ?>/apply">
    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
    <button type="submit">ยืนยันนำเข้า</button>
</form>
<?php endif; ?>
<form method="post" action="/academic/student-import/<?= $escape($batch['id']) ?>/cancel">
    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
    <button type="submit">ยกเลิกตัวอย่าง</button>
</form>
<?php endif; ?>
<?php endif; ?>
</body></html>
