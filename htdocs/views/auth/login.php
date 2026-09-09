<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>เข้าสู่ระบบ ปพ.5</title>
</head>
<body>
<main>
  <h1>เข้าสู่ระบบ ปพ.5</h1>

  <?php if (is_string($error) && $error !== ''): ?>
    <div role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" action="/login">
    <input type="hidden" name="_token"
      value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <label>ชื่อผู้ใช้
      <input type="text" name="username" autocomplete="username" required>
    </label>

    <label>รหัสผ่าน
      <input type="password" name="password" autocomplete="current-password" required>
    </label>

    <button type="submit">เข้าสู่ระบบ</button>
  </form>
</main>
</body>
</html>
