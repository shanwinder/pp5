<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แดชบอร์ด — ระบบ ปพ.5</title>
</head>
<body>
<header>
  <strong><?= htmlspecialchars($school['name_th'], ENT_QUOTES, 'UTF-8') ?></strong>
  <span><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>

  <form method="post" action="/logout">
    <input type="hidden" name="_token"
      value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">ออกจากระบบ</button>
  </form>
</header>

<main>
  <h1>แดชบอร์ด</h1>
  <p>ยินดีต้อนรับเข้าสู่ระบบ ปพ.5</p>
</main>
</body>
</html>
