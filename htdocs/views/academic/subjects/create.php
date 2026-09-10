<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>เพิ่มรายวิชา — PP5</title></head>
<body>
<h1>เพิ่มรายวิชา</h1>
<p><a href="/academic/subjects">กลับรายการรายวิชา</a></p>
<?php if ($error !== null): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<p>รายวิชาใหม่จะมีสถานะ ACTIVE รหัสรายวิชาต้องไม่ซ้ำภายในโรงเรียน</p>
<form method="post" action="/academic/subjects">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <p><label>รหัสรายวิชา <input name="code" required maxlength="50" value="<?= htmlspecialchars($values['code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <p><label>ชื่อรายวิชา <input name="name_th" required maxlength="190" value="<?= htmlspecialchars($values['name_th'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <button type="submit">บันทึกรายวิชา</button>
</form>
</body>
</html>
