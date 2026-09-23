<div class="pp5-admin-page">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/subjects">รายวิชา</a></li><li class="breadcrumb-item active" aria-current="page">เพิ่มข้อมูล</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<p>รายวิชาใหม่จะมีสถานะใช้งาน รหัสรายวิชาต้องไม่ซ้ำภายในโรงเรียน</p>
<form class="pp5-form pp5-surface" method="post" action="/academic/subjects">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="pp5-field"><label class="form-label" for="academic-subjects-create-code">รหัสรายวิชา <input class="form-control" id="academic-subjects-create-code" type="text" name="code" required maxlength="50" value="<?= htmlspecialchars($values['code'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-subjects-create-name_th">ชื่อรายวิชา <input class="form-control" id="academic-subjects-create-name_th" type="text" name="name_th" required maxlength="190" value="<?= htmlspecialchars($values['name_th'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <button class="btn btn-primary" type="submit">บันทึกรายวิชา</button>
</form>
<p class="pp5-actions"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/subjects">กลับรายการ</a><?php endif; ?></p>
</div>
