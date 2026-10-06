<?php
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$base = '/workspaces/classrooms/' . (int) $workspace['classroom']['id'];
?>
<div class="pp5-admin-page pp5-classroom-overview">
  <section aria-labelledby="workspace-overview" class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
      <div><div class="subheader mb-1">พื้นที่ทำงานของห้องนี้</div><h2 class="h2 mb-1" id="workspace-overview">เริ่มงานจากสิ่งที่ต้องดูแล</h2><p class="text-secondary mb-0">นักเรียน → รายวิชาและครู → คะแนน ใน <?= $escape($workspace['classroom']['name']) ?></p></div>
      <div class="d-flex flex-wrap gap-2" aria-label="สถานะบริบท"><span>ปีการศึกษา <?= App\Support\View::render('ui/status', ['status' => $workspace['academicYear']['status'], 'kind' => 'academic-year']) ?></span><span>ห้องเรียน <?= App\Support\View::render('ui/status', ['status' => $workspace['classroom']['status']]) ?></span></div>
    </div>
  </section>
  <div class="row row-cards pp5-task-zones mb-4">
    <?php if ($workspace['capabilities']['students']): ?>
    <section class="col-md-6 col-xl-4 pp5-task-zone pp5-task-zone--students" aria-labelledby="workspace-students-zone"><div class="card h-100 pp5-work-card pp5-work-card--students"><div class="card-status-top"></div><div class="card-body d-flex flex-column"><span class="pp5-icon-tile pp5-icon-tile--students mb-3"><?= App\Support\View::render('ui/icon', ['name' => 'users']) ?></span><div class="subheader mb-3">01 / นักเรียน</div><h2 class="card-title" id="workspace-students-zone">ดูแลรายชื่อนักเรียน</h2><p class="display-6 fw-semibold mb-2 pp5-task-zone-stat"><?= $studentCount === null ? 'รายชื่อในห้อง' : $escape($studentCount) . ' คน' ?></p><p class="text-secondary">ดูสถานะ เลือกนักเรียน และเปิดงานจัดการจากรายชื่อในห้อง</p><a class="btn btn-primary mt-auto align-self-start" href="<?= $escape($base) ?>/students">จัดการนักเรียน</a></div></div></section>
    <?php endif; ?>
    <?php if ($workspace['capabilities']['subjects'] || $workspace['capabilities']['teaching'] || $workspace['capabilities']['scores']): ?>
    <section class="col-md-6 col-xl-4 pp5-task-zone pp5-task-zone--subjects" aria-labelledby="workspace-subjects-zone"><div class="card h-100 pp5-work-card pp5-work-card--subjects"><div class="card-status-top"></div><div class="card-body d-flex flex-column"><span class="pp5-icon-tile pp5-icon-tile--subjects mb-3"><?= App\Support\View::render('ui/icon', ['name' => 'books']) ?></span><div class="subheader mb-3">02 / รายวิชาและครู</div><h2 class="card-title" id="workspace-subjects-zone">งานสอนของห้องนี้</h2><p class="display-6 fw-semibold mb-2 pp5-task-zone-stat"><?= $offeringCount === null ? 'รายการที่เข้าถึงได้' : $escape($offeringCount) . ' รายการ' ?></p><p class="text-secondary">รายวิชาที่เปิดสอนในแต่ละภาคเรียนและครูผู้สอนที่คุณเข้าถึงได้</p><a class="btn btn-outline-primary mt-auto align-self-start pp5-task-zone-action" href="<?= $escape($base) ?>/subjects">ดูรายวิชาและครู</a></div></div></section>
    <?php endif; ?>
    <?php if ($workspace['capabilities']['scores']): ?>
    <section class="col-md-6 col-xl-4 pp5-task-zone pp5-task-zone--scores" aria-labelledby="workspace-score-zone"><div class="card h-100 pp5-work-card pp5-work-card--scores"><div class="card-status-top"></div><div class="card-body d-flex flex-column"><span class="pp5-icon-tile pp5-icon-tile--scores mb-3"><?= App\Support\View::render('ui/icon', ['name' => 'notebook']) ?></span><div class="subheader mb-3">03 / คะแนน</div><h2 class="card-title" id="workspace-score-zone">สมุดคะแนนที่เปิดได้</h2><p class="display-6 fw-semibold mb-2 pp5-task-zone-stat"><?= $escape(count($workspace['gradebooks'])) ?> รายการ</p><p class="text-secondary"><?= $configuredCount === null ? 'เลือกสมุดคะแนนที่เข้าถึงได้' : $escape($configuredCount) . ' รายการมีหัวข้อคะแนนที่ใช้งานอยู่' ?></p><a class="btn btn-outline-primary mt-auto align-self-start pp5-task-zone-action" href="#workspace-scores">เลือกสมุดคะแนน</a></div></div></section>
    <?php endif; ?>
  </div>
  <?php if ($workspace['capabilities']['scores']): ?>
  <section id="workspace-scores" aria-labelledby="workspace-scores-heading" class="card pp5-workspace-data mb-4">
    <div class="card-header"><div><div class="subheader">เลือกงานคะแนน</div><h2 class="card-title mb-0" id="workspace-scores-heading">สมุดคะแนนที่คุณเข้าถึงได้</h2></div></div>
    <?php if ($workspace['gradebooks'] === []): ?>
      <div class="card-body text-secondary pp5-empty-state">ยังไม่มีสมุดคะแนนที่คุณเข้าถึงได้ในห้องนี้</div>
    <?php else: ?>
      <div class="pp5-table-scroll table-responsive" role="region" aria-label="สมุดคะแนนในห้องนี้" tabindex="0"><table class="table table-vcenter card-table pp5-table"><caption>รายวิชาที่คุณเปิดดูคะแนนได้ในห้องนี้</caption><thead><tr><th scope="col">รายวิชา</th><th scope="col">ภาคเรียน</th><th scope="col">สถานะรายวิชา</th><th scope="col">การทำงาน</th></tr></thead><tbody>
      <?php foreach ($workspace['gradebooks'] as $gradebook): ?><tr><th scope="row"><span class="text-secondary"><?= $escape($gradebook['subject_code']) ?></span><br><?= $escape($gradebook['subject_name']) ?></th><td><?= $escape($gradebook['term_no']) ?></td><td><?= App\Support\View::render('ui/status', ['status' => $gradebook['status']]) ?></td><td><a class="btn btn-outline-primary btn-sm btn-icon pp5-row-action" href="/gradebook/<?= (int) $gradebook['id'] ?>" aria-label="เปิดสมุดคะแนน <?= $escape($gradebook['subject_name']) ?> ภาคเรียน <?= $escape($gradebook['term_no']) ?>" title="เปิดสมุดคะแนน" data-tooltip="เปิดสมุดคะแนน"><?= App\Support\View::render('ui/icon', ['name' => 'notebook']) ?></a></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
