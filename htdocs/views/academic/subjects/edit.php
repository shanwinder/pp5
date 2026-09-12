<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>รายละเอียดรายวิชา — PP5</title></head>
<body>
<h1>รายละเอียดรายวิชา</h1>
<p><a href="/academic/subjects">กลับรายการรายวิชา</a></p>
<?php if ($error !== null): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
    <p>สถานะรายวิชา: <?= htmlspecialchars($target['status'], ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/academic/subjects/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <p><label>รหัสรายวิชา <input name="code" required maxlength="50" value="<?= htmlspecialchars($target['code'], ENT_QUOTES, 'UTF-8') ?>"></label></p>
        <p><label>ชื่อรายวิชา <input name="name_th" required maxlength="190" value="<?= htmlspecialchars($target['name_th'], ENT_QUOTES, 'UTF-8') ?>"></label></p>
        <button type="submit">บันทึกการแก้ไข</button>
    </form>
    <form method="post" action="/academic/subjects/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES, 'UTF-8') ?>/status">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="status" value="<?= $target['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
        <button type="submit"><?= $target['status'] === 'ACTIVE' ? 'ระงับใช้งานรายวิชา' : 'เปิดใช้งานรายวิชา' ?></button>
    </form>
<?php endif; ?>
</body>
</html>
