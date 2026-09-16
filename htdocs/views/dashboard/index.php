<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แดชบอร์ด — ระบบ ปพ.5</title>
</head>
<body>
<header>
  <strong><?= htmlspecialchars($school['name_th'], ENT_QUOTES, 'UTF-8') ?></strong>
  <span><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>

  <form method="post" action="/logout">
    <input type="hidden" name="_token"
      value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">ออกจากระบบ</button>
  </form>
</header>

<main>
  <h1>แดชบอร์ด</h1>
  <p>ยินดีต้อนรับเข้าสู่ระบบ ปพ.5</p>
  <?php if ($canManageUsers): ?>
    <p><a href="/admin/users">จัดการผู้ใช้</a></p>
  <?php endif; ?>
  <?php if ($canViewAcademicSetup): ?>
    <p><a href="/academic/years">จัดการโครงสร้างวิชาการ</a></p>
  <?php endif; ?>
  <?php if ($canManageTeachingAssignments): ?>
    <p><a href="/academic/teaching-assignments">การมอบหมายครูประจำวิชา</a></p>
  <?php endif; ?>
  <?php if ($canViewStudents): ?>
    <p><a href="/students">จัดการนักเรียน</a></p>
    <p><a href="/academic/enrollments">การลงทะเบียนนักเรียน</a></p>
  <?php endif; ?>
  <?php if ($canImportStudents): ?>
    <p><a href="/academic/student-import">นำเข้านักเรียน</a></p>
  <?php endif; ?>
  <?php if ($gradebookOfferings !== []): ?>
    <section aria-labelledby="gradebooks-heading">
      <h2 id="gradebooks-heading">สมุดคะแนน</h2>
      <ul>
        <?php foreach ($gradebookOfferings as $offering): ?>
          <li><a href="/gradebook/<?= htmlspecialchars((string) $offering['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(
              $offering['year_be'] . ' / ' . $offering['classroom_code'] . ' ' . $offering['classroom_name'] . ' / '
              . $offering['subject_code'] . ' ' . $offering['subject_name'] . ' / ภาคเรียน ' . $offering['term_no'], ENT_QUOTES, 'UTF-8') ?></a>
            — <?= htmlspecialchars($offering['academic_year_status'] . ' / ' . $offering['status'], ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
