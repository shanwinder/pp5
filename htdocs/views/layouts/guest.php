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
  <?= $headAssets ?>
</head>
<body class="<?= $escape($bodyClass) ?>">
<main id="main-content">
  <?php if ($pageTitle !== ''): ?><h1><?= $escape($pageTitle) ?></h1><?php endif; ?>
  <?= $content ?>
</main>
<?= $scripts ?>
</body>
</html>
