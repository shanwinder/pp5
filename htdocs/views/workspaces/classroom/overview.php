<?php
use App\Support\View;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="pp5-admin-page">
  <p>ปีการศึกษา <?= $escape($workspace['academicYear']['year_be']) ?> · <?= $escape($workspace['classroom']['name']) ?> · <?= $escape($workspace['school']['name']) ?></p>
  <section aria-labelledby="workspace-overview">
    <h2 id="workspace-overview">ภาพรวม</h2>
    <dl>
      <dt>ระดับชั้น</dt><dd><?= $escape($workspace['gradeLevel']['name']) ?></dd>
      <dt>สถานะปีการศึกษา</dt><dd><?= View::render('ui/status', ['status' => $workspace['academicYear']['status'], 'kind' => 'academic-year']) ?></dd>
      <dt>สถานะห้องเรียน</dt><dd><?= View::render('ui/status', ['status' => $workspace['classroom']['status'], 'kind' => 'entity']) ?></dd>
    </dl>
    <?php if ($workspace['links'] !== []): ?>
      <ul>
        <?php foreach ($workspace['links'] as $link): ?>
          <li><a href="<?= $escape($link['url']) ?>"><?= $escape($link['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <section aria-labelledby="workspace-scores">
    <h2 id="workspace-scores">สมุดคะแนนที่คุณเข้าถึงได้</h2>
    <?php if ($workspace['gradebooks'] === []): ?>
      <p class="pp5-empty-state">ยังไม่มีสมุดคะแนนที่คุณเข้าถึงได้ในห้องนี้</p>
    <?php else: ?>
      <div class="pp5-table-scroll" role="region" aria-label="สมุดคะแนนในห้องนี้" tabindex="0">
        <table class="table pp5-table">
          <caption>รายวิชาที่คุณเปิดดูคะแนนได้ในห้องนี้</caption>
          <thead><tr><th scope="col">รายวิชา</th><th scope="col">ภาคเรียน</th><th scope="col">สถานะรายวิชา</th><th scope="col">การทำงาน</th></tr></thead>
          <tbody>
          <?php foreach ($workspace['gradebooks'] as $gradebook): ?>
            <tr>
              <th scope="row"><?= $escape($gradebook['subject_code']) ?><br><?= $escape($gradebook['subject_name']) ?></th>
              <td><?= $escape($gradebook['term_no']) ?></td>
              <td><?= View::render('ui/status', ['status' => $gradebook['status'], 'kind' => 'entity']) ?></td>
              <td><a class="btn btn-outline-secondary" href="/gradebook/<?= (int) $gradebook['id'] ?>" aria-label="เปิดสมุดคะแนน <?= $escape($gradebook['subject_name']) ?> ภาคเรียน <?= $escape($gradebook['term_no']) ?>">เปิดสมุดคะแนน</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
