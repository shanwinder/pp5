<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>เพิ่มการเปิดรายวิชา — PP5</title></head>
<body>
<h1>เพิ่มการเปิดรายวิชา</h1>
<p><a href="/academic/offerings">กลับรายการเปิดรายวิชา</a></p>
<?php if ($error !== null): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<p>เลือกปีการศึกษาสถานะ DRAFT หรือ ACTIVE เพื่อแสดงห้องเรียนที่เปิดใช้งานในปีนั้น</p>
<form method="get" action="/academic/offerings/create">
    <p><label>ปีการศึกษา (พ.ศ.)
        <select name="academic_year_id" required>
            <option value="">เลือกปีการศึกษา</option>
            <?php foreach ($years as $year): ?>
                <option value="<?= htmlspecialchars((string) $year['id'], ENT_QUOTES, 'UTF-8') ?>"<?= ($selectedYear['id'] ?? null) === $year['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $year['year_be'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($year['status'], ENT_QUOTES, 'UTF-8') ?>)</option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <button type="submit">เลือกปีการศึกษา</button>
</form>
<?php if ($selectedYear !== null): ?>
    <p>ปีการศึกษา (พ.ศ.): <?= htmlspecialchars((string) $selectedYear['year_be'], ENT_QUOTES, 'UTF-8') ?> เมื่อบันทึกแล้วจะเปลี่ยนปีการศึกษาไม่ได้</p>
    <p>การเปิดรายวิชาใหม่จะมีสถานะ ACTIVE</p>
    <form method="post" action="/academic/offerings">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="academic_year_id" value="<?= htmlspecialchars((string) $selectedYear['id'], ENT_QUOTES, 'UTF-8') ?>">
        <p><label>ห้องเรียน
            <select name="classroom_id" required>
                <option value="">เลือกห้องเรียน</option>
                <?php foreach ($classrooms as $room): ?>
                    <option value="<?= htmlspecialchars((string) $room['id'], ENT_QUOTES, 'UTF-8') ?>"<?= ($values['classroom_id'] ?? null) === $room['id'] ? ' selected' : '' ?>><?= htmlspecialchars($room['code'] . ' — ' . $room['name_th'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label></p>
        <p><label>รายวิชา
            <select name="subject_id" required>
                <option value="">เลือกรายวิชา</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= htmlspecialchars((string) $subject['id'], ENT_QUOTES, 'UTF-8') ?>"<?= ($values['subject_id'] ?? null) === $subject['id'] ? ' selected' : '' ?>><?= htmlspecialchars($subject['code'] . ' — ' . $subject['name_th'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label></p>
        <p><label>ภาคเรียน
            <select name="term_no" required>
                <option value="">เลือกภาคเรียน</option>
                <?php foreach ([1, 2] as $term): ?>
                    <option value="<?= htmlspecialchars((string) $term, ENT_QUOTES, 'UTF-8') ?>"<?= ($values['term_no'] ?? null) === $term ? ' selected' : '' ?>><?= htmlspecialchars((string) $term, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label></p>
        <button type="submit">บันทึกการเปิดรายวิชา</button>
    </form>
<?php endif; ?>
</body>
</html>
