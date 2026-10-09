<div class="pp5-admin-page pp5-admin-tabler pp5-admin-editor">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/classrooms">ห้องเรียน</a></li><li class="breadcrumb-item active" aria-current="page">เพิ่มข้อมูล</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<form class="card card-body mb-3" method="post" action="/academic/classrooms">
    <div class="card-status-top bg-cyan" aria-hidden="true"></div>
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="mb-3"><label class="form-label" for="academic-classrooms-create-academic_year_id">ปีการศึกษา (พ.ศ.) <span class="text-secondary small">(จำเป็น)</span></label><select class="form-select" id="academic-classrooms-create-academic_year_id" name="academic_year_id" required>
            <option value="">เลือกปีการศึกษา</option>
            <?php foreach ($years as $year): ?>
                <option value="<?= htmlspecialchars((string) $year['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= ($values['academic_year_id'] ?? null) === $year['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $year['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= htmlspecialchars(App\Support\StatusLabel::text($year['status'], 'academic-year'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)</option>
            <?php endforeach; ?>
        </select><div class="form-hint">เลือกปีร่างหรือกำลังใช้งาน เมื่อบันทึกแล้วเปลี่ยนปีไม่ได้</div>
    </div>
    <div class="mb-3"><label class="form-label" for="academic-classrooms-create-grade_level_id">ระดับชั้น <span class="text-secondary small">(จำเป็น)</span></label><select class="form-select" id="academic-classrooms-create-grade_level_id" name="grade_level_id" required>
            <option value="">เลือกระดับชั้น</option>
            <?php foreach ($grades as $grade): ?>
                <option value="<?= htmlspecialchars((string) $grade['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= ($values['grade_level_id'] ?? null) === $grade['id'] ? ' selected' : '' ?>><?= htmlspecialchars($grade['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3"><label class="form-label" for="academic-classrooms-create-code">รหัสห้องเรียน <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="academic-classrooms-create-code" type="text" name="code" required maxlength="50" value="<?= htmlspecialchars($values['code'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
    <div class="mb-3"><label class="form-label" for="academic-classrooms-create-name_th">ชื่อห้องเรียน <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="academic-classrooms-create-name_th" type="text" name="name_th" required maxlength="120" value="<?= htmlspecialchars($values['name_th'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
    <button class="btn btn-primary align-self-start" type="submit">บันทึกห้องเรียน</button>
</form>
<p class="d-flex flex-wrap gap-2 mt-3"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/classrooms">กลับรายการ</a><?php endif; ?></p>
</div>
