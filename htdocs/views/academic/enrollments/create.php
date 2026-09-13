<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>การลงทะเบียน — PP5</title></head>
<body>
<h1>เพิ่มการลงทะเบียน</h1>
<?php if ($canView): ?><p><a href="/academic/enrollments">กลับรายการลงทะเบียน</a></p><?php endif; ?>
<p>เลือกปีการศึกษาและระดับชั้นเพื่อดูห้องเรียนที่เลือกได้</p>
<form method="get" action="/academic/enrollments/create">
    <label>ปีการศึกษา <select name="academic_year_id" required><option value="">เลือกปีการศึกษา</option>
        <?php foreach ($years as $option): ?><option value="<?= $escape($option['id']) ?>"<?= ($year['id'] ?? null) === $option['id'] ? ' selected' : '' ?>><?= $escape($option['year_be']) ?></option><?php endforeach; ?>
    </select></label>
    <label>ระดับชั้น <select name="grade_level_id" required><option value="">เลือกระดับชั้น</option>
        <?php foreach ($grades as $option): ?><option value="<?= $escape($option['id']) ?>"<?= ($grade['id'] ?? null) === $option['id'] ? ' selected' : '' ?>><?= $escape($option['name_th']) ?></option><?php endforeach; ?>
    </select></label>
    <button type="submit">เลือกปีและระดับชั้น</button>
</form>
<?php if ($year !== null && $grade !== null): ?>
<p>ปีการศึกษา <?= $escape($year['year_be']) ?> — <?= $escape($grade['name_th']) ?></p>
<form method="post" action="/academic/enrollments">
    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
    <input type="hidden" name="academic_year_id" value="<?= $escape($year['id']) ?>">
    <input type="hidden" name="grade_level_id" value="<?= $escape($grade['id']) ?>">
    <p><label>นักเรียน <select name="student_id" required><option value="">เลือกนักเรียน</option>
        <?php foreach ($students as $student): ?><option value="<?= $escape($student['id']) ?>"><?= $escape($student['student_code'].' '.implode(' ',[$student['prefix_th'],$student['first_name_th'],$student['last_name_th']])) ?></option><?php endforeach; ?>
    </select></label></p>
    <p><label>ห้องเรียน <select name="classroom_id"><option value="">ยังไม่จัดห้อง</option>
        <?php foreach ($classrooms as $room): ?><option value="<?= $escape($room['id']) ?>"><?= $escape($room['name_th']) ?></option><?php endforeach; ?>
    </select></label></p>
    <p><label>วันที่เริ่มลงทะเบียน (ถ้ามี) <input type="date" name="entry_date"></label></p>
    <button type="submit">บันทึกการลงทะเบียน</button>
</form>
<?php endif; ?>
</body></html>
