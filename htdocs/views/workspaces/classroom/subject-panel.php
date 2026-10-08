<?php
use App\Support\View;
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$room = $work['workspace']['classroom'];
$year = $work['workspace']['academicYear'];
$base = '/hx/workspaces/classrooms/' . $room['id'] . '/subjects/' . $offering['id'];
$active = array_values(array_filter($components, static fn (array $item): bool => $item['status'] === 'ACTIVE'));
$inactive = array_values(array_filter($components, static fn (array $item): bool => $item['status'] === 'INACTIVE'));
$formHx = ' hx-target="#subject-workspace-content" hx-swap="outerHTML"';
?>
<section class="card pp5-subject-context-card" data-selected-offering="<?= $escape($offering['id']) ?>" aria-labelledby="subject-context-heading">
  <div class="card-status-top bg-purple"></div>
  <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div><div class="subheader">รายวิชาที่เลือก · <?= $escape($offering['code']) ?></div><h3 class="card-title mb-0" id="subject-context-heading" tabindex="-1"><?= $escape($offering['name']) ?> · ภาคเรียน <?= $escape($offering['term']) ?></h3></div>
    <button class="btn btn-outline-secondary btn-sm" type="button" data-subject-close>ปิดข้อมูล</button>
  </div>
  <div class="card-body">
    <div role="status" aria-live="polite">
      <?php if ($success !== null): ?><div class="alert alert-success" tabindex="-1" data-subject-result><?= $escape($success) ?></div><?php endif; ?>
      <?php if ($error !== null): ?><div class="alert alert-danger" role="alert" id="subject-workflow-error" tabindex="-1" data-subject-result><?= $escape($error) ?></div><?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
      <span><?= $escape($room['name']) ?> · ปีการศึกษา <?= $escape($year['year_be']) ?></span>
      <?= View::render('ui/status', ['status' => $offering['status'], 'kind' => 'entity']) ?>
    </div>
    <nav class="d-flex flex-wrap gap-3 mb-4" aria-label="ส่วนของรายวิชา"><a href="#subject-detail">รายละเอียดรายวิชา</a><a href="#subject-teachers">ครูผู้สอน</a><a href="#subject-scores">การเก็บคะแนน</a></nav>
    <section id="subject-detail" class="pp5-subject-section" aria-labelledby="subject-detail-heading">
      <h4 class="h4" id="subject-detail-heading">รายละเอียดรายวิชา</h4>
      <dl class="row mb-2"><dt class="col-sm-3">รายวิชา</dt><dd class="col-sm-9"><?= $escape($offering['code']) ?> · <?= $escape($offering['name']) ?></dd><dt class="col-sm-3">ห้อง / ภาคเรียน</dt><dd class="col-sm-9"><?= $escape($room['name']) ?> · ภาคเรียน <?= $escape($offering['term']) ?></dd></dl>
      <div class="d-flex flex-wrap gap-3">
        <?php if ($offering['canEdit']): ?><a href="/academic/offerings/<?= $escape($offering['id']) ?>/edit">แก้ไขการเปิดรายวิชา / สถานะ</a><?php endif; ?>
        <?php if ($offering['canOpenGradebook']): ?><a href="/gradebook/<?= $escape($offering['id']) ?>">เปิดสมุดคะแนน</a><?php endif; ?>
        <?php if ($offering['canSetup']): ?><a href="/gradebook/<?= $escape($offering['id']) ?>/setup">หน้าตั้งค่าคะแนนแบบเต็ม</a><?php endif; ?>
      </div>
    </section>
    <section id="subject-teachers" class="pp5-subject-section mt-4" aria-labelledby="subject-teachers-heading">
      <h4 class="h4" id="subject-teachers-heading">ครูผู้สอน</h4>
      <?php if (!$offering['teacherNamesVisible']): ?><p class="text-secondary">ไม่มีสิทธิ์ดูข้อมูลครูผู้สอน</p>
      <?php elseif ($offering['teachers'] === []): ?><p class="text-secondary">ยังไม่มีครูที่กำลังสอน</p><?php endif; ?>
      <?php if ($offering['teacherNamesVisible'] && $offering['teachers'] !== []): ?>
        <ul class="list-group mb-3">
          <?php foreach ($offering['teachers'] as $teacher): ?>
            <li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2"><span><?= $escape($teacher['name']) ?></span>
              <?php if ($canMutateAssignments): ?>
                <form method="post" action="<?= $escape($base . '/assignments/' . $teacher['id'] . '/status') ?>" hx-post="<?= $escape($base . '/assignments/' . $teacher['id'] . '/status') ?>"<?= $formHx ?> hx-confirm="<?= $escape('ยืนยันหยุดการมอบหมาย ' . $teacher['name'] . ' จาก ' . $offering['name'] . ' ภาคเรียน ' . $offering['term'] . '? ประวัติจะยังคงอยู่') ?>" data-confirm="<?= $escape('ยืนยันหยุดการมอบหมาย ' . $teacher['name'] . ' จาก ' . $offering['name'] . ' ภาคเรียน ' . $offering['term'] . '? ประวัติจะยังคงอยู่') ?>">
                  <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="status" value="INACTIVE">
                  <button class="btn btn-outline-danger btn-sm" type="submit">หยุดมอบหมาย <?= $escape($teacher['name']) ?></button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($canMutateAssignments): ?>
        <?php if ($work['teacherChoices'] === []): ?><p class="text-secondary">ไม่มีครูที่มีสิทธิ์รับการมอบหมายในขณะนี้</p><?php else: ?>
          <form method="post" action="<?= $escape($base . '/assignments') ?>" hx-post="<?= $escape($base . '/assignments') ?>"<?= $formHx ?><?= $error !== null ? ' aria-describedby="subject-workflow-error"' : '' ?> class="row g-2 align-items-end">
            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
            <div class="col-sm-7 col-lg-5"><label class="form-label" for="subject-teacher-choice">มอบหมายครูประจำวิชา</label><select class="form-select" id="subject-teacher-choice" name="user_role_assignment_id" required><option value="">เลือกครู</option><?php foreach ($work['teacherChoices'] as $choice): ?><option value="<?= $escape($choice['user_role_assignment_id']) ?>"><?= $escape($choice['display_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-auto"><button class="btn btn-primary" type="submit">มอบหมายครู</button></div>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <section id="subject-scores" class="pp5-subject-section mt-4" aria-labelledby="subject-scores-heading">
      <h4 class="h4" id="subject-scores-heading">การเก็บคะแนน</h4>
      <p class="alert alert-info py-2 mb-3"><strong>คะแนนเต็มรวมที่ใช้งานอยู่ <?= $escape($offering['scoreSummary']['active_max_total']) ?></strong> · <?= $escape($offering['scoreSummary']['active_count']) ?> รายการ · <?= $escape($offering['scoreSummary']['inactive_count']) ?> รายการปิดใช้งาน</p>
      <?php if (!$canReadComponents): ?><p class="text-secondary">ไม่มีสิทธิ์ดูรายละเอียดรายการคะแนน</p><?php else: ?>
        <?php if ($canMutateComponents): ?>
          <details class="pp5-subject-disclosure mb-3"<?= $error !== null ? ' open' : '' ?>><summary class="btn btn-outline-primary">เพิ่มรายการคะแนน</summary>
            <form method="post" action="<?= $escape($base . '/components') ?>" hx-post="<?= $escape($base . '/components') ?>"<?= $formHx ?> class="row g-2 align-items-end mt-2"<?= $error !== null ? ' aria-describedby="subject-workflow-error"' : '' ?>>
              <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
              <div class="col-sm-6 col-lg-5"><label class="form-label" for="subject-new-score-name">ชื่อรายการคะแนน</label><input class="form-control" id="subject-new-score-name" name="name_th" maxlength="190" value="<?= $escape(($old['kind'] ?? null) === 'component' && is_string($old['name_th'] ?? null) ? $old['name_th'] : '') ?>" required></div>
              <div class="col-sm-3 col-lg-2"><label class="form-label" for="subject-new-score-max">คะแนนเต็ม</label><input class="form-control" id="subject-new-score-max" name="max_score" inputmode="decimal" value="<?= $escape(($old['kind'] ?? null) === 'component' && is_string($old['max_score'] ?? null) ? $old['max_score'] : '') ?>" aria-describedby="subject-score-help" required></div>
              <div class="col-auto"><button class="btn btn-primary" type="submit">เพิ่มรายการ</button></div>
            </form>
          </details>
          <p id="subject-score-help" class="text-secondary small">คะแนนเต็ม 0.01–99999.99 ทศนิยมไม่เกิน 2 ตำแหน่ง เมื่อมีประวัติคะแนนแล้วจะเปลี่ยนคะแนนเต็มไม่ได้ แม้ล้างคะแนนแล้ว</p>
        <?php endif; ?>
        <?php foreach ([['title' => 'รายการที่ใช้งานอยู่', 'items' => $active], ['title' => 'รายการปิดใช้งาน / ประวัติ', 'items' => $inactive]] as $group): ?>
          <?php if ($group['title'] === 'รายการปิดใช้งาน / ประวัติ' && !$offering['canSetup']) { continue; } ?>
          <div class="mt-3"><h5 class="h5 mb-2"><?= $escape($group['title']) ?></h5>
            <?php if ($group['items'] === []): ?><p class="text-secondary">ไม่มีรายการในส่วนนี้</p><?php endif; ?>
            <?php foreach ($group['items'] as $item): $locked = isset($historyIds[(int) $item['id']]); ?>
              <details class="pp5-subject-disclosure pp5-score-item mb-2" data-component-id="<?= $escape($item['id']) ?>"<?= $error !== null && ($old['kind'] ?? null) === 'componentUpdate' && ($old['resourceId'] ?? null) === (int) $item['id'] ? ' open' : '' ?>>
                <summary><strong><?= $escape($item['name_th']) ?></strong> · คะแนนเต็ม <?= $escape($item['max_score']) ?><?= $locked ? ' · มีประวัติคะแนน' : '' ?></summary>
                <?php if ($canMutateComponents): ?>
                  <div class="pt-3">
                    <form method="post" action="<?= $escape($base . '/components/' . $item['id']) ?>" hx-post="<?= $escape($base . '/components/' . $item['id']) ?>"<?= $formHx ?> class="row g-2 align-items-end mb-3"<?= $error !== null ? ' aria-describedby="subject-workflow-error"' : '' ?>>
                      <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                      <div class="col-sm-6 col-lg-5"><label class="form-label" for="subject-score-<?= $escape($item['id']) ?>-name">ชื่อรายการคะแนน</label><input class="form-control" id="subject-score-<?= $escape($item['id']) ?>-name" name="name_th" value="<?= $escape(($old['kind'] ?? null) === 'componentUpdate' && ($old['resourceId'] ?? null) === (int) $item['id'] && is_string($old['name_th'] ?? null) ? $old['name_th'] : $item['name_th']) ?>" maxlength="190" required></div>
                      <div class="col-sm-3 col-lg-2"><label class="form-label" for="subject-score-<?= $escape($item['id']) ?>-max">คะแนนเต็ม</label><input class="form-control" id="subject-score-<?= $escape($item['id']) ?>-max" name="max_score" value="<?= $escape(!$locked && ($old['kind'] ?? null) === 'componentUpdate' && ($old['resourceId'] ?? null) === (int) $item['id'] && is_string($old['max_score'] ?? null) ? $old['max_score'] : $item['max_score']) ?>" inputmode="decimal"<?= $locked ? ' readonly aria-describedby="subject-score-locked-' . $escape($item['id']) . '"' : ' aria-describedby="subject-score-help"' ?> required></div>
                      <div class="col-auto"><button class="btn btn-primary" type="submit">บันทึกการแก้ไข</button></div>
                    </form>
                    <?php if ($locked): ?><p class="text-secondary small" id="subject-score-locked-<?= $escape($item['id']) ?>">รายการนี้มีประวัติคะแนน จึงแก้ไขคะแนนเต็มไม่ได้</p><?php endif; ?>
                    <form method="post" action="<?= $escape($base . '/components/' . $item['id'] . '/status') ?>" hx-post="<?= $escape($base . '/components/' . $item['id'] . '/status') ?>"<?= $formHx ?> data-confirm="<?= $escape(($item['status'] === 'ACTIVE' ? 'ยืนยันปิดใช้งาน ' : 'ยืนยันเปิดใช้งาน ') . $item['name_th'] . ' ใน ' . $offering['name'] . ' ภาคเรียน ' . $offering['term'] . '? ประวัติยังคงอยู่') ?>" hx-confirm="<?= $escape(($item['status'] === 'ACTIVE' ? 'ยืนยันปิดใช้งาน ' : 'ยืนยันเปิดใช้งาน ') . $item['name_th'] . ' ใน ' . $offering['name'] . ' ภาคเรียน ' . $offering['term'] . '? ประวัติยังคงอยู่') ?>">
                      <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="status" value="<?= $item['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
                      <button class="btn btn-outline-secondary btn-sm" type="submit"><?= $item['status'] === 'ACTIVE' ? 'ปิดใช้งานรายการนี้' : 'เปิดใช้งานรายการนี้' ?></button>
                    </form>
                  </div>
                <?php endif; ?>
              </details>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </div>
</section>
