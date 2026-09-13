<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>รายละเอียดนักเรียน — PP5</title></head>
<body>
<h1>รายละเอียดนักเรียน</h1>
<p><a href="/students">กลับรายการนักเรียน</a></p>
<dl>
    <dt>รหัสนักเรียน</dt><dd><?= htmlspecialchars($target['student_code'], ENT_QUOTES, 'UTF-8') ?></dd>
    <dt>ชื่อ–นามสกุล</dt><dd><?= htmlspecialchars(implode(' ', [$target['prefix_th'], $target['first_name_th'], $target['last_name_th']]), ENT_QUOTES, 'UTF-8') ?></dd>
    <dt>เลขประจำตัวประชาชน</dt><dd><?= htmlspecialchars($maskedNationalId ?? 'ไม่ระบุ', ENT_QUOTES, 'UTF-8') ?></dd>
    <dt>เพศ</dt><dd><?= htmlspecialchars($target['gender_code'] ?? 'ไม่ระบุ', ENT_QUOTES, 'UTF-8') ?></dd>
    <dt>วันเกิด</dt><dd><?= htmlspecialchars($target['birth_date'] ?? 'ไม่ระบุ', ENT_QUOTES, 'UTF-8') ?></dd>
    <dt>สถานะ</dt><dd><?= htmlspecialchars($target['status'], ENT_QUOTES, 'UTF-8') ?></dd>
</dl>
<?php if ($canManage): ?><p><a href="/students/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES, 'UTF-8') ?>/edit">แก้ไขข้อมูลนักเรียน</a></p><?php endif; ?>
</body>
</html>
