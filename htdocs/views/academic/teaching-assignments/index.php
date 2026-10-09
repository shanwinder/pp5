<div class="pp5-admin-page pp5-admin-tabler">
<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
  <?php if ($error !== null): ?><p class="alert alert-danger" role="alert"><?= $escape($error) ?></p><?php endif; ?>

  <?php if (($workspace ?? null) !== null): ?>
<p class="text-muted">หน้านี้แสดงทุกห้องในปีการศึกษา <?= (int) $workspace['academicYear']['year_be'] ?> <a href="/academic/teaching-assignments">เลือกปีนอกงานชั้นเรียน</a></p>
<?php else: ?>
<form class="d-flex flex-wrap align-items-end gap-2 mb-3 pp5-admin-filter" method="get" action="/academic/teaching-assignments">
    <label class="form-label" for="academic-year">ปีการศึกษา</label>
    <select class="form-select w-auto" id="academic-year" name="academic_year_id" required>
      <option value="" disabled <?= $selectedYear === null ? 'selected' : '' ?>>เลือกปีการศึกษา</option>
      <?php foreach ($years as $year): ?>
        <option value="<?= $escape($year['id']) ?>" <?= ($selectedYear['id'] ?? null) === $year['id'] ? 'selected' : '' ?>><?= $escape($year['year_be'] . ' — ' . App\Support\StatusLabel::text($year['status'], 'academic-year')) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary" type="submit">แสดงปีที่เลือก</button>
    <a class="btn btn-link" href="/academic/teaching-assignments">แสดงทุกปี</a>
  </form>
<?php endif; ?>

  <?php if ($canCreate): ?>
    <details class="mb-3"<?= $error !== null ? ' open' : '' ?>><summary class="btn btn-primary">เพิ่มการมอบหมาย</summary><section class="mt-3" aria-labelledby="create-heading">
      <h2 id="create-heading">เพิ่มการมอบหมาย</h2>
      <form class="card card-body mb-3" method="post" action="/academic/teaching-assignments">
    <div class="card-status-top bg-purple" aria-hidden="true"></div>
        <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
        <div class="mb-3"><label class="form-label" for="teacher">ครูประจำวิชา</label>
          <select class="form-select" id="teacher" name="user_role_assignment_id" required>
            <option value="">เลือกครู</option>
            <?php foreach ($teachers as $teacher): ?>
              <option value="<?= $escape($teacher['user_role_assignment_id']) ?>"><?= $escape($teacher['display_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3"><label class="form-label" for="offering">รายวิชาที่เปิดสอน</label>
          <select class="form-select" id="offering" name="subject_offering_id" required>
            <option value="">เลือกห้องเรียน รายวิชา และภาคเรียน</option>
            <?php foreach ($offerings as $offering): ?>
              <option value="<?= $escape($offering['id']) ?>"><?= $escape($offering['classroom_code'] . ' ' . $offering['classroom_name'] . ' / ' . $offering['subject_code'] . ' ' . $offering['subject_name'] . ' / ภาคเรียน ' . $offering['term_no']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" type="submit" <?= $teachers === [] || $offerings === [] ? 'disabled' : '' ?>>บันทึกการมอบหมาย</button>
      </form>
      <?php if ($teachers === [] || $offerings === []): ?><p class="text-secondary p-3 mb-0">ยังไม่มีครูหรือรายวิชาที่พร้อมให้มอบหมายในปีนี้</p><?php endif; ?>
    </section></details>
  <?php elseif ($selectedYear !== null): ?>
    <p>ปีการศึกษานี้ปิดปีแล้ว สามารถดูประวัติได้เท่านั้น</p>
  <?php else: ?>
    <p>เลือกปีการศึกษาเพื่อเพิ่มการมอบหมาย</p>
  <?php endif; ?>

  <h2 class="h3">ประวัติการมอบหมาย</h2>
  <div class="card pp5-table-scroll" role="region" aria-label="การมอบหมายครูประจำวิชา" tabindex="0">
    <div class="card-status-top bg-purple" aria-hidden="true"></div>
<table class="table table-vcenter table-hover mb-0 pp5-table pp5-assignment-table">
      <thead><tr><th scope="col">ปีการศึกษา</th><th scope="col">ครู</th><th scope="col">ห้องเรียน</th><th scope="col">รายวิชา</th><th scope="col">ภาคเรียน</th><th scope="col">สถานะรายวิชา</th><th scope="col">สถานะการมอบหมาย</th><th scope="col" class="pp5-admin-actions">จัดการ</th></tr></thead>
      <tbody>
      <?php foreach ($assignments as $assignment): ?>
        <tr data-assignment-id="<?= $escape($assignment['id']) ?>">
          <th scope="row"><?= $escape($assignment['year_be']) ?> <?= App\Support\View::render('ui/status', ['status'=>$assignment['academic_year_status'], 'kind'=>'academic-year']) ?></th>
          <td><?= $escape($assignment['teacher_display_name']) ?></td>
          <td><?= $escape($assignment['classroom_code'] . ' ' . $assignment['classroom_name']) ?></td>
          <td><?= $escape($assignment['subject_code'] . ' ' . $assignment['subject_name']) ?></td>
          <td><?= $escape($assignment['term_no']) ?></td>
          <td><?= App\Support\View::render('ui/status', ['status'=>$assignment['offering_status'], 'kind'=>'assignment']) ?></td>
          <td><?= App\Support\View::render('ui/status', ['status'=>$assignment['status'], 'kind'=>'assignment']) ?></td>
          <td>
            <?php if ($permissions['TEACHING_ASSIGNMENT_MANAGE'] && in_array($assignment['academic_year_status'], ['DRAFT', 'ACTIVE'], true)): ?>
              <details data-admin-detail><summary class="btn btn-sm btn-icon btn-outline-secondary pp5-row-action" aria-label="เปลี่ยนสถานะ <?= htmlspecialchars($assignment['teacher_display_name'] . ' / ' . $assignment['subject_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="เปลี่ยนสถานะ <?= htmlspecialchars($assignment['teacher_display_name'] . ' / ' . $assignment['subject_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-tooltip="เปลี่ยนสถานะ <?= htmlspecialchars($assignment['teacher_display_name'] . ' / ' . $assignment['subject_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= App\Support\View::render('ui/icon', ['name'=>'dots-vertical']) ?></summary><div class="pp5-admin-row-panel pt-3"><h3 class="h4">เปลี่ยนสถานะ <?= htmlspecialchars($assignment['teacher_display_name'] . ' / ' . $assignment['subject_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3><form class="card card-body mb-3 border-danger" method="post" action="/academic/teaching-assignments/<?= $escape($assignment['id']) ?>/status" data-confirm="ยืนยันการเปลี่ยนสถานะการมอบหมายหรือไม่? มีผลต่อสิทธิ์งานสอนของครู โดยเก็บประวัติเดิมไว้"><p class="text-secondary">การเปลี่ยนสถานะมีผลต่อสิทธิ์งานสอนของครู โดยเก็บประวัติเดิมไว้</p>
                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="status" value="<?= $assignment['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
                <button class="btn btn-danger align-self-start" type="submit"><?= $assignment['status'] === 'ACTIVE' ? 'ปิดการมอบหมาย' : 'เปิดการมอบหมาย' ?></button>
              </form></div></details>
            <?php else: ?>ดูประวัติเท่านั้น<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($assignments === []): ?><tr><td class="text-secondary p-3 mb-0" colspan="8">ยังไม่มีการมอบหมายในรายการที่เลือก</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
