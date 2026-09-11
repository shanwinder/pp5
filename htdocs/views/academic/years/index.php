<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>ปีการศึกษา — PP5</title></head>
<body>
<h1>ปีการศึกษา</h1>
<nav>
    <a href="/academic/classrooms">ห้องเรียน</a>
    <a href="/academic/subjects">รายวิชา</a>
    <a href="/academic/offerings">การเปิดรายวิชา</a>
</nav>
<p><a href="/academic/years/create">เพิ่มปีการศึกษา</a></p>
<p>ปีการศึกษาระบุเป็น พ.ศ. ส่วนวันที่ระบุเป็น ค.ศ.</p>
<table>
    <thead><tr><th>ปีการศึกษา (พ.ศ.)</th><th>วันเริ่มต้น (ค.ศ.)</th><th>วันสิ้นสุด (ค.ศ.)</th><th>สถานะ</th><th>รายละเอียด</th></tr></thead>
    <tbody>
    <?php foreach ($years as $year): ?>
        <tr>
            <td><?= htmlspecialchars((string) $year['year_be'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($year['start_date'] ?? 'ยังไม่ระบุ', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($year['end_date'] ?? 'ยังไม่ระบุ', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($year['status'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><a href="/academic/years/<?= htmlspecialchars((string) $year['id'], ENT_QUOTES, 'UTF-8') ?>/edit">ดูรายละเอียด</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php if ($years === []): ?><p>ยังไม่มีปีการศึกษา</p><?php endif; ?>
</body>
</html>
