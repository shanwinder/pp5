<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>สร้างผู้ใช้โรงเรียน — ระบบ ปพ.5</title>
</head>
<body>
<main>
  <p><a href="/admin/users">กลับไปจัดการผู้ใช้</a></p>
  <h1>สร้างผู้ใช้โรงเรียน</h1>
  <?php if (is_string($error) && $error !== ''): ?>
    <div role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <form method="post" action="/admin/users">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <p><label>ชื่อผู้ใช้
      <input type="text" name="username" minlength="3" maxlength="100" autocomplete="off" required
        value="<?= htmlspecialchars($values['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </label></p>
    <p><label>ชื่อที่แสดง
      <input type="text" name="display_name" maxlength="190" required
        value="<?= htmlspecialchars($values['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </label></p>
    <p><label>อีเมล (ไม่บังคับ)
      <input type="email" name="email" maxlength="190"
        value="<?= htmlspecialchars($values['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </label></p>
    <p><label>รหัสผ่าน (อย่างน้อย 12 ตัวอักษร)
      <input type="password" name="password" minlength="12" autocomplete="new-password" required>
    </label></p>
    <fieldset>
      <legend>บทบาทโรงเรียน (เลือกอย่างน้อยหนึ่งบทบาท)</legend>
      <?php foreach ($roles as $role): ?>
        <p><label>
          <input type="checkbox" name="role_codes[]" value="<?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?>"<?= in_array($role['code'], $values['role_codes'] ?? [], true) ? ' checked' : '' ?>>
          <?= htmlspecialchars($role['name_th'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?>)
        </label></p>
      <?php endforeach; ?>
    </fieldset>
    <p><button type="submit">สร้างผู้ใช้</button></p>
  </form>
</main>
</body>
</html>
