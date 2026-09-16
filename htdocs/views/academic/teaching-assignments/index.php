<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>การมอบหมายครูประจำวิชา — ระบบ ปพ.5</title>
</head>
<body>
<main class="container">
  <h1>การมอบหมายครูประจำวิชา</h1>
  <p><a href="/dashboard">กลับแดชบอร์ด</a></p>
  <?php if ($error !== null): ?><p class="alert alert-danger" role="alert"><?= $escape($error) ?></p><?php endif; ?>

  <form method="get" action="/academic/teaching-assignments">
    <label for="academic-year">ปีการศึกษา</label>
    <select id="academic-year" name="academic_year_id" required>
      <option value="" disabled <?= $selectedYear === null ? 'selected' : '' ?>>เลือกปีการศึกษา</option>
      <?php foreach ($years as $year): ?>
        <option value="<?= $escape($year['id']) ?>" <?= ($selectedYear['id'] ?? null) === $year['id'] ? 'selected' : '' ?>><?= $escape($year['year_be'] . ' — ' . $year['status']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">แสดงปีที่เลือก</button>
    <a href="/academic/teaching-assignments">แสดงทุกปี</a>
  </form>

  <?php if ($canCreate): ?>
    <section aria-labelledby="create-heading">
      <h2 id="create-heading">เพิ่มการมอบหมาย</h2>
      <form method="post" action="/academic/teaching-assignments">
        <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
        <p><label for="teacher">ครูประจำวิชา</label>
          <select id="teacher" name="user_role_assignment_id" required>
            <option value="">เลือกครู</option>
            <?php foreach ($teachers as $teacher): ?>
              <option value="<?= $escape($teacher['user_role_assignment_id']) ?>"><?= $escape($teacher['display_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </p>
        <p><label for="offering">รายวิชาที่เปิดสอน</label>
          <select id="offering" name="subject_offering_id" required>
            <option value="">เลือกห้องเรียน รายวิชา และภาคเรียน</option>
            <?php foreach ($offerings as $offering): ?>
              <option value="<?= $escape($offering['id']) ?>"><?= $escape($offering['classroom_code'] . ' ' . $offering['classroom_name'] . ' / ' . $offering['subject_code'] . ' ' . $offering['subject_name'] . ' / ภาคเรียน ' . $offering['term_no']) ?></option>
            <?php endforeach; ?>
          </select>
        </p>
        <button type="submit" <?= $teachers === [] || $offerings === [] ? 'disabled' : '' ?>>บันทึกการมอบหมาย</button>
      </form>
      <?php if ($teachers === [] || $offerings === []): ?><p>ยังไม่มีครูหรือรายวิชาที่พร้อมให้มอบหมายในปีนี้</p><?php endif; ?>
    </section>
  <?php elseif ($selectedYear !== null): ?>
    <p>ปีการศึกษานี้ปิดแล้ว สามารถดูประวัติได้เท่านั้น</p>
  <?php else: ?>
    <p>เลือกปีการศึกษาเพื่อเพิ่มการมอบหมาย</p>
  <?php endif; ?>

  <h2>ประวัติการมอบหมาย</h2>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th scope="col">ปีการศึกษา</th><th scope="col">ครู</th><th scope="col">ห้องเรียน</th><th scope="col">รายวิชา</th><th scope="col">ภาคเรียน</th><th scope="col">สถานะรายวิชา</th><th scope="col">สถานะการมอบหมาย</th><th scope="col">จัดการ</th></tr></thead>
      <tbody>
      <?php foreach ($assignments as $assignment): ?>
        <tr data-assignment-id="<?= $escape($assignment['id']) ?>">
          <td><?= $escape($assignment['year_be'] . ' — ' . $assignment['academic_year_status']) ?></td>
          <td><?= $escape($assignment['teacher_display_name']) ?></td>
          <td><?= $escape($assignment['classroom_code'] . ' ' . $assignment['classroom_name']) ?></td>
          <td><?= $escape($assignment['subject_code'] . ' ' . $assignment['subject_name']) ?></td>
          <td><?= $escape($assignment['term_no']) ?></td>
          <td><?= $escape($assignment['offering_status']) ?></td>
          <td><?= $escape($assignment['status']) ?></td>
          <td>
            <?php if (in_array($assignment['academic_year_status'], ['DRAFT', 'ACTIVE'], true)): ?>
              <form method="post" action="/academic/teaching-assignments/<?= $escape($assignment['id']) ?>/status">
                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="status" value="<?= $assignment['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
                <button type="submit"><?= $assignment['status'] === 'ACTIVE' ? 'ปิดการมอบหมาย' : 'เปิดการมอบหมาย' ?></button>
              </form>
            <?php else: ?>ดูประวัติเท่านั้น<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($assignments === []): ?><tr><td colspan="8">ยังไม่มีการมอบหมายในรายการที่เลือก</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
