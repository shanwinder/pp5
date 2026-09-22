<?php
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $escape($documentTitle) ?></title>
  <link rel="stylesheet" href="/assets/vendor/bootstrap-5.3.8.min.css">
  <link rel="stylesheet" href="/assets/app.css">
  <script src="/assets/app.js" defer></script>
  <?= $headAssets ?>
</head>
<body class="<?= $escape($bodyClass) ?>">
<?php if ($ui !== null): ?>
<a class="pp5-skip-link" href="#main-content">ข้ามไปยังเนื้อหาหลัก</a>
<div class="pp5-shell">
<header class="pp5-topbar">
  <button type="button" class="btn btn-outline-secondary pp5-nav-toggle" data-nav-toggle aria-controls="app-navigation" aria-expanded="true">เมนูหลัก</button>
  <div class="pp5-identity">
    <strong>ปพ.5<?= $ui['contextType'] === 'SYSTEM' ? ' — ผู้ดูแลระบบ' : '' ?></strong>
    <?php if ($ui['schoolName'] !== null): ?><span class="pp5-school-name"><?= $escape($ui['schoolName']) ?></span><?php endif; ?>
  </div>
  <span class="pp5-user-name"><?= $escape($ui['displayName']) ?></span>
  <form method="post" action="/logout">
    <input type="hidden" name="_token" value="<?= $escape($ui['csrfToken']) ?>">
    <button class="btn btn-outline-secondary" type="submit">ออกจากระบบ</button>
  </form>
</header>
<aside class="pp5-sidebar" id="app-navigation" data-nav-panel tabindex="-1">
  <button type="button" class="btn btn-outline-secondary pp5-nav-close" data-nav-close>ปิดเมนู</button>
  <nav aria-label="เมนูหลัก">
    <?php foreach ($ui['sections'] as $section): ?>
      <section aria-labelledby="nav-<?= $escape($section['key']) ?>">
        <h2 id="nav-<?= $escape($section['key']) ?>"><?= $escape($section['label']) ?></h2>
        <ul>
          <?php foreach ($section['items'] as $item): ?>
            <li><a href="<?= $escape($item['url']) ?>"<?= $item['key'] === $ui['currentKey'] ? ' aria-current="page"' : '' ?>><?= $escape($item['label']) ?><?php if ($item['detail'] !== null): ?><small><?= $escape($item['detail']) ?></small><?php endif; ?></a></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </nav>
</aside>
<?php endif; ?>
<main id="main-content"<?= $ui !== null ? ' class="pp5-content" tabindex="-1"' : '' ?>>
  <?php if ($pageTitle !== ''): ?><h1><?= $escape($pageTitle) ?></h1><?php endif; ?>
  <?= $content ?>
</main>
<?php if ($ui !== null): ?></div><?php endif; ?>
<?= $scripts ?>
</body>
</html>
