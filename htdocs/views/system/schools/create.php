<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>สร้างโรงเรียน — ระบบ ปพ.5</title>
</head>
<body>
<main>
  <p><a href="/system/schools">กลับไปจัดการโรงเรียน</a></p>
  <h1>สร้างโรงเรียนและผู้ดูแลคนแรก</h1>
  <?php if (is_string($error) && $error !== ''): ?>
    <div role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" action="/system/schools">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <fieldset>
      <legend>ข้อมูลโรงเรียน</legend>
      <p><label>รหัสโรงเรียน
        <input type="text" name="school_code" minlength="2" maxlength="30" pattern="[A-Za-z0-9._\-]+" required
          value="<?= htmlspecialchars($values['school_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </label></p>
      <p><label>ชื่อโรงเรียน
        <input type="text" name="name_th" maxlength="190" required
          value="<?= htmlspecialchars($values['name_th'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </label></p>
    </fieldset>
    <fieldset>
      <legend>ผู้ดูแลโรงเรียนคนแรก</legend>
      <p><label>ชื่อผู้ใช้
        <input type="text" name="admin_username" minlength="3" maxlength="100" pattern="[A-Za-z0-9._\-]+" autocomplete="off" required
          value="<?= htmlspecialchars($values['admin_username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </label></p>
      <p><label>ชื่อที่แสดง
        <input type="text" name="admin_display_name" maxlength="190" required
          value="<?= htmlspecialchars($values['admin_display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </label></p>
      <p><label>อีเมล (ไม่บังคับ)
        <input type="email" name="admin_email" maxlength="190"
          value="<?= htmlspecialchars($values['admin_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </label></p>
      <p><label>รหัสผ่าน (อย่างน้อย 12 ตัวอักษร)
        <input type="password" name="admin_password" minlength="12" autocomplete="new-password" required>
      </label></p>
    </fieldset>
    <p><button type="submit">สร้างโรงเรียนและผู้ดูแล</button></p>
  </form>
</main>
</body>
</html>
