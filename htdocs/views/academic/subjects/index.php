<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>รายวิชา — PP5</title></head>
<body>
<h1>รายวิชา</h1>
<p><a href="/academic/subjects/create">เพิ่มรายวิชา</a></p>
<table>
    <thead><tr><th>รหัสรายวิชา</th><th>ชื่อรายวิชา</th><th>สถานะ</th><th>รายละเอียด</th></tr></thead>
    <tbody>
    <?php foreach ($subjects as $subject): ?>
        <tr>
            <td><?= htmlspecialchars($subject['code'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($subject['name_th'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($subject['status'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><a href="/academic/subjects/<?= htmlspecialchars((string) $subject['id'], ENT_QUOTES, 'UTF-8') ?>/edit">ดูรายละเอียด</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php if ($subjects === []): ?><p>ยังไม่มีรายวิชา</p><?php endif; ?>
</body>
</html>
