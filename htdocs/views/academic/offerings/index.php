<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>การเปิดรายวิชา — PP5</title></head>
<body>
<h1>การเปิดรายวิชา</h1>
<p><a href="/academic/offerings/create">เพิ่มการเปิดรายวิชา</a></p>
<table>
    <thead><tr><th>ปีการศึกษา (พ.ศ.)</th><th>ห้องเรียน</th><th>รายวิชา</th><th>ภาคเรียน</th><th>สถานะ</th><th>รายละเอียด</th></tr></thead>
    <tbody>
    <?php foreach ($offerings as $offering): ?>
        <tr>
            <td><?= htmlspecialchars((string) $offering['year_be'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($offering['classroom_code'] . ' — ' . $offering['classroom_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($offering['subject_code'] . ' — ' . $offering['subject_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $offering['term_no'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($offering['status'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><a href="/academic/offerings/<?= htmlspecialchars((string) $offering['id'], ENT_QUOTES, 'UTF-8') ?>/edit">ดูรายละเอียด</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php if ($offerings === []): ?><p>ยังไม่มีการเปิดรายวิชา</p><?php endif; ?>
</body>
</html>
