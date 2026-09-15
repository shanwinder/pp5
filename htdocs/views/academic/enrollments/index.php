<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>การลงทะเบียน — PP5</title></head>
<body>
<h1>การลงทะเบียนนักเรียน</h1>
<?php if ($canManage): ?><p><a href="/academic/enrollments/create">เพิ่มการลงทะเบียน</a></p><?php endif; ?>
<form method="get" action="/academic/enrollments">
    <label>ปีการศึกษา <select name="academic_year_id"><option value="">เลือกปีการศึกษา</option>
        <?php foreach ($years as $year): ?><option value="<?= $escape($year['id']) ?>"<?= $yearId === $year['id'] ? ' selected' : '' ?>><?= $escape($year['year_be']) ?></option><?php endforeach; ?>
    </select></label>
    <label>ระดับชั้น <select name="grade_level_id"><option value="">ทุกระดับชั้น</option>
        <?php foreach ($grades as $grade): ?><option value="<?= $escape($grade['id']) ?>"<?= $gradeId === $grade['id'] ? ' selected' : '' ?>><?= $escape($grade['name_th']) ?></option><?php endforeach; ?>
    </select></label>
    <label>ห้องเรียน <select name="classroom_id"><option value="">ทุกห้อง / ยังไม่จัดห้อง</option>
        <?php foreach ($classrooms as $room): ?><option value="<?= $escape($room['id']) ?>"<?= $classroomId === $room['id'] ? ' selected' : '' ?>><?= $escape($room['name_th']) ?></option><?php endforeach; ?>
    </select></label>
    <label>สถานะ <select name="status"><option value="">ทุกสถานะ</option>
        <?php foreach (['ACTIVE','TRANSFERRED_OUT','WITHDRAWN'] as $state): ?><option value="<?= $escape($state) ?>"<?= $status === $state ? ' selected' : '' ?>><?= $escape($state) ?></option><?php endforeach; ?>
    </select></label>
    <label>ค้นหารหัสหรือชื่อนักเรียน <input name="q" maxlength="100"></label>
    <button type="submit">ค้นหา</button>
</form>
<?php if ($error !== null): ?><p role="alert"><?= $escape($error) ?></p><?php endif; ?>
<table>
<thead><tr><th>รหัสนักเรียน</th><th>ชื่อ–นามสกุล</th><th>ปีการศึกษา</th><th>ระดับชั้น</th><th>ห้องปัจจุบัน</th><th>สถานะ</th><th>รายละเอียด</th></tr></thead>
<tbody>
<?php foreach ($enrollments as $row): ?>
<tr>
    <td><?= $escape($row['student_code']) ?></td>
    <td><?= $escape(implode(' ',[$row['prefix_th'],$row['first_name_th'],$row['last_name_th']])) ?></td>
    <td><?= $escape($row['year_be']) ?></td><td><?= $escape($row['grade_level_name']) ?></td>
    <td><?= $escape($row['classroom_name'] ?? 'ยังไม่จัดห้อง') ?></td><td><?= $escape($row['status']) ?></td>
    <td><a href="/students/<?= $escape($row['student_id']) ?>">ประวัตินักเรียน</a>
        <?php if ($canManage): ?><a href="/academic/enrollments/<?= $escape($row['id']) ?>/edit">รายละเอียดการลงทะเบียน</a><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php if ($enrollments === [] && $error === null): ?><p>ไม่พบการลงทะเบียน</p><?php endif; ?>
</body></html>
