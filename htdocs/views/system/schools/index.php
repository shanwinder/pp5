<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการโรงเรียน — ระบบ ปพ.5</title>
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
  <h1>จัดการโรงเรียน</h1>
  <p><a href="/system/schools/create">สร้างโรงเรียนและผู้ดูแลคนแรก</a></p>
  <?php if (is_string($error) && $error !== ''): ?>
    <div role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if ($schools === []): ?>
    <p>ยังไม่มีโรงเรียน</p>
  <?php elseif ($schools !== null): ?>
    <table>
      <thead>
        <tr><th scope="col">รหัสโรงเรียน</th><th scope="col">ชื่อโรงเรียน</th><th scope="col">สถานะ</th><th scope="col">เปลี่ยนสถานะ</th></tr>
      </thead>
      <tbody>
      <?php foreach ($schools as $school): ?>
        <tr>
          <td><?= htmlspecialchars($school['school_code'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($school['name_th'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($school['status'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <form method="post" action="/system/schools/<?= htmlspecialchars((string) $school['id'], ENT_QUOTES, 'UTF-8') ?>/status">
              <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
              <label>สถานะใหม่
                <select name="status">
                  <?php foreach (['ACTIVE' => 'ใช้งาน', 'SUSPENDED' => 'ระงับชั่วคราว', 'INACTIVE' => 'ไม่ใช้งาน'] as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"<?= $school['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button type="submit">บันทึกสถานะ</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>
</body>
</html>
