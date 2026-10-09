<div class="pp5-admin-page pp5-admin-tabler pp5-admin-editor">
<?php if ($permissions['SCHOOL_USER_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/admin/users">ผู้ใช้งาน</a></li><li class="breadcrumb-item active" aria-current="page">รายละเอียด</li></ol></nav><?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($target !== null): ?>
    <p>ชื่อผู้ใช้: <?= htmlspecialchars($target['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <p>สถานะสมาชิก: <?= App\Support\View::render('ui/status', ['status'=>$target['status'], 'kind'=>'membership']) ?></p>
    <?php if (!$permissions['SCHOOL_USER_UPDATE']): ?>
    <dl><dt>ชื่อที่แสดง</dt><dd><?= htmlspecialchars($target['display_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd><dt>อีเมล</dt><dd><?= htmlspecialchars($target['email'] ?? 'ยังไม่ระบุ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></dl>
    <?php endif; ?>
    <?php if ($permissions['SCHOOL_USER_UPDATE']): ?>
    <form class="card card-body mb-3" method="post" action="/admin/users/<?= htmlspecialchars((string) $target['user_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/profile">
    <div class="card-status-top bg-blue" aria-hidden="true"></div>
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <fieldset>
        <legend class="h3 mb-3">ข้อมูลผู้ใช้</legend>
        <div class="mb-3"><label class="form-label" for="admin-users-edit-display_name">ชื่อที่แสดง <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="admin-users-edit-display_name" type="text" name="display_name" maxlength="190" required value="<?= htmlspecialchars($target['display_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </div>
        <div class="mb-3"><label class="form-label" for="admin-users-edit-email">อีเมล (ไม่บังคับ)</label><input class="form-control" id="admin-users-edit-email" type="email" name="email" maxlength="190" value="<?= htmlspecialchars($target['email'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </div>
        <button class="btn btn-primary align-self-start" type="submit">บันทึกข้อมูลผู้ใช้</button>
      </fieldset>
    </form>
    <?php endif; ?>
    <?php if ($permissions['SCHOOL_MEMBERSHIP_STATUS_MANAGE']): ?>
    <form class="card card-body mb-3 border-danger" method="post" action="/admin/users/<?= htmlspecialchars((string) $target['user_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/membership-status" data-confirm="ยืนยันการเปลี่ยนสถานะสมาชิกหรือไม่? การระงับจะหยุดการเข้าถึงโรงเรียนนี้">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <fieldset>
        <legend class="h3 mb-3">สถานะสมาชิกโรงเรียน</legend>
        <p>การระงับสมาชิกจะหยุดการเข้าถึงโรงเรียนนี้ของผู้ใช้</p>
        <div class="mb-3"><label class="form-label" for="admin-users-edit-status">สถานะใหม่ <span class="text-secondary small">(จำเป็น)</span></label><select class="form-select" id="admin-users-edit-status" name="status" required>
            <?php foreach (['ACTIVE', 'SUSPENDED'] as $value): ?>
              <option value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $target['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars(App\Support\StatusLabel::text($value, 'membership'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-danger align-self-start" type="submit">เปลี่ยนสถานะสมาชิก</button>
      </fieldset>
    </form>
    <?php endif; ?>
    <?php if (!$permissions['SCHOOL_ROLE_MANAGE']): ?>
    <section class="card card-body mb-3" aria-labelledby="roles-heading"><h2 id="roles-heading">บทบาท/สิทธิ์ที่กำหนด</h2><p><?= htmlspecialchars(implode(', ', $roleCodes), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p></section>
    <?php endif; ?>
    <?php if ($permissions['SCHOOL_ROLE_MANAGE']): ?>
    <form class="card card-body mb-3" method="post" action="/admin/users/<?= htmlspecialchars((string) $target['user_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/roles" data-confirm="ยืนยันการบันทึกบทบาทหรือไม่? บทบาทที่เลือกจะแทนที่บทบาทเดิมและมีผลต่อสิทธิ์ของผู้ใช้">
    <div class="card-status-top bg-blue" aria-hidden="true"></div>
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <fieldset>
        <legend class="h3 mb-3">บทบาท/สิทธิ์ที่กำหนด</legend>
        <p>เลือกอย่างน้อยหนึ่งบทบาท บทบาทที่เลือกจะแทนที่บทบาทเดิม และมีผลต่อสิทธิ์การใช้งานโรงเรียน</p>
        <?php foreach ($roles as $role): ?>
          <div class="form-check mb-2"><input class="form-check-input" id="admin-users-edit-role_codes-<?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?>" type="checkbox" name="role_codes[]" value="<?= htmlspecialchars($role['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= in_array($role['code'], $roleCodes, true) ? ' checked' : '' ?>>
          <label class="form-check-label" for="admin-users-edit-role_codes-<?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($role['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= htmlspecialchars($role['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)</label></div>
        <?php endforeach; ?>
        <button class="btn btn-primary align-self-start" type="submit">บันทึกบทบาท</button>
      </fieldset>
    </form>
    <?php endif; ?>
    <?php if ($permissions['SCHOOL_PASSWORD_RESET']): ?>
    <form class="card card-body mb-3 border-danger" method="post" action="/admin/users/<?= htmlspecialchars((string) $target['user_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/reset-password" data-confirm="ยืนยันการตั้งรหัสผ่านใหม่หรือไม่? รหัสผ่านเดิมจะใช้เข้าสู่ระบบไม่ได้">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <fieldset>
        <legend class="h3 mb-3">รีเซ็ตรหัสผ่าน</legend>
        <p>เมื่อบันทึก รหัสผ่านเดิมจะใช้เข้าสู่ระบบไม่ได้</p>
        <div class="mb-3"><label class="form-label" for="admin-users-edit-password">รหัสผ่านใหม่ (อย่างน้อย 12 ตัวอักษร) <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="admin-users-edit-password" type="password" name="password" minlength="12" autocomplete="new-password" required>
        </div>
        <button class="btn btn-danger align-self-start" type="submit">ตั้งรหัสผ่านใหม่</button>
      </fieldset>
    </form>
    <?php endif; ?>
  <?php endif; ?>
<p class="d-flex flex-wrap gap-2 mt-3"><?php if ($permissions['SCHOOL_USER_VIEW']): ?><a class="btn btn-outline-secondary" href="/admin/users">กลับรายการ</a><?php endif; ?></p>
</div>
