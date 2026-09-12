<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>เพิ่มห้องเรียน — PP5</title></head>
<body>
<h1>เพิ่มห้องเรียน</h1>
<p><a href="/academic/classrooms">กลับรายการห้องเรียน</a></p>
<?php if ($error !== null): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<p>เพิ่มห้องเรียนได้ในปีการศึกษาสถานะ DRAFT หรือ ACTIVE ห้องเรียนใหม่จะมีสถานะ ACTIVE</p>
<p>เมื่อบันทึกแล้วจะเปลี่ยนปีการศึกษาของห้องเรียนไม่ได้</p>
<form method="post" action="/academic/classrooms">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <p><label>ปีการศึกษา (พ.ศ.)
        <select name="academic_year_id" required>
            <option value="">เลือกปีการศึกษา</option>
            <?php foreach ($years as $year): ?>
                <option value="<?= htmlspecialchars((string) $year['id'], ENT_QUOTES, 'UTF-8') ?>"<?= ($values['academic_year_id'] ?? null) === $year['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $year['year_be'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($year['status'], ENT_QUOTES, 'UTF-8') ?>)</option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <p><label>ระดับชั้น
        <select name="grade_level_id" required>
            <option value="">เลือกระดับชั้น</option>
            <?php foreach ($grades as $grade): ?>
                <option value="<?= htmlspecialchars((string) $grade['id'], ENT_QUOTES, 'UTF-8') ?>"<?= ($values['grade_level_id'] ?? null) === $grade['id'] ? ' selected' : '' ?>><?= htmlspecialchars($grade['name_th'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <p><label>รหัสห้องเรียน <input name="code" required maxlength="50" value="<?= htmlspecialchars($values['code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <p><label>ชื่อห้องเรียน <input name="name_th" required maxlength="120" value="<?= htmlspecialchars($values['name_th'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <button type="submit">บันทึกห้องเรียน</button>
</form>
</body>
</html>
