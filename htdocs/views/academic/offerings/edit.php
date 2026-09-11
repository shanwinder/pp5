<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>รายละเอียดการเปิดรายวิชา — PP5</title></head>
<body>
<h1>รายละเอียดการเปิดรายวิชา</h1>
<p><a href="/academic/offerings">กลับรายการเปิดรายวิชา</a></p>
<?php if ($error !== null): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
    <dl>
        <dt>ปีการศึกษา (พ.ศ.)</dt><dd><?= htmlspecialchars((string) $target['year_be'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($target['academic_year_status'], ENT_QUOTES, 'UTF-8') ?>)</dd>
        <dt>ห้องเรียนปัจจุบัน</dt><dd><?= htmlspecialchars($target['classroom_code'] . ' — ' . $target['classroom_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($target['classroom_status'], ENT_QUOTES, 'UTF-8') ?>)</dd>
        <dt>รายวิชาปัจจุบัน</dt><dd><?= htmlspecialchars($target['subject_code'] . ' — ' . $target['subject_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($target['subject_status'], ENT_QUOTES, 'UTF-8') ?>)</dd>
        <dt>ภาคเรียน</dt><dd><?= htmlspecialchars((string) $target['term_no'], ENT_QUOTES, 'UTF-8') ?></dd>
        <dt>สถานะการเปิดรายวิชา</dt><dd><?= htmlspecialchars($target['status'], ENT_QUOTES, 'UTF-8') ?></dd>
    </dl>
    <?php if (in_array($target['academic_year_status'], ['DRAFT', 'ACTIVE'], true)): ?>
        <form method="post" action="/academic/offerings/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <p><label>ห้องเรียน
                <select name="classroom_id" required>
                    <option value="">เลือกห้องเรียน</option>
                    <?php foreach ($classrooms as $room): ?>
                        <option value="<?= htmlspecialchars((string) $room['id'], ENT_QUOTES, 'UTF-8') ?>"<?= $target['classroom_id'] === $room['id'] ? ' selected' : '' ?>><?= htmlspecialchars($room['code'] . ' — ' . $room['name_th'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label></p>
            <p><label>รายวิชา
                <select name="subject_id" required>
                    <option value="">เลือกรายวิชา</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= htmlspecialchars((string) $subject['id'], ENT_QUOTES, 'UTF-8') ?>"<?= $target['subject_id'] === $subject['id'] ? ' selected' : '' ?>><?= htmlspecialchars($subject['code'] . ' — ' . $subject['name_th'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label></p>
            <p><label>ภาคเรียน
                <select name="term_no" required>
                    <?php foreach ([1, 2] as $term): ?>
                        <option value="<?= htmlspecialchars((string) $term, ENT_QUOTES, 'UTF-8') ?>"<?= $target['term_no'] === $term ? ' selected' : '' ?>><?= htmlspecialchars((string) $term, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label></p>
            <button type="submit">บันทึกการแก้ไข</button>
        </form>
        <form method="post" action="/academic/offerings/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES, 'UTF-8') ?>/status">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="status" value="<?= $target['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
            <button type="submit"><?= $target['status'] === 'ACTIVE' ? 'ระงับการเปิดรายวิชา' : 'เปิดใช้งานรายวิชาอีกครั้ง' ?></button>
        </form>
    <?php else: ?>
        <p>ปีการศึกษาปิดแล้ว ดูรายละเอียดได้อย่างเดียว</p>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
