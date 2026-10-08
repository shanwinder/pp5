<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$offering = $gradebook['offering'];
$setupUrl = $subjectsUrl ?? '/gradebook/' . (int) $offering['id'] . '/setup';
?>
<header class="card pp5-gradebook-context mb-2" aria-labelledby="gradebook-context-title">
  <div class="card-status-start bg-orange"></div>
  <div class="card-body p-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
      <div class="pp5-gradebook-context-identity">
        <h1 id="gradebook-context-title" class="page-title mb-1">สมุดคะแนน — <?= $escape($offering['subject_name']) ?></h1>
        <p class="mb-1 text-blue fw-semibold"><?= $escape($offering['classroom_code']) ?> · <?= $escape($offering['classroom_name']) ?> · ภาคเรียน <?= $escape($offering['term_no']) ?> · ปีการศึกษา <?= $escape($offering['year_be']) ?></p>
        <p class="small text-secondary mb-0"><span class="text-purple">รหัสวิชา <?= $escape($offering['subject_code']) ?></span> · รายวิชา <?= App\Support\View::render('ui/status', ['status' => $offering['status']]) ?> · ปีการศึกษา <?= App\Support\View::render('ui/status', ['status' => $offering['academic_year_status'], 'kind' => 'academic-year']) ?></p>
      </div>
      <span class="badge <?= $canScore ? 'bg-green-lt text-green' : 'bg-secondary-lt text-secondary' ?>" id="gradebook-mode"><?= $canScore ? 'แก้ไขคะแนนได้' : 'อ่านอย่างเดียว' ?></span>
    </div>
    <nav class="pp5-gradebook-toolbar mt-2" aria-label="งานสมุดคะแนน">
      <a class="btn btn-outline-secondary btn-sm" href="/gradebooks">งานสอนของฉัน</a>
      <?php if ($workspace !== null): ?><a class="btn btn-outline-primary btn-sm" href="/workspaces/classrooms/<?= (int) $workspace['classroom']['id'] ?>"><?= App\Support\View::render('ui/icon', ['name' => 'users']) ?>งานชั้นเรียน</a><?php endif; ?>
      <?php if ($subjectsUrl !== null): ?><a class="btn btn-outline-secondary btn-sm" href="<?= $escape($subjectsUrl) ?>"><?= App\Support\View::render('ui/icon', ['name' => 'books']) ?>รายวิชาและครู</a><?php endif; ?>
      <?php if ($canManageComponents): ?><a class="btn btn-outline-warning btn-sm" href="<?= $escape($setupUrl) ?>"><?= App\Support\View::render('ui/icon', ['name' => 'notebook']) ?>จัดการรายการคะแนน</a><?php endif; ?>
    </nav>
  </div>
</header>
<details class="pp5-gradebook-overview mb-2" id="gradebook-components">
  <summary>ดูรายการคะแนน <strong><?= $escape($gradebook['active_component_count']) ?> รายการ</strong> · คะแนนเต็มรวม <strong><?= $escape($gradebook['configured_max_total']) ?></strong></summary>
  <div class="pt-2">
    <h2 class="h4">รายการคะแนนที่ใช้งานอยู่</h2>
    <?php if ($gradebook['components'] === []): ?><p class="mb-1">ยังไม่มีรายการคะแนนที่ใช้งานอยู่</p><?php else: ?>
      <dl class="pp5-gradebook-component-list mb-2">
        <?php foreach ($gradebook['components'] as $component): ?>
          <div><dt><?= $escape($component['name_th']) ?></dt><dd>เต็ม <?= $escape($component['max_score']) ?> คะแนน</dd></div>
        <?php endforeach; ?>
      </dl>
    <?php endif; ?>
    <p class="small text-secondary mb-0">ข้อมูลรายการคะแนน ณ เวลาเปิดหน้านี้ หากจัดการรายการคะแนนในหน้าอื่น ให้เปิดสมุดคะแนนใหม่เพื่อดูข้อมูลล่าสุด</p>
  </div>
</details>
