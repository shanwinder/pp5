<?php
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $escape($documentTitle) ?></title>
  <link rel="stylesheet" href="/assets/vendor/tabler/tabler-1.6.1.min.css">
  <link rel="stylesheet" href="/assets/tabler-spike.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/tabler-spike.css') ?>">
  <script src="/assets/app.js" defer></script>
  <?= $headAssets ?>
</head>
<body class="pp5-tabler-spike <?= $escape($bodyClass) ?>">
<a class="pp5-skip-link" href="#main-content">ข้ามไปยังเนื้อหาหลัก</a>
<div class="page">
  <header class="navbar navbar-expand-md d-print-none pp5-spike-topbar">
    <div class="container-fluid gap-3">
      <button type="button" class="btn btn-outline-primary pp5-nav-toggle" data-nav-toggle aria-controls="app-navigation" aria-expanded="true">เมนูหลัก</button>
      <a class="navbar-brand text-decoration-none" href="/dashboard" aria-label="ปพ.5 กลับแดชบอร์ด"><span class="pp5-brand-mark" aria-hidden="true">ปพ</span><span>ปพ.5 <small class="d-block fw-normal text-secondary">พื้นที่ทำงานโรงเรียน</small></span></a>
      <span class="pp5-spike-school text-secondary text-truncate"><?= $escape((string) ($ui['schoolName'] ?? '')) ?></span>
      <span class="pp5-spike-user text-truncate"><?= $escape((string) ($ui['displayName'] ?? '')) ?></span>
      <form method="post" action="/logout">
        <input type="hidden" name="_token" value="<?= $escape($ui['csrfToken']) ?>">
        <button class="btn btn-outline-secondary btn-sm" type="submit">ออกจากระบบ</button>
      </form>
    </div>
  </header>
  <aside class="navbar navbar-vertical navbar-expand-lg pp5-spike-sidebar" id="app-navigation" data-nav-panel tabindex="-1">
    <div class="container-fluid d-block">
      <button type="button" class="btn btn-outline-secondary btn-sm pp5-nav-close mb-3" data-nav-close>ปิดเมนู</button>
      <nav aria-label="เมนูหลัก">
        <?php foreach ($ui['sections'] as $section): ?>
        <div class="pp5-spike-nav-section" aria-labelledby="nav-<?= $escape($section['key']) ?>">
          <h2 class="text-secondary text-uppercase small fw-bold" id="nav-<?= $escape($section['key']) ?>"><?= $escape($section['label']) ?></h2>
          <ul class="navbar-nav">
            <?php foreach ($section['items'] as $item): ?>
            <li class="nav-item"><a class="nav-link<?= $item['key'] === $ui['currentKey'] ? ' active' : '' ?>" href="<?= $escape($item['url']) ?>"<?= $item['key'] === $ui['currentKey'] ? ' aria-current="page"' : '' ?>><span class="nav-link-title"><?= $escape($item['label']) ?></span><?php if ($item['detail'] !== null): ?><small class="text-secondary d-block"><?= $escape($item['detail']) ?></small><?php endif; ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endforeach; ?>
      </nav>
    </div>
  </aside>
  <div class="page-wrapper pp5-spike-wrapper">
    <main id="main-content" class="page-body" tabindex="-1">
      <div class="container-xl">
        <?php if ($pageTitle !== ''): ?>
        <div class="page-header d-print-none"><div class="row align-items-center"><div class="col"><div class="page-pretitle">ปพ.5 / งานของโรงเรียน</div><h1 class="page-title"><?= $escape($pageTitle) ?></h1></div></div></div>
        <?php endif; ?>
        <?= $content ?>
      </div>
    </main>
  </div>
</div>
<?= $scripts ?>
</body>
</html>
