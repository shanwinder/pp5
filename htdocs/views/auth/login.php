<p class="text-muted">ระบบจัดการข้อมูลและสมุดคะแนนของโรงเรียน</p>
<?php if (is_string($error) && $error !== ''): ?>
  <div class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
<?php endif; ?>
<form method="post" action="/login" class="pp5-form">
  <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <div class="pp5-field">
    <label class="form-label" for="username">ชื่อผู้ใช้</label>
    <input class="form-control" id="username" type="text" name="username" autocomplete="username" required>
  </div>
  <div class="pp5-field">
    <label class="form-label" for="password">รหัสผ่าน</label>
    <input class="form-control" id="password" type="password" name="password" autocomplete="current-password" required>
  </div>
  <button class="btn btn-primary w-100" type="submit">เข้าสู่ระบบ</button>
</form>
