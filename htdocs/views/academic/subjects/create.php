<div class="pp5-admin-page pp5-admin-tabler pp5-admin-editor">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/subjects">รายวิชาโรงเรียน</a></li><li class="breadcrumb-item active" aria-current="page">เพิ่มข้อมูล</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<form class="card card-body mb-3" method="post" action="/academic/subjects">
    <div class="card-status-top bg-purple" aria-hidden="true"></div>
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="mb-3"><label class="form-label" for="academic-subjects-create-code">รหัสรายวิชา <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="academic-subjects-create-code" type="text" aria-describedby="subject-code-hint" name="code" required maxlength="50" value="<?= htmlspecialchars($values['code'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><div class="form-hint" id="subject-code-hint">รหัสรายวิชาต้องไม่ซ้ำภายในโรงเรียน</div></div>
    <div class="mb-3"><label class="form-label" for="academic-subjects-create-name_th">ชื่อรายวิชา <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="academic-subjects-create-name_th" type="text" name="name_th" required maxlength="190" value="<?= htmlspecialchars($values['name_th'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
    <button class="btn btn-primary align-self-start" type="submit">บันทึกรายวิชา</button>
</form>
<p class="d-flex flex-wrap gap-2 mt-3"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/subjects">กลับรายการ</a><?php endif; ?></p>
</div>
