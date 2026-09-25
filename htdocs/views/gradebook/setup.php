<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<div class="pp5-admin-page pp5-gradebook-page">
  <p><a class="btn btn-outline-secondary" href="/gradebooks">กลับรายการสมุดคะแนน</a></p>
  <?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= $escape($error) ?></p><?php endif; ?>
  <?php if ($offering !== null): ?>
    <?= App\Support\View::render('gradebook/metadata', ['offering'=>$offering]) ?>
    <p id="component-max-help" class="pp5-alert pp5-alert--info">คะแนนเต็ม 0.01–99999.99 ใช้จุดทศนิยมได้ไม่เกิน 2 ตำแหน่ง เมื่อเคยบันทึกคะแนนแล้วจะเปลี่ยนคะแนนเต็มไม่ได้ แม้ล้างคะแนนจนเป็นช่องว่าง ประวัติยังคงอยู่</p>
    <p>องค์ประกอบที่ปิดใช้งานไม่รวมในคะแนนรวมปัจจุบัน แต่ยังเก็บประวัติคะแนนไว้</p>
    <?php if ($canMutate): ?>
      <h2>เพิ่มองค์ประกอบคะแนน</h2>
      <form class="pp5-form" method="post" action="/gradebook/<?= $escape($offering['id']) ?>/components">
        <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
        <div class="pp5-field"><label class="form-label" for="component-new-code">รหัส <input class="form-control" id="component-new-code" name="code" maxlength="50" required></label></div>
        <div class="pp5-field"><label class="form-label" for="component-new-name_th">ชื่อ <input class="form-control" id="component-new-name_th" name="name_th" maxlength="190" required></label></div>
        <div class="pp5-field"><label class="form-label" for="component-new-max_score">คะแนนเต็ม <input class="form-control" id="component-new-max_score" aria-describedby="component-max-help" name="max_score" inputmode="decimal" required></label></div>
        <div class="pp5-field"><label class="form-label" for="component-new-sort_order">ลำดับ <input class="form-control" id="component-new-sort_order" name="sort_order" type="number" min="0" max="65535" step="1" value="0" required></label></div>
        <button class="btn btn-primary" type="submit">เพิ่มองค์ประกอบ</button>
      </form>
    <?php else: ?>
      <p class="pp5-alert pp5-alert--info">อ่านอย่างเดียว — แสดงประวัติเท่านั้น ปีการศึกษาปิดแล้วหรือรายวิชาไม่ได้เปิดใช้งาน</p>
    <?php endif; ?>
    <h2>องค์ประกอบคะแนนทั้งหมด</h2>
    <?php if ($components === []): ?><p class="pp5-empty-state">ยังไม่มีองค์ประกอบคะแนน</p><?php endif; ?>
    <?php foreach ($components as $component): ?>
      <section class="pp5-surface" data-component-id="<?= $escape($component['id']) ?>" aria-labelledby="component-<?= $escape($component['id']) ?>">
        <h3 id="component-<?= $escape($component['id']) ?>"><?= $escape($component['code'] . ' — ' . $component['name_th']) ?></h3>
        <p>คะแนนเต็ม <?= $escape($component['max_score']) ?> · ลำดับ <?= $escape($component['sort_order']) ?> · สถานะ <?= App\Support\View::render('ui/status', ['status'=>$component['status']]) ?></p>
        <?php if ($canMutate): ?>
          <form class="pp5-form" method="post" action="/gradebook/<?= $escape($offering['id']) ?>/components/<?= $escape($component['id']) ?>">
            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
            <div class="pp5-field"><label class="form-label" for="component-<?= $escape($component['id']) ?>-code">รหัส <input class="form-control" id="component-<?= $escape($component['id']) ?>-code" name="code" value="<?= $escape($component['code']) ?>" maxlength="50" required></label></div>
            <div class="pp5-field"><label class="form-label" for="component-<?= $escape($component['id']) ?>-name_th">ชื่อ <input class="form-control" id="component-<?= $escape($component['id']) ?>-name_th" name="name_th" value="<?= $escape($component['name_th']) ?>" maxlength="190" required></label></div>
            <div class="pp5-field"><label class="form-label" for="component-<?= $escape($component['id']) ?>-max_score">คะแนนเต็ม <input class="form-control" id="component-<?= $escape($component['id']) ?>-max_score" aria-describedby="component-max-help" name="max_score" value="<?= $escape($component['max_score']) ?>" inputmode="decimal" required></label></div>
            <div class="pp5-field"><label class="form-label" for="component-<?= $escape($component['id']) ?>-sort_order">ลำดับ <input class="form-control" id="component-<?= $escape($component['id']) ?>-sort_order" name="sort_order" type="number" min="0" max="65535" step="1" value="<?= $escape($component['sort_order']) ?>" required></label></div>
            <button class="btn btn-primary" type="submit">บันทึกการแก้ไข</button>
          </form>
          <form class="pp5-form" method="post" action="/gradebook/<?= $escape($offering['id']) ?>/components/<?= $escape($component['id']) ?>/status">
            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="status" value="<?= $component['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
            <button class="btn btn-primary" type="submit"><?= $component['status'] === 'ACTIVE' ? 'ปิดใช้งานองค์ประกอบ' : 'เปิดใช้งานองค์ประกอบ' ?></button>
          </form>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
