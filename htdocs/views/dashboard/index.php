<?php
use App\Support\View;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$workAreas = array_values(array_filter($ui['sections'], static fn (array $section): bool => in_array($section['key'], ['students', 'academic', 'management'], true)));
$areaIcons = ['students' => 'users', 'academic' => 'books', 'management' => 'notebook'];
?>
<div class="card pp5-hero mb-3"><div class="card-status-start bg-blue"></div>
  <div class="card-body py-3">
    <h2 class="h2 mb-1">สวัสดี <?= $escape($ui['displayName']) ?></h2>
    <p class="text-secondary mb-0"><?= $escape($ui['schoolName']) ?></p>
  </div>
</div>
<?php if (($ui['classroomWorkspaces'] ?? []) !== []): ?>
<section class="mb-3" aria-labelledby="classroom-workspaces">
  <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
    <div><h2 class="h2 mb-0" id="classroom-workspaces">งานชั้นเรียน</h2></div>
  </div>
  <div class="card"><div class="card-body"><?= View::render('workspaces/classroom/choices', ['targets' => $ui['classroomWorkspaces']]) ?></div></div>
</section>
<?php endif; ?>
<section class="mb-3" aria-labelledby="my-gradebooks">
  <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
    <div><h2 class="h2 mb-0" id="my-gradebooks">สมุดคะแนนของฉัน</h2></div>
    <?php if ($ui['gradebooks'] !== []): ?><a class="btn btn-outline-primary" href="/gradebooks">ดูสมุดคะแนนทั้งหมด</a><?php endif; ?>
  </div>
  <div class="card"><div class="card-body p-0"><?= View::render('gradebook/offering-list', ['offerings' => array_slice($ui['gradebooks'], 0, 3), 'compactActions' => true]) ?></div></div>
</section>
<section class="mb-3" aria-labelledby="work-areas">
  <h2 class="h2 mb-3" id="work-areas">งานจัดการของโรงเรียน</h2>
  <?php if ($workAreas === []): ?>
  <div class="card"><div class="card-body text-secondary">ยังไม่มีงานจัดการที่เข้าถึงได้ หากต้องการความช่วยเหลือ กรุณาติดต่อผู้ดูแลระบบ</div></div>
  <?php else: ?>
  <ul class="row row-cards list-unstyled pp5-work-areas">
    <?php foreach ($workAreas as $area): $entry = $area['items'][0]; ?>
    <li class="col-sm-6 col-xl-4"><div class="card card-link-pop h-100 pp5-work-card pp5-work-card--<?= $escape($area['key']) ?>"><div class="card-status-top"></div><div class="card-body"><span class="pp5-icon-tile pp5-icon-tile--<?= $escape($area['key'] === 'academic' ? 'subjects' : ($area['key'] === 'management' ? 'management' : 'students')) ?> mb-2"><?= View::render('ui/icon', ['name' => $areaIcons[$area['key']]]) ?></span><h3 class="card-title mb-2"><?= $escape($entry['label']) ?></h3><a class="btn btn-primary" href="<?= $escape($entry['url']) ?>">เปิด<?= $escape($entry['label']) ?></a></div></div></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
