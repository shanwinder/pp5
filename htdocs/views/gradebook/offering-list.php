<?php
use App\Support\StatusLabel;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php if ($offerings === []): ?>
<div class="pp5-empty-state">
  <p><strong>ยังไม่มีสมุดคะแนนที่เข้าถึงได้</strong></p>
  <p>เมื่อได้รับมอบหมายรายวิชา สมุดคะแนนจะแสดงที่นี่ หากต้องการความช่วยเหลือ กรุณาติดต่อผู้ดูแลระบบ</p>
</div>
<?php else: ?>
<div class="pp5-table-scroll" role="region" aria-label="รายการสมุดคะแนน" tabindex="0">
<table class="table pp5-table pp5-offering-table">
  <caption>รายวิชาที่คุณเข้าถึงสมุดคะแนนได้</caption>
  <thead><tr>
    <th scope="col">ปีการศึกษา</th><th scope="col">ห้องเรียน</th><th scope="col">รายวิชา</th>
    <th scope="col">ภาคเรียน</th><th scope="col">สถานะปี</th><th scope="col">สถานะรายวิชา</th><th scope="col">การทำงาน</th>
  </tr></thead>
  <tbody>
  <?php foreach ($offerings as $offering): ?>
    <tr>
      <td><?= $escape($offering['year_be']) ?></td>
      <td><?= $escape($offering['classroom_code']) ?><br><?= $escape($offering['classroom_name']) ?></td>
      <th scope="row"><?= $escape($offering['subject_code']) ?><br><?= $escape($offering['subject_name']) ?></th>
      <td><?= $escape($offering['term_no']) ?></td>
      <td><span class="pp5-badge" data-status="<?= $escape($offering['academic_year_status']) ?>"><?= $escape(StatusLabel::text($offering['academic_year_status'], 'academic-year')) ?></span></td>
      <td><span class="pp5-badge" data-status="<?= $escape($offering['status']) ?>"><?= $escape(StatusLabel::text($offering['status'])) ?></span></td>
      <td><a class="btn btn-outline-secondary" href="/gradebook/<?= (int) $offering['id'] ?>">เปิดสมุดคะแนน<span class="visually-hidden"> <?= $escape($offering['year_be'].' '.$offering['classroom_code'].' '.$offering['subject_code'].' ภาคเรียน '.$offering['term_no']) ?></span></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
