<section id="fixture-content" tabindex="-1" aria-labelledby="fixture-form-title">
  <div class="pp5-page-header">
    <div><p class="text-secondary">ข้อมูลตัวอย่างสำหรับตรวจรูปแบบและการใช้งานแป้นพิมพ์</p></div>
    <div class="pp5-actions"><button type="button" class="btn btn-primary">บันทึกตัวอย่าง</button></div>
  </div>
  <div class="card pp5-surface">
    <div class="card-body">
      <h2 id="fixture-form-title" class="card-title">แบบฟอร์ม</h2>
      <form id="fixture-form" class="pp5-form">
        <div class="pp5-field"><label for="fixture-name" class="form-label">ชื่อรายการ <span>(จำเป็น)</span></label>
          <input class="form-control" id="fixture-name" required aria-describedby="fixture-help">
          <div id="fixture-help" class="form-text">ใช้ข้อความภาษาไทยได้</div></div>
        <div class="pp5-field"><label for="fixture-status" class="form-label">สถานะ</label>
          <select class="form-select" id="fixture-status"><option>ใช้งาน</option><option>ปิดใช้งาน</option></select></div>
        <div class="pp5-actions"><button class="btn btn-primary" type="button">บันทึก</button><button class="btn btn-secondary" type="button">ยกเลิก</button><button class="btn btn-danger" type="button">ปิดใช้งาน</button></div>
      </form>
    </div>
  </div>
  <p class="pp5-alert pp5-alert--info" role="status">ข้อมูล: ตัวอย่างนี้ไม่บันทึกข้อมูล</p>
  <p class="pp5-alert pp5-alert--danger" role="alert">ผิดพลาด: โปรดตรวจสอบรายการ</p>
  <p><span class="pp5-badge pp5-badge--success">ใช้งาน</span> <span class="pp5-badge pp5-badge--warning">ร่าง</span></p>
  <div class="pp5-table-scroll pp5-gradebook" tabindex="0" role="region" aria-label="ตารางตัวอย่าง เลื่อนแนวนอนได้">
    <table class="table pp5-table">
      <caption>ตัวอย่างตารางข้อมูลแนวนอน</caption>
      <thead><tr><th scope="col">รายการ</th><?php for ($i = 1; $i <= 12; $i++): ?><th scope="col">องค์ประกอบ <?= $i ?></th><?php endfor; ?></tr></thead>
      <tbody><tr><th scope="row" class="pp5-gradebook-identity">ข้อมูลตัวอย่าง</th><?php for ($i = 1; $i <= 12; $i++): ?><td>0.00</td><?php endfor; ?></tr></tbody>
    </table>
  </div>
  <div class="pp5-empty-state"><h2>ยังไม่มีรายการเพิ่มเติม</h2><p>ข้อความอธิบายขั้นตอนถัดไป</p></div>
</section>
