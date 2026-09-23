<div class="pp5-admin-page">
<?php if ($permissions['SYSTEM_SCHOOL_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/system/schools">รายการโรงเรียน</a></li><li class="breadcrumb-item active" aria-current="page">เพิ่มข้อมูล</li></ol></nav><?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
  <?php endif; ?>

  <form class="pp5-form pp5-surface" method="post" action="/system/schools">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <fieldset>
      <legend>ข้อมูลโรงเรียน</legend>
      <div class="pp5-field"><label class="form-label" for="system-schools-create-school_code">รหัสโรงเรียน
        <input class="form-control" id="system-schools-create-school_code" type="text" name="school_code" minlength="2" maxlength="30" pattern="[A-Za-z0-9._\-]+" required
          value="<?= htmlspecialchars($values['school_code'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      </label></div>
      <div class="pp5-field"><label class="form-label" for="system-schools-create-name_th">ชื่อโรงเรียน
        <input class="form-control" id="system-schools-create-name_th" type="text" name="name_th" maxlength="190" required
          value="<?= htmlspecialchars($values['name_th'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      </label></div>
    </fieldset>
    <fieldset>
      <legend>ผู้ดูแลโรงเรียนคนแรก</legend>
      <div class="pp5-field"><label class="form-label" for="system-schools-create-admin_username">ชื่อผู้ใช้
        <input class="form-control" id="system-schools-create-admin_username" type="text" name="admin_username" minlength="3" maxlength="100" pattern="[A-Za-z0-9._\-]+" autocomplete="off" required
          value="<?= htmlspecialchars($values['admin_username'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      </label></div>
      <div class="pp5-field"><label class="form-label" for="system-schools-create-admin_display_name">ชื่อที่แสดง
        <input class="form-control" id="system-schools-create-admin_display_name" type="text" name="admin_display_name" maxlength="190" required
          value="<?= htmlspecialchars($values['admin_display_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      </label></div>
      <div class="pp5-field"><label class="form-label" for="system-schools-create-admin_email">อีเมล (ไม่บังคับ)
        <input class="form-control" id="system-schools-create-admin_email" type="email" name="admin_email" maxlength="190"
          value="<?= htmlspecialchars($values['admin_email'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      </label></div>
      <div class="pp5-field"><label class="form-label" for="system-schools-create-admin_password">รหัสผ่าน (อย่างน้อย 12 ตัวอักษร)
        <input class="form-control" id="system-schools-create-admin_password" type="password" name="admin_password" minlength="12" autocomplete="new-password" required>
      </label></div>
    </fieldset>
    <p><button class="btn btn-primary" type="submit">สร้างโรงเรียนและผู้ดูแล</button></p>
  </form>
<p class="pp5-actions"><?php if ($permissions['SYSTEM_SCHOOL_VIEW']): ?><a class="btn btn-outline-secondary" href="/system/schools">กลับรายการ</a><?php endif; ?></p>
</div>
