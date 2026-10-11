<details class="pp5-gradebook-tool" name="gradebook-tools" id="gradebook-help">
  <summary class="btn btn-outline-secondary btn-sm">วิธีกรอกคะแนน</summary>
  <div class="pp5-gradebook-tool-panel">
    <p><?= $guidance ?></p>
    <p>ใช้ลูกศรหรือ Tab เพื่อนำทาง และ Shift+ลูกศรเพื่อขยายช่วงที่เลือก ช่องอ่านอย่างเดียวและประวัติยังเลือกและคัดลอกได้ แต่แก้ไขไม่ได้<?= $canScore ? ' · คำสั่งช่วงที่ส่งขณะกำลังบันทึกจะรอการบันทึกก่อนหน้า หากผลไม่แน่นอนให้โหลดหน้าใหม่เพื่อตรวจสอบก่อนแก้ไขต่อ' : '' ?></p>
    <?php if ($canScore): ?><p class="mb-0">วางคะแนนด้วย Ctrl+V หรือ Cmd+V เริ่มจากมุมซ้ายบนของช่วงที่เลือก · ช่องว่างในตารางที่วางจะล้างคะแนน</p><?php endif; ?>
  </div>
</details>
<?php if ($canScore): ?>
  <details class="pp5-gradebook-tool" name="gradebook-tools" id="gradebook-range-tool" hidden>
    <summary class="btn btn-outline-primary btn-sm">เครื่องมือช่วงคะแนน</summary>
    <form id="gradebook-range-actions" class="pp5-gradebook-tool-panel" aria-label="ใส่คะแนนในช่วงที่เลือก" hidden>
      <p id="gradebook-range-summary">ยังไม่ได้เลือกช่วงคะแนน</p>
      <label class="form-label" for="gradebook-fill-value">คะแนนที่จะใส่</label>
      <input id="gradebook-fill-value" class="form-control mb-2" type="text" inputmode="decimal" autocomplete="off" aria-describedby="gradebook-range-summary">
      <div class="d-flex flex-wrap gap-2">
        <button id="gradebook-fill-submit" class="btn btn-primary" type="submit" disabled>ใส่คะแนน</button>
        <button id="gradebook-clear-submit" class="btn btn-outline-secondary" type="button" disabled>ล้างคะแนน</button>
      </div>
      <p id="gradebook-range-feedback" class="text-danger small mb-0 mt-2"></p>
    </form>
  </details>
<?php endif; ?>
