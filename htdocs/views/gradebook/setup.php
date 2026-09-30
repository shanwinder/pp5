<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$active = array_values(array_filter($components, static fn (array $item): bool => $item['status'] === 'ACTIVE'));
$inactive = array_values(array_filter($components, static fn (array $item): bool => $item['status'] === 'INACTIVE'));
?>
<div class="pp5-admin-page pp5-gradebook-page">
  <?php if ($offering !== null): ?>
    <nav class="pp5-actions" aria-label="กลับไปยังงานรายวิชา">
      <?php if ($workspace !== null && ($workspace['capabilities']['subjects'] || $workspace['capabilities']['teaching'] || $workspace['capabilities']['scores'])): ?>
        <a class="btn btn-outline-secondary" href="/workspaces/classrooms/<?= $escape($offering['classroom_id']) ?>/subjects">กลับรายวิชาและครูในห้องนี้</a>
      <?php endif; ?>
      <a href="/gradebooks">กลับงานสอนของฉัน</a>
    </nav>
    <p class="pp5-workspace-context"><strong>การเก็บคะแนน &gt; <?= $escape($offering['subject_name']) ?></strong><br><?= $escape($offering['classroom_name']) ?> · ปีการศึกษา <?= $escape($offering['year_be']) ?> · ภาคเรียนที่ <?= $escape($offering['term_no']) ?></p>
    <?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= $escape($error) ?></p><?php endif; ?>
    <p class="pp5-alert pp5-alert--info"><strong>คะแนนเต็มรวมที่ใช้งานอยู่: <?= $escape($summary['active_max_total']) ?> คะแนน</strong> · <?= $escape($summary['active_count']) ?> รายการคะแนน</p>
    <?php if ($summary['active_count'] === 0): ?><p class="pp5-empty-state">ยังไม่ได้ตั้งค่าการเก็บคะแนนที่ใช้งานอยู่</p><?php endif; ?>
    <?php if (!$canMutate): ?><p class="pp5-alert pp5-alert--info">อ่านอย่างเดียว — ปีการศึกษาปิดแล้วหรือรายวิชาไม่ได้เปิดใช้งาน ข้อมูลเดิมยังดูได้</p><?php endif; ?>
    <?php if ($canMutate): ?>
      <section class="pp5-surface" aria-labelledby="add-score-item"><h2 id="add-score-item">เพิ่มช่องคะแนน</h2>
        <p id="score-max-help">คะแนนเต็มต้องมากกว่า 0 ไม่เกิน 99999.99 และใช้ทศนิยมได้ไม่เกิน 2 ตำแหน่ง เมื่อมีประวัติคะแนนแล้วจะเปลี่ยนคะแนนเต็มไม่ได้ แม้ล้างคะแนนจนเป็นช่องว่าง ประวัติยังคงอยู่</p>
        <form class="pp5-form" method="post" action="/gradebook/<?= $escape($offering['id']) ?>/components">
          <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
          <div class="pp5-field"><label class="form-label" for="score-new-name">หัวข้อคะแนน / ชื่อรายการคะแนน</label><input class="form-control" id="score-new-name" name="name_th" maxlength="190" required></div>
          <div class="pp5-field"><label class="form-label" for="score-new-max">คะแนนเต็ม</label><input class="form-control" id="score-new-max" name="max_score" inputmode="decimal" aria-describedby="score-max-help" required></div>
          <button class="btn btn-primary" type="submit">เพิ่มช่องคะแนน</button>
        </form>
      </section>
    <?php endif; ?>
    <?php foreach ([['title' => 'รายการคะแนนที่ใช้งานอยู่', 'items' => $active], ['title' => 'รายการคะแนนที่ปิดใช้งาน / ประวัติ', 'items' => $inactive]] as $group): ?>
      <section aria-label="<?= $escape($group['title']) ?>"><h2><?= $escape($group['title']) ?></h2>
        <?php if ($group['title'] === 'รายการคะแนนที่ปิดใช้งาน / ประวัติ'): ?><p>รายการที่ปิดใช้งานไม่รวมในคะแนนรวมปัจจุบัน แต่ยังเก็บประวัติไว้</p><?php endif; ?>
        <?php if ($group['items'] === []): ?><p class="pp5-empty-state">ไม่มีรายการคะแนนในส่วนนี้</p><?php endif; ?>
        <?php foreach ($group['items'] as $component): ?>
          <?php $locked = isset($historyIds[(int) $component['id']]); ?>
          <section class="pp5-surface" data-component-id="<?= $escape($component['id']) ?>" aria-labelledby="score-item-<?= $escape($component['id']) ?>">
            <h3 id="score-item-<?= $escape($component['id']) ?>"><?= $escape($component['name_th']) ?></h3>
            <p>คะแนนเต็ม <?= $escape($component['max_score']) ?> · <?= $component['status'] === 'ACTIVE' ? 'ใช้งาน' : 'ปิดใช้งาน / ประวัติ' ?><?= $locked ? ' · มีประวัติคะแนน — คะแนนเต็มแก้ไขไม่ได้' : '' ?></p>
            <details><summary>ข้อมูลอ้างอิงรายการคะแนน</summary><p>รหัสอ้างอิง: <?= $escape($component['code']) ?></p></details>
            <?php if ($canMutate): ?>
              <form class="pp5-form" method="post" action="/gradebook/<?= $escape($offering['id']) ?>/components/<?= $escape($component['id']) ?>">
                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                <div class="pp5-field"><label class="form-label" for="score-<?= $escape($component['id']) ?>-name">หัวข้อคะแนน</label><input class="form-control" id="score-<?= $escape($component['id']) ?>-name" name="name_th" value="<?= $escape($component['name_th']) ?>" maxlength="190" required></div>
                <div class="pp5-field"><label class="form-label" for="score-<?= $escape($component['id']) ?>-max">คะแนนเต็ม</label><input class="form-control" id="score-<?= $escape($component['id']) ?>-max" name="max_score" value="<?= $escape($component['max_score']) ?>" inputmode="decimal"<?= $locked ? ' readonly aria-describedby="score-locked-help-' . $escape($component['id']) . '"' : ' aria-describedby="score-max-help"' ?> required></div>
                <?php if ($locked): ?><p id="score-locked-help-<?= $escape($component['id']) ?>">รายการนี้มีประวัติคะแนน จึงแก้ไขคะแนนเต็มไม่ได้</p><?php endif; ?>
                <button class="btn btn-primary" type="submit" aria-label="<?= $escape('บันทึกการแก้ไข ' . $component['name_th']) ?>">บันทึกการแก้ไข</button>
              </form>
              <form class="pp5-form" method="post" action="/gradebook/<?= $escape($offering['id']) ?>/components/<?= $escape($component['id']) ?>/status">
                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="status" value="<?= $component['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
                <button class="btn btn-outline-secondary" type="submit" aria-label="<?= $escape(($component['status'] === 'ACTIVE' ? 'ปิดใช้งาน ' : 'เปิดใช้งาน ') . $component['name_th']) ?>"><?= $component['status'] === 'ACTIVE' ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?></button>
              </form>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
