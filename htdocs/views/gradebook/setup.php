<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ตั้งค่าโครงสร้างคะแนน — ระบบ ปพ.5</title>
</head>
<body>
<main class="container">
  <h1>ตั้งค่าโครงสร้างคะแนน</h1>
  <p><a href="/academic/offerings">กลับรายการเปิดรายวิชา</a></p>
  <?php if ($error !== null): ?><p class="alert alert-danger" role="alert"><?= $escape($error) ?></p><?php endif; ?>
  <?php if ($offering !== null): ?>
    <dl>
      <dt>ปีการศึกษา</dt><dd><?= $escape($offering['year_be'] . ' — ' . $offering['academic_year_status']) ?></dd>
      <dt>ห้องเรียน</dt><dd><?= $escape($offering['classroom_code'] . ' — ' . $offering['classroom_name']) ?></dd>
      <dt>รายวิชา</dt><dd><?= $escape($offering['subject_code'] . ' — ' . $offering['subject_name']) ?></dd>
      <dt>ภาคเรียน</dt><dd><?= $escape($offering['term_no']) ?></dd>
      <dt>สถานะการเปิดรายวิชา</dt><dd><?= $escape($offering['status']) ?></dd>
    </dl>
    <?php if ($canMutate): ?>
      <h2>เพิ่มองค์ประกอบคะแนน</h2>
      <form method="post" action="/gradebook/<?= $escape($offering['id']) ?>/components">
        <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
        <p><label>รหัส <input name="code" maxlength="50" required></label></p>
        <p><label>ชื่อ <input name="name_th" maxlength="190" required></label></p>
        <p><label>คะแนนเต็ม <input name="max_score" inputmode="decimal" required></label></p>
        <p><label>ลำดับ <input name="sort_order" type="number" min="0" max="65535" step="1" value="0" required></label></p>
        <button type="submit">เพิ่มองค์ประกอบ</button>
      </form>
      <p>คะแนนเต็ม 0.01–99999.99 ใช้จุดทศนิยมได้ไม่เกิน 2 ตำแหน่ง เมื่อมีประวัติคะแนนแล้วจะเปลี่ยนคะแนนเต็มไม่ได้</p>
    <?php else: ?>
      <p>แสดงประวัติเท่านั้น ปีการศึกษาปิดแล้วหรือรายวิชาไม่ได้เปิดใช้งาน</p>
    <?php endif; ?>
    <h2>องค์ประกอบคะแนนทั้งหมด</h2>
    <?php if ($components === []): ?><p>ยังไม่มีองค์ประกอบคะแนน</p><?php endif; ?>
    <?php foreach ($components as $component): ?>
      <section data-component-id="<?= $escape($component['id']) ?>" aria-labelledby="component-<?= $escape($component['id']) ?>">
        <h3 id="component-<?= $escape($component['id']) ?>"><?= $escape($component['code'] . ' — ' . $component['name_th']) ?></h3>
        <p>คะแนนเต็ม <?= $escape($component['max_score']) ?> · ลำดับ <?= $escape($component['sort_order']) ?> · สถานะ <?= $escape($component['status']) ?></p>
        <?php if ($canMutate): ?>
          <form method="post" action="/gradebook/<?= $escape($offering['id']) ?>/components/<?= $escape($component['id']) ?>">
            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
            <p><label>รหัส <input name="code" value="<?= $escape($component['code']) ?>" maxlength="50" required></label></p>
            <p><label>ชื่อ <input name="name_th" value="<?= $escape($component['name_th']) ?>" maxlength="190" required></label></p>
            <p><label>คะแนนเต็ม <input name="max_score" value="<?= $escape($component['max_score']) ?>" inputmode="decimal" required></label></p>
            <p><label>ลำดับ <input name="sort_order" type="number" min="0" max="65535" step="1" value="<?= $escape($component['sort_order']) ?>" required></label></p>
            <button type="submit">บันทึกการแก้ไข</button>
          </form>
          <form method="post" action="/gradebook/<?= $escape($offering['id']) ?>/components/<?= $escape($component['id']) ?>/status">
            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="status" value="<?= $component['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
            <button type="submit"><?= $component['status'] === 'ACTIVE' ? 'ปิดใช้งานองค์ประกอบ' : 'เปิดใช้งานองค์ประกอบ' ?></button>
          </form>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
</body>
</html>
