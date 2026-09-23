<?php
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$workAreas = array_values(array_filter($ui['sections'], static fn (array $section): bool => in_array($section['key'], ['students', 'academic', 'management'], true)));
$descriptions = ['students' => 'ตรวจสอบข้อมูลนักเรียนและการลงทะเบียน', 'academic' => 'จัดเตรียมปีการศึกษา ห้องเรียน และรายวิชา', 'management' => 'ดูแลบัญชีผู้ใช้งานของโรงเรียน'];
?>
<p class="pp5-welcome">ยินดีต้อนรับ <?= $escape($ui['displayName']) ?> <span class="text-muted">— <?= $escape($ui['schoolName']) ?></span></p>
<section aria-labelledby="my-gradebooks">
  <div class="pp5-page-header">
    <div><h2 id="my-gradebooks">สมุดคะแนนของฉัน</h2><p class="text-muted">เลือกรายวิชาเพื่อเริ่มงาน หรือดูสมุดคะแนนย้อนหลัง</p></div>
    <?php if ($ui['gradebooks'] !== []): ?><a class="btn btn-primary" href="/gradebooks">ดูสมุดคะแนนทั้งหมด</a><?php endif; ?>
  </div>
  <?= App\Support\View::render('gradebook/offering-list', ['offerings' => array_slice($ui['gradebooks'], 0, 3)]) ?>
</section>
<section aria-labelledby="work-areas">
  <h2 id="work-areas">งานจัดการของโรงเรียน</h2>
  <?php if ($workAreas === []): ?>
    <p class="text-muted">ยังไม่มีงานจัดการที่เข้าถึงได้ หากต้องการความช่วยเหลือ กรุณาติดต่อผู้ดูแลระบบ</p>
  <?php else: ?>
    <ul class="pp5-work-areas">
    <?php foreach ($workAreas as $area): $entry = $area['items'][0]; ?>
      <li><h3><?= $escape($area['label']) ?></h3><p class="text-muted"><?= $escape($descriptions[$area['key']]) ?></p>
        <a href="<?= $escape($entry['url']) ?>">เปิด<?= $escape($entry['label']) ?></a></li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
