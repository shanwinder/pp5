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
<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<h2>ประวัติการลงทะเบียน</h2>
<?php if ($history === []): ?><p>ยังไม่มีประวัติการลงทะเบียน</p><?php endif; ?>
<?php foreach ($history as $enrollment): ?>
<section class="enrollment-history">
<h3>ปีการศึกษา <?= $escape($enrollment['year_be']) ?></h3>
<p>ระดับชั้น: <?= $escape($enrollment['grade_level_name']) ?> — สถานะ: <?= $escape($enrollment['status']) ?></p>
<p>วันที่เริ่ม: <?= $escape($enrollment['entry_date'] ?? 'ไม่ระบุ') ?> — วันที่สิ้นสุด: <?= $escape($enrollment['exit_date'] ?? 'ไม่ระบุ') ?></p>
<p>ห้องปัจจุบัน: <?= $escape($enrollment['classroom_name'] ?? 'ยังไม่จัดห้อง') ?></p>
<?php $lastPlacement = $enrollment['placements'][array_key_last($enrollment['placements'])] ?? null; ?>
<?php if ($enrollment['classroom_id'] === null && $lastPlacement !== null): ?><p>ห้องล่าสุด: <?= $escape($lastPlacement['classroom_name']) ?> (สิ้นสุดแล้ว)</p><?php endif; ?>
<table><thead><tr><th>ประวัติห้องเรียน</th><th>สถานะ</th><th>เริ่ม</th><th>สิ้นสุด</th></tr></thead><tbody>
<?php foreach ($enrollment['placements'] as $placement): ?><tr><td><?= $escape($placement['classroom_name']) ?></td><td><?= $escape($placement['status']) ?></td><td><?= $escape($placement['started_at']) ?></td><td><?= $escape($placement['ended_at'] ?? 'ยังไม่สิ้นสุด') ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php if ($enrollment['placements'] === []): ?><p>ยังไม่มีประวัติห้องเรียน</p><?php endif; ?>
<?php if ($canManageEnrollment): ?><p><a href="/academic/enrollments/<?= $escape($enrollment['id']) ?>/edit">รายละเอียดการลงทะเบียน</a></p><?php endif; ?>
</section>
<?php endforeach; ?>
</body>
</html>
