<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>เพิ่มปีการศึกษา — PP5</title></head>
<body>
<h1>เพิ่มปีการศึกษา</h1>
<p><a href="/academic/years">กลับรายการปีการศึกษา</a></p>
<?php if ($error !== null): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<p>ปีการศึกษาระบุเป็น พ.ศ. ส่วนวันที่ระบุเป็น ค.ศ. ในรูปแบบ YYYY-MM-DD</p>
<p>ปีใหม่จะอยู่ในสถานะ DRAFT และเว้นวันที่ว่างได้ ก่อนเปิดใช้งานต้องระบุทั้งสองวันที่</p>
<p>ปี ค.ศ. ของวันเริ่มต้นต้องเท่ากับปี พ.ศ. ลบ 543 และวันสิ้นสุดอยู่ในปีเดียวกันหรือปีถัดไป</p>
<form method="post" action="/academic/years">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <p><label>ปีการศึกษา (พ.ศ.) <input type="number" name="year_be" required min="2400" max="2700" value="<?= htmlspecialchars($values['year_be'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <p><label>วันเริ่มต้น (ค.ศ.) <input type="date" name="start_date" value="<?= htmlspecialchars($values['start_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <p><label>วันสิ้นสุด (ค.ศ.) <input type="date" name="end_date" value="<?= htmlspecialchars($values['end_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <button type="submit">บันทึกปีการศึกษา</button>
</form>
</body>
</html>
