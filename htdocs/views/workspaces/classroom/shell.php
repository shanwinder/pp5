<?php
use App\Support\View;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="card pp5-context-card mb-4" aria-label="บริบทงานชั้นเรียน">
  <div class="card-status-start bg-blue"></div>
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
      <div>
        <div class="subheader mb-2">ห้องเรียนที่กำลังทำงาน</div>
        <h2 class="card-title mb-1"><?= $escape($workspace['classroom']['name']) ?></h2>
        <p class="text-secondary mb-2"><?= $escape($workspace['school']['name']) ?> · ปีการศึกษา <?= $escape($workspace['academicYear']['year_be']) ?></p>
        <span class="badge bg-blue-lt text-blue"><?= $escape($workspace['gradeLevel']['name']) ?></span>
      </div>
      <details class="pp5-workspace-switcher">
        <summary class="btn btn-outline-primary">เปลี่ยนห้อง / ปีการศึกษา</summary>
        <div class="card mt-2"><div class="card-body"><?= View::render('workspaces/classroom/choices', ['targets' => $workspace['switchTargets'], 'currentId' => $workspace['classroom']['id']]) ?></div></div>
      </details>
    </div>
  </div>
  <nav class="card-footer" aria-label="งานในห้องเรียน">
    <ul class="nav nav-pills gap-2">
      <?php foreach ($workspace['navigation'] as $item): ?>
      <li class="nav-item"><a class="nav-link<?= $item['active'] ? ' active' : '' ?>" href="<?= $escape($item['url']) ?>"<?= $item['active'] ? ' aria-current="page"' : '' ?>><?= $escape($item['label']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
</section>
