<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>ห้องเรียน — PP5</title></head>
<body>
<h1>ห้องเรียน</h1>
<p><a href="/academic/classrooms/create">เพิ่มห้องเรียน</a></p>
<table>
    <thead><tr><th>ปีการศึกษา (พ.ศ.)</th><th>ระดับชั้น</th><th>รหัสห้องเรียน</th><th>ชื่อห้องเรียน</th><th>สถานะ</th><th>รายละเอียด</th></tr></thead>
    <tbody>
    <?php foreach ($classrooms as $classroom): ?>
        <tr>
            <td><?= htmlspecialchars((string) $classroom['year_be'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($classroom['grade_level_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($classroom['code'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($classroom['name_th'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($classroom['status'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><a href="/academic/classrooms/<?= htmlspecialchars((string) $classroom['id'], ENT_QUOTES, 'UTF-8') ?>/edit">ดูรายละเอียด</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php if ($classrooms === []): ?><p>ยังไม่มีห้องเรียน</p><?php endif; ?>
</body>
</html>
