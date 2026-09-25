<div class="pp5-admin-page">
<?php if ($permissions['SCHOOL_USER_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/admin/users">ผู้ใช้งาน</a></li><li class="breadcrumb-item active" aria-current="page">เพิ่มข้อมูล</li></ol></nav><?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
  <?php endif; ?>
  <form class="pp5-form pp5-surface" method="post" action="/admin/users">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="pp5-field"><label class="form-label" for="admin-users-create-username">ชื่อผู้ใช้
      <input class="form-control" id="admin-users-create-username" type="text" name="username" minlength="3" maxlength="100" autocomplete="off" required
        value="<?= htmlspecialchars($values['username'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </label></div>
    <div class="pp5-field"><label class="form-label" for="admin-users-create-display_name">ชื่อที่แสดง
      <input class="form-control" id="admin-users-create-display_name" type="text" name="display_name" maxlength="190" required
        value="<?= htmlspecialchars($values['display_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </label></div>
    <div class="pp5-field"><label class="form-label" for="admin-users-create-email">อีเมล (ไม่บังคับ)
      <input class="form-control" id="admin-users-create-email" type="email" name="email" maxlength="190"
        value="<?= htmlspecialchars($values['email'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </label></div>
    <div class="pp5-field"><label class="form-label" for="admin-users-create-password">รหัสผ่าน (อย่างน้อย 12 ตัวอักษร)
      <input class="form-control" id="admin-users-create-password" type="password" name="password" minlength="12" autocomplete="new-password" required>
    </label></div>
    <fieldset>
      <legend>บทบาทโรงเรียน (เลือกอย่างน้อยหนึ่งบทบาท)</legend>
      <?php foreach ($roles as $role): ?>
        <div class="pp5-field"><label class="form-label" for="admin-users-create-role_codes-<?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?>">
          <input class="form-check-input" id="admin-users-create-role_codes-<?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?>" type="checkbox" name="role_codes[]" value="<?= htmlspecialchars($role['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= in_array($role['code'], $values['role_codes'] ?? [], true) ? ' checked' : '' ?>>
          <?= htmlspecialchars($role['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= htmlspecialchars($role['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)
        </label></div>
      <?php endforeach; ?>
    </fieldset>
    <p><button class="btn btn-primary" type="submit">สร้างผู้ใช้</button></p>
  </form>
<p class="pp5-actions"><?php if ($permissions['SCHOOL_USER_VIEW']): ?><a class="btn btn-outline-secondary" href="/admin/users">กลับรายการ</a><?php endif; ?></p>
</div>
