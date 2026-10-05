<?php
use App\Support\View;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="pp5-surface pp5-workspace-shell" aria-label="บริบทงานชั้นเรียน">
  <div class="pp5-workspace-heading">
    <div class="pp5-workspace-context">
      <p class="pp5-workspace-eyebrow">ปพ.5 / ห้องเรียน</p>
      <h2><?= $escape($workspace['classroom']['name']) ?></h2>
      <p>ปีการศึกษา <?= $escape($workspace['academicYear']['year_be']) ?> · <?= $escape($workspace['school']['name']) ?></p>
      <p class="pp5-workspace-grade"><?= $escape($workspace['gradeLevel']['name']) ?></p>
    </div>
    <details class="pp5-workspace-switcher">
      <summary>เปลี่ยนห้อง / ปีการศึกษา</summary>
      <?= View::render('workspaces/classroom/choices', ['targets' => $workspace['switchTargets'], 'currentId' => $workspace['classroom']['id']]) ?>
    </details>
  </div>
  <nav aria-label="งานในห้องเรียน">
    <ul class="pp5-workspace-nav">
    <?php foreach ($workspace['navigation'] as $item): ?>
      <li><a href="<?= $escape($item['url']) ?>"<?= $item['active'] ? ' aria-current="page"' : '' ?>><?= $escape($item['label']) ?></a></li>
    <?php endforeach; ?>
    </ul>
  </nav>
</section>
