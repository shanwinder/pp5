<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการผู้ใช้โรงเรียน — ระบบ ปพ.5</title>
</head>
<body>
<header>
  <strong>ระบบ ปพ.5</strong>
  <span><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
  <form method="post" action="/logout">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">ออกจากระบบ</button>
  </form>
</header>
<main>
  <h1>จัดการผู้ใช้โรงเรียน</h1>
  <p><a href="/admin/users/create">สร้างผู้ใช้</a></p>
  <?php if ($members === []): ?>
    <p>ยังไม่มีสมาชิกโรงเรียน</p>
  <?php else: ?>
    <table>
      <thead>
        <tr><th scope="col">ชื่อผู้ใช้</th><th scope="col">ชื่อที่แสดง</th><th scope="col">อีเมล</th><th scope="col">สถานะสมาชิก</th><th scope="col">บทบาท</th><th scope="col">จัดการ</th></tr>
      </thead>
      <tbody>
      <?php foreach ($members as $member): ?>
        <tr>
          <td><?= htmlspecialchars($member['username'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($member['display_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($member['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($member['status'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars(implode(', ', $member['role_codes']), ENT_QUOTES, 'UTF-8') ?></td>
          <td><a href="/admin/users/<?= htmlspecialchars((string) $member['user_id'], ENT_QUOTES, 'UTF-8') ?>/edit">จัดการผู้ใช้</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>
</body>
</html>
