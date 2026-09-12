<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>รายละเอียดปีการศึกษา — PP5</title></head>
<body>
<h1>รายละเอียดปีการศึกษา</h1>
<p><a href="/academic/years">กลับรายการปีการศึกษา</a></p>
<?php if ($error !== null): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
    <p>สถานะ: <?= htmlspecialchars($target['status'], ENT_QUOTES, 'UTF-8') ?></p>
    <p>ปีการศึกษาระบุเป็น พ.ศ. ส่วนวันที่ระบุเป็น ค.ศ. ในรูปแบบ YYYY-MM-DD</p>
    <?php if ($target['status'] === 'DRAFT'): ?>
        <p>ปี ค.ศ. ของวันเริ่มต้นต้องเท่ากับปี พ.ศ. ลบ 543 และวันสิ้นสุดอยู่ในปีเดียวกันหรือปีถัดไป</p>
        <form method="post" action="/academic/years/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <p><label>ปีการศึกษา (พ.ศ.) <input type="number" name="year_be" required min="2400" max="2700" value="<?= htmlspecialchars((string) $target['year_be'], ENT_QUOTES, 'UTF-8') ?>"></label></p>
            <p><label>วันเริ่มต้น (ค.ศ.) <input type="date" name="start_date" value="<?= htmlspecialchars($target['start_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
            <p><label>วันสิ้นสุด (ค.ศ.) <input type="date" name="end_date" value="<?= htmlspecialchars($target['end_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
            <button type="submit">บันทึกการแก้ไข</button>
        </form>
    <?php else: ?>
        <dl>
            <dt>ปีการศึกษา (พ.ศ.)</dt><dd><?= htmlspecialchars((string) $target['year_be'], ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>วันเริ่มต้น (ค.ศ.)</dt><dd><?= htmlspecialchars($target['start_date'] ?? 'ยังไม่ระบุ', ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>วันสิ้นสุด (ค.ศ.)</dt><dd><?= htmlspecialchars($target['end_date'] ?? 'ยังไม่ระบุ', ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>
    <?php endif; ?>
    <?php if ($target['status'] === 'DRAFT' || $target['status'] === 'ACTIVE'): ?>
        <?php if ($target['status'] === 'DRAFT'): ?><p>กรุณาบันทึกวันเริ่มต้นและวันสิ้นสุดให้ครบก่อนเปิดใช้งาน</p><?php endif; ?>
        <form method="post" action="/academic/years/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES, 'UTF-8') ?>/status">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="status" value="<?= $target['status'] === 'DRAFT' ? 'ACTIVE' : 'CLOSED' ?>">
            <button type="submit"><?= $target['status'] === 'DRAFT' ? 'เปิดใช้งานปีการศึกษา' : 'ปิดปีการศึกษา' ?></button>
        </form>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
