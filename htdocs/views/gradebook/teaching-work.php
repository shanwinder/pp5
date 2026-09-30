<?php
use App\Support\StatusLabel;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$groups = ['current' => [], 'history' => []];
foreach ($offerings as $offering) {
    $section = $offering['isHistorical'] ? 'history' : 'current';
    $year = (string) $offering['year_be'];
    $room = (int) $offering['classroom_id'];
    $groups[$section][$year][$room]['name'] = $offering['classroom_code'] . ' · ' . $offering['classroom_name'];
    $groups[$section][$year][$room]['offerings'][] = $offering;
}
?>
<?php if ($offerings === []): ?>
<div class="pp5-empty-state"><p><strong>ยังไม่มีรายวิชาที่เข้าถึงได้ในขณะนี้</strong></p><p>เมื่อมีรายวิชาที่คุณเข้าถึงได้ งานจะแสดงที่นี่</p></div>
<?php else: ?>
<?php foreach (['current' => 'งานปัจจุบัน', 'history' => 'ประวัติ'] as $section => $sectionLabel): ?>
<?php if ($groups[$section] === []): continue; endif; ?>
<section class="pp5-teaching-section" aria-labelledby="teaching-<?= $section ?>">
  <h2 id="teaching-<?= $section ?>"><?= $sectionLabel ?></h2>
  <?php foreach ($groups[$section] as $year => $rooms): ?>
  <section aria-label="ปีการศึกษา <?= $escape($year) ?>">
    <h3>ปีการศึกษา <?= $escape($year) ?></h3>
    <?php foreach ($rooms as $room): ?>
    <section class="pp5-teaching-room" aria-label="ห้องเรียน <?= $escape($room['name']) ?>">
      <h4>ห้องเรียน <?= $escape($room['name']) ?></h4>
      <ul class="pp5-teaching-list">
      <?php foreach ($room['offerings'] as $offering): ?>
        <?php $context = $offering['classroom_code'] . ' ' . $offering['subject_name'] . ' ภาคเรียน ' . $offering['term_no'] . ' ปีการศึกษา ' . $year; ?>
        <li class="pp5-teaching-item">
          <div class="pp5-teaching-detail">
            <strong><?= $escape($offering['subject_name']) ?></strong>
            <span><?= $escape($offering['subject_code']) ?> · ภาคเรียน <?= $escape($offering['term_no']) ?></span>
            <span>สถานะปี: <span data-status="<?= $escape($offering['academic_year_status']) ?>"><?= $escape(StatusLabel::text($offering['academic_year_status'], 'academic-year')) ?></span> · สถานะรายวิชา: <span data-status="<?= $escape($offering['status']) ?>"><?= $escape(StatusLabel::text($offering['status'])) ?></span><?= $offering['canScore'] ? '' : ' · อ่านอย่างเดียว' ?></span>
          </div>
          <div class="pp5-teaching-actions">
            <a class="btn <?= $offering['canScore'] ? 'btn-primary' : 'btn-outline-secondary' ?>" href="/gradebook/<?= (int) $offering['id'] ?>"><?= $offering['canScore'] ? 'กรอกคะแนน' : 'ดูคะแนน' ?><span class="visually-hidden"> <?= $escape($context) ?></span></a>
            <?php if ($offering['canSetupScoreStructure']): ?><a class="btn btn-outline-secondary" href="/gradebook/<?= (int) $offering['id'] ?>/setup"><?= $offering['isHistorical'] ? 'ดูการเก็บคะแนน' : 'ตั้งค่าการเก็บคะแนน' ?><span class="visually-hidden"> <?= $escape($context) ?></span></a><?php endif; ?>
            <a href="/workspaces/classrooms/<?= (int) $offering['classroom_id'] ?>">งานชั้นเรียน<span class="visually-hidden"> <?= $escape($context) ?></span></a>
          </div>
        </li>
      <?php endforeach; ?>
      </ul>
    </section>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php endif; ?>
