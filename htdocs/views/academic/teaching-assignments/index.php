<div class="pp5-admin-page">
<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
  <?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= $escape($error) ?></p><?php endif; ?>

  <form class="pp5-filter-bar" method="get" action="/academic/teaching-assignments">
    <label class="form-label" for="academic-year">ปีการศึกษา</label>
    <select class="form-select" id="academic-year" name="academic_year_id" required>
      <option value="" disabled <?= $selectedYear === null ? 'selected' : '' ?>>เลือกปีการศึกษา</option>
      <?php foreach ($years as $year): ?>
        <option value="<?= $escape($year['id']) ?>" <?= ($selectedYear['id'] ?? null) === $year['id'] ? 'selected' : '' ?>><?= $escape($year['year_be'] . ' — ' . App\Support\StatusLabel::text($year['status'], 'academic-year')) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary" type="submit">แสดงปีที่เลือก</button>
    <a class="btn btn-link" href="/academic/teaching-assignments">แสดงทุกปี</a>
  </form>

  <?php if ($canCreate): ?>
    <section aria-labelledby="create-heading">
      <h2 id="create-heading">เพิ่มการมอบหมาย</h2>
      <form class="pp5-form pp5-surface" method="post" action="/academic/teaching-assignments">
        <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
        <div class="pp5-field"><label class="form-label" for="teacher">ครูประจำวิชา</label>
          <select class="form-select" id="teacher" name="user_role_assignment_id" required>
            <option value="">เลือกครู</option>
            <?php foreach ($teachers as $teacher): ?>
              <option value="<?= $escape($teacher['user_role_assignment_id']) ?>"><?= $escape($teacher['display_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pp5-field"><label class="form-label" for="offering">รายวิชาที่เปิดสอน</label>
          <select class="form-select" id="offering" name="subject_offering_id" required>
            <option value="">เลือกห้องเรียน รายวิชา และภาคเรียน</option>
            <?php foreach ($offerings as $offering): ?>
              <option value="<?= $escape($offering['id']) ?>"><?= $escape($offering['classroom_code'] . ' ' . $offering['classroom_name'] . ' / ' . $offering['subject_code'] . ' ' . $offering['subject_name'] . ' / ภาคเรียน ' . $offering['term_no']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" type="submit" <?= $teachers === [] || $offerings === [] ? 'disabled' : '' ?>>บันทึกการมอบหมาย</button>
      </form>
      <?php if ($teachers === [] || $offerings === []): ?><p class="pp5-empty-state">ยังไม่มีครูหรือรายวิชาที่พร้อมให้มอบหมายในปีนี้</p><?php endif; ?>
    </section>
  <?php elseif ($selectedYear !== null): ?>
    <p>ปีการศึกษานี้ปิดปีแล้ว สามารถดูประวัติได้เท่านั้น</p>
  <?php else: ?>
    <p>เลือกปีการศึกษาเพื่อเพิ่มการมอบหมาย</p>
  <?php endif; ?>

  <h2>ประวัติการมอบหมาย</h2>
  <div class="pp5-table-scroll" role="region" aria-label="การมอบหมายครูประจำวิชา" tabindex="0">
    <table class="table pp5-table pp5-assignment-table">
      <thead><tr><th scope="col">ปีการศึกษา</th><th scope="col">ครู</th><th scope="col">ห้องเรียน</th><th scope="col">รายวิชา</th><th scope="col">ภาคเรียน</th><th scope="col">สถานะรายวิชา</th><th scope="col">สถานะการมอบหมาย</th><th scope="col">จัดการ</th></tr></thead>
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
            <?php if (in_array($assignment['academic_year_status'], ['DRAFT', 'ACTIVE'], true)): ?>
              <form class="pp5-form pp5-surface pp5-sensitive" method="post" action="/academic/teaching-assignments/<?= $escape($assignment['id']) ?>/status">
                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="status" value="<?= $assignment['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
                <button class="btn btn-danger" type="submit"><?= $assignment['status'] === 'ACTIVE' ? 'ปิดการมอบหมาย' : 'เปิดการมอบหมาย' ?></button>
              </form>
            <?php else: ?>ดูประวัติเท่านั้น<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($assignments === []): ?><tr><td class="pp5-empty-state" colspan="8">ยังไม่มีการมอบหมายในรายการที่เลือก</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
