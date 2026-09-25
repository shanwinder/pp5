<div class="pp5-admin-page">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/classrooms">ห้องเรียน</a></li><li class="breadcrumb-item active" aria-current="page">เพิ่มข้อมูล</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<p>เพิ่มห้องเรียนได้ในปีการศึกษาสถานะร่างหรือกำลังใช้งาน ห้องเรียนใหม่จะมีสถานะใช้งาน</p>
<p>เมื่อบันทึกแล้วจะเปลี่ยนปีการศึกษาของห้องเรียนไม่ได้</p>
<form class="pp5-form pp5-surface" method="post" action="/academic/classrooms">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="pp5-field"><label class="form-label" for="academic-classrooms-create-academic_year_id">ปีการศึกษา (พ.ศ.)
        <select class="form-select" id="academic-classrooms-create-academic_year_id" name="academic_year_id" required>
            <option value="">เลือกปีการศึกษา</option>
            <?php foreach ($years as $year): ?>
                <option value="<?= htmlspecialchars((string) $year['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= ($values['academic_year_id'] ?? null) === $year['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $year['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= htmlspecialchars(App\Support\StatusLabel::text($year['status'], 'academic-year'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)</option>
            <?php endforeach; ?>
        </select>
    </label></div>
    <div class="pp5-field"><label class="form-label" for="academic-classrooms-create-grade_level_id">ระดับชั้น
        <select class="form-select" id="academic-classrooms-create-grade_level_id" name="grade_level_id" required>
            <option value="">เลือกระดับชั้น</option>
            <?php foreach ($grades as $grade): ?>
                <option value="<?= htmlspecialchars((string) $grade['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= ($values['grade_level_id'] ?? null) === $grade['id'] ? ' selected' : '' ?>><?= htmlspecialchars($grade['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </label></div>
    <div class="pp5-field"><label class="form-label" for="academic-classrooms-create-code">รหัสห้องเรียน <input class="form-control" id="academic-classrooms-create-code" type="text" name="code" required maxlength="50" value="<?= htmlspecialchars($values['code'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-classrooms-create-name_th">ชื่อห้องเรียน <input class="form-control" id="academic-classrooms-create-name_th" type="text" name="name_th" required maxlength="120" value="<?= htmlspecialchars($values['name_th'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <button class="btn btn-primary" type="submit">บันทึกห้องเรียน</button>
</form>
<p class="pp5-actions"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/classrooms">กลับรายการ</a><?php endif; ?></p>
</div>
