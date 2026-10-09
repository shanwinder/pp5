<div class="pp5-admin-page pp5-admin-tabler pp5-admin-editor">
<?php if ($permissions['SCHOOL_USER_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/admin/users">ผู้ใช้งาน</a></li><li class="breadcrumb-item active" aria-current="page">เพิ่มข้อมูล</li></ol></nav><?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
  <?php endif; ?>
  <form class="card card-body mb-3" method="post" action="/admin/users">
    <div class="card-status-top bg-blue" aria-hidden="true"></div>
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="mb-3"><label class="form-label" for="admin-users-create-username">ชื่อผู้ใช้ <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="admin-users-create-username" type="text" name="username" minlength="3" maxlength="100" autocomplete="off" required
        value="<?= htmlspecialchars($values['username'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </div>
    <div class="mb-3"><label class="form-label" for="admin-users-create-display_name">ชื่อที่แสดง <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="admin-users-create-display_name" type="text" name="display_name" maxlength="190" required
        value="<?= htmlspecialchars($values['display_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </div>
    <div class="mb-3"><label class="form-label" for="admin-users-create-email">อีเมล (ไม่บังคับ)</label><input class="form-control" id="admin-users-create-email" type="email" name="email" maxlength="190"
        value="<?= htmlspecialchars($values['email'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </div>
    <div class="mb-3"><label class="form-label" for="admin-users-create-password">รหัสผ่าน (อย่างน้อย 12 ตัวอักษร) <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="admin-users-create-password" type="password" name="password" minlength="12" autocomplete="new-password" required>
    </div>
    <fieldset>
      <legend class="h3 mb-3">บทบาทโรงเรียน (เลือกอย่างน้อยหนึ่งบทบาท)</legend>
      <?php foreach ($roles as $role): ?>
        <div class="form-check mb-2"><input class="form-check-input" id="admin-users-create-role_codes-<?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?>" type="checkbox" name="role_codes[]" value="<?= htmlspecialchars($role['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= in_array($role['code'], $values['role_codes'] ?? [], true) ? ' checked' : '' ?>>
          <label class="form-check-label" for="admin-users-create-role_codes-<?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($role['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= htmlspecialchars($role['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)</label></div>
      <?php endforeach; ?>
    </fieldset>
    <div class="card-footer px-0 pb-0 mt-2"><button class="btn btn-primary align-self-start" type="submit">สร้างผู้ใช้</button></div>
  </form>
<p class="d-flex flex-wrap gap-2 mt-3"><?php if ($permissions['SCHOOL_USER_VIEW']): ?><a class="btn btn-outline-secondary" href="/admin/users">กลับรายการ</a><?php endif; ?></p>
</div>
