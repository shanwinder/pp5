<div class="pp5-admin-page">
<?php if ($permissions['SCHOOL_USER_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/admin/users">ผู้ใช้งาน</a></li><li class="breadcrumb-item active" aria-current="page">รายละเอียด</li></ol></nav><?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($target !== null): ?>
    <p>ชื่อผู้ใช้: <?= htmlspecialchars($target['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <p>สถานะสมาชิก: <?= App\Support\View::render('ui/status', ['status'=>$target['status'], 'kind'=>'membership']) ?></p>
    <?php if (!$permissions['SCHOOL_USER_UPDATE']): ?>
    <dl><dt>ชื่อที่แสดง</dt><dd><?= htmlspecialchars($target['display_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd><dt>อีเมล</dt><dd><?= htmlspecialchars($target['email'] ?? 'ยังไม่ระบุ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></dl>
    <?php endif; ?>
    <?php if ($permissions['SCHOOL_USER_UPDATE']): ?>
    <form class="pp5-form pp5-surface" method="post" action="/admin/users/<?= htmlspecialchars((string) $target['user_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/profile">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <fieldset>
        <legend>ข้อมูลผู้ใช้</legend>
        <div class="pp5-field"><label class="form-label" for="admin-users-edit-display_name">ชื่อที่แสดง
          <input class="form-control" id="admin-users-edit-display_name" type="text" name="display_name" maxlength="190" required value="<?= htmlspecialchars($target['display_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </label></div>
        <div class="pp5-field"><label class="form-label" for="admin-users-edit-email">อีเมล (ไม่บังคับ)
          <input class="form-control" id="admin-users-edit-email" type="email" name="email" maxlength="190" value="<?= htmlspecialchars($target['email'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </label></div>
        <button class="btn btn-primary" type="submit">บันทึกข้อมูลผู้ใช้</button>
      </fieldset>
    </form>
    <?php endif; ?>
    <?php if ($permissions['SCHOOL_MEMBERSHIP_STATUS_MANAGE']): ?>
    <form class="pp5-form pp5-surface pp5-sensitive" method="post" action="/admin/users/<?= htmlspecialchars((string) $target['user_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/membership-status">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <fieldset>
        <legend>สถานะสมาชิกโรงเรียน</legend>
        <p>การระงับสมาชิกจะหยุดการเข้าถึงโรงเรียนนี้ของผู้ใช้</p>
        <div class="pp5-field"><label class="form-label" for="admin-users-edit-status">สถานะใหม่
          <select class="form-select" id="admin-users-edit-status" name="status">
            <?php foreach (['ACTIVE', 'SUSPENDED'] as $value): ?>
              <option value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $target['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars(App\Support\StatusLabel::text($value, 'membership'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </label></div>
        <button class="btn btn-danger" type="submit">เปลี่ยนสถานะสมาชิก</button>
      </fieldset>
    </form>
    <?php endif; ?>
    <?php if (!$permissions['SCHOOL_ROLE_MANAGE']): ?>
    <section class="pp5-surface" aria-labelledby="roles-heading"><h2 id="roles-heading">บทบาท/สิทธิ์ที่กำหนด</h2><p><?= htmlspecialchars(implode(', ', $roleCodes), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p></section>
    <?php endif; ?>
    <?php if ($permissions['SCHOOL_ROLE_MANAGE']): ?>
    <form class="pp5-form pp5-surface" method="post" action="/admin/users/<?= htmlspecialchars((string) $target['user_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/roles">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <fieldset>
        <legend>บทบาท/สิทธิ์ที่กำหนด</legend>
        <p>เลือกอย่างน้อยหนึ่งบทบาท การเปลี่ยนบทบาทมีผลต่อสิทธิ์การใช้งานโรงเรียน</p>
        <?php foreach ($roles as $role): ?>
          <div class="pp5-field"><label class="form-label" for="admin-users-edit-role_codes-<?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?>">
            <input class="form-check-input" id="admin-users-edit-role_codes-<?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?>" type="checkbox" name="role_codes[]" value="<?= htmlspecialchars($role['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= in_array($role['code'], $roleCodes, true) ? ' checked' : '' ?>>
            <?= htmlspecialchars($role['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= htmlspecialchars($role['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)
          </label></div>
        <?php endforeach; ?>
        <button class="btn btn-primary" type="submit">บันทึกบทบาท</button>
      </fieldset>
    </form>
    <?php endif; ?>
    <?php if ($permissions['SCHOOL_PASSWORD_RESET']): ?>
    <form class="pp5-form pp5-surface pp5-sensitive" method="post" action="/admin/users/<?= htmlspecialchars((string) $target['user_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/reset-password">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <fieldset>
        <legend>รีเซ็ตรหัสผ่าน</legend>
        <p>เมื่อบันทึก รหัสผ่านเดิมจะใช้เข้าสู่ระบบไม่ได้</p>
        <div class="pp5-field"><label class="form-label" for="admin-users-edit-password">รหัสผ่านใหม่ (อย่างน้อย 12 ตัวอักษร)
          <input class="form-control" id="admin-users-edit-password" type="password" name="password" minlength="12" autocomplete="new-password" required>
        </label></div>
        <button class="btn btn-danger" type="submit">ตั้งรหัสผ่านใหม่</button>
      </fieldset>
    </form>
    <?php endif; ?>
  <?php endif; ?>
<p class="pp5-actions"><?php if ($permissions['SCHOOL_USER_VIEW']): ?><a class="btn btn-outline-secondary" href="/admin/users">กลับรายการ</a><?php endif; ?></p>
</div>
