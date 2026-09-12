<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>รายละเอียดห้องเรียน — PP5</title></head>
<body>
<h1>รายละเอียดห้องเรียน</h1>
<p><a href="/academic/classrooms">กลับรายการห้องเรียน</a></p>
<?php if ($error !== null): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
    <p>ปีการศึกษา (พ.ศ.): <?= htmlspecialchars((string) $target['year_be'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($target['academic_year_status'], ENT_QUOTES, 'UTF-8') ?>)</p>
    <p>สถานะห้องเรียน: <?= htmlspecialchars($target['status'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php if (in_array($target['academic_year_status'], ['DRAFT', 'ACTIVE'], true)): ?>
        <form method="post" action="/academic/classrooms/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <p><label>ระดับชั้น
                <select name="grade_level_id" required>
                    <option value="">เลือกระดับชั้น</option>
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?= htmlspecialchars((string) $grade['id'], ENT_QUOTES, 'UTF-8') ?>"<?= $target['grade_level_id'] === $grade['id'] ? ' selected' : '' ?>><?= htmlspecialchars($grade['name_th'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label></p>
            <p><label>รหัสห้องเรียน <input name="code" required maxlength="50" value="<?= htmlspecialchars($target['code'], ENT_QUOTES, 'UTF-8') ?>"></label></p>
            <p><label>ชื่อห้องเรียน <input name="name_th" required maxlength="120" value="<?= htmlspecialchars($target['name_th'], ENT_QUOTES, 'UTF-8') ?>"></label></p>
            <button type="submit">บันทึกการแก้ไข</button>
        </form>
        <form method="post" action="/academic/classrooms/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES, 'UTF-8') ?>/status">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="status" value="<?= $target['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
            <button type="submit"><?= $target['status'] === 'ACTIVE' ? 'ระงับใช้งานห้องเรียน' : 'เปิดใช้งานห้องเรียน' ?></button>
        </form>
    <?php else: ?>
        <p>ปีการศึกษาปิดแล้ว ดูรายละเอียดได้อย่างเดียว</p>
        <dl>
            <dt>ระดับชั้น</dt><dd><?= htmlspecialchars($target['grade_level_name'], ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>รหัสห้องเรียน</dt><dd><?= htmlspecialchars($target['code'], ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>ชื่อห้องเรียน</dt><dd><?= htmlspecialchars($target['name_th'], ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
