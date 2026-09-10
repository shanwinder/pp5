<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการผู้ใช้โรงเรียน — ระบบ ปพ.5</title>
</head>
<body>
<main>
  <p><a href="/admin/users">กลับไปจัดการผู้ใช้</a></p>
  <h1>จัดการผู้ใช้โรงเรียน</h1>
  <?php if (is_string($error) && $error !== ''): ?>
    <div role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($target !== null): ?>
    <p>ชื่อผู้ใช้: <?= htmlspecialchars($target['username'], ENT_QUOTES, 'UTF-8') ?></p>
    <p>สถานะสมาชิก: <?= htmlspecialchars($target['status'], ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/admin/users/<?= htmlspecialchars((string) $target['user_id'], ENT_QUOTES, 'UTF-8') ?>/profile">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <fieldset>
        <legend>ข้อมูลผู้ใช้</legend>
        <p><label>ชื่อที่แสดง
          <input type="text" name="display_name" maxlength="190" required value="<?= htmlspecialchars($target['display_name'], ENT_QUOTES, 'UTF-8') ?>">
        </label></p>
        <p><label>อีเมล (ไม่บังคับ)
          <input type="email" name="email" maxlength="190" value="<?= htmlspecialchars($target['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </label></p>
        <button type="submit">บันทึกข้อมูลผู้ใช้</button>
      </fieldset>
    </form>
    <form method="post" action="/admin/users/<?= htmlspecialchars((string) $target['user_id'], ENT_QUOTES, 'UTF-8') ?>/membership-status">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <fieldset>
        <legend>สถานะสมาชิก</legend>
        <p><label>สถานะใหม่
          <select name="status">
            <?php foreach (['ACTIVE' => 'ใช้งาน', 'SUSPENDED' => 'ระงับชั่วคราว'] as $value => $label): ?>
              <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"<?= $target['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </label></p>
        <button type="submit">บันทึกสถานะ</button>
      </fieldset>
    </form>
    <form method="post" action="/admin/users/<?= htmlspecialchars((string) $target['user_id'], ENT_QUOTES, 'UTF-8') ?>/roles">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <fieldset>
        <legend>บทบาทโรงเรียน (เลือกอย่างน้อยหนึ่งบทบาท)</legend>
        <?php foreach ($roles as $role): ?>
          <p><label>
            <input type="checkbox" name="role_codes[]" value="<?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?>"<?= in_array($role['code'], $roleCodes, true) ? ' checked' : '' ?>>
            <?= htmlspecialchars($role['name_th'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?>)
          </label></p>
        <?php endforeach; ?>
        <button type="submit">บันทึกบทบาท</button>
      </fieldset>
    </form>
    <form method="post" action="/admin/users/<?= htmlspecialchars((string) $target['user_id'], ENT_QUOTES, 'UTF-8') ?>/reset-password">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <fieldset>
        <legend>ตั้งรหัสผ่านใหม่</legend>
        <p><label>รหัสผ่านใหม่ (อย่างน้อย 12 ตัวอักษร)
          <input type="password" name="password" minlength="12" autocomplete="new-password" required>
        </label></p>
        <button type="submit">ตั้งรหัสผ่านใหม่</button>
      </fieldset>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
