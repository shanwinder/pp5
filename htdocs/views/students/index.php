<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>นักเรียน — PP5</title></head>
<body>
<h1>นักเรียน</h1>
<?php if ($canManage): ?><p><a href="/students/create">เพิ่มนักเรียน</a></p><?php endif; ?>
<form method="get" action="/students">
    <label>ค้นหารหัสหรือชื่อนักเรียน <input name="q" maxlength="100"></label>
    <button type="submit">ค้นหา</button>
</form>
<?php if ($error !== null): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<table>
    <thead><tr><th>รหัสนักเรียน</th><th>ชื่อ–นามสกุล</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
    <tbody>
    <?php foreach ($students as $student): ?>
        <tr>
            <td><?= htmlspecialchars($student['student_code'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(implode(' ', [$student['prefix_th'], $student['first_name_th'], $student['last_name_th']]), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($student['status'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
                <a href="/students/<?= htmlspecialchars((string) $student['id'], ENT_QUOTES, 'UTF-8') ?>">ดูรายละเอียด</a>
                <?php if ($canManage): ?><a href="/students/<?= htmlspecialchars((string) $student['id'], ENT_QUOTES, 'UTF-8') ?>/edit">แก้ไข</a><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php if ($students === [] && $error === null): ?><p>ไม่พบนักเรียน</p><?php endif; ?>
</body>
</html>
