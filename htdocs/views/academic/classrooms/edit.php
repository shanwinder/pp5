<div class="pp5-admin-page">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/classrooms">ห้องเรียน</a></li><li class="breadcrumb-item active" aria-current="page">รายละเอียด</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
    <p>ปีการศึกษา (พ.ศ.): <?= htmlspecialchars((string) $target['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= App\Support\View::render('ui/status', ['status'=>$target['academic_year_status'], 'kind'=>'academic-year']) ?>)</p>
    <p>สถานะห้องเรียน: <?= App\Support\View::render('ui/status', ['status'=>$target['status'], 'kind'=>'entity']) ?></p>
    <?php if (in_array($target['academic_year_status'], ['DRAFT', 'ACTIVE'], true)): ?>
        <form class="pp5-form pp5-surface" method="post" action="/academic/classrooms/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <div class="pp5-field"><label class="form-label" for="academic-classrooms-edit-grade_level_id">ระดับชั้น
                <select class="form-select" id="academic-classrooms-edit-grade_level_id" name="grade_level_id" required>
                    <option value="">เลือกระดับชั้น</option>
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?= htmlspecialchars((string) $grade['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $target['grade_level_id'] === $grade['id'] ? ' selected' : '' ?>><?= htmlspecialchars($grade['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label></div>
            <div class="pp5-field"><label class="form-label" for="academic-classrooms-edit-code">รหัสห้องเรียน <input class="form-control" id="academic-classrooms-edit-code" type="text" name="code" required maxlength="50" value="<?= htmlspecialchars($target['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
            <div class="pp5-field"><label class="form-label" for="academic-classrooms-edit-name_th">ชื่อห้องเรียน <input class="form-control" id="academic-classrooms-edit-name_th" type="text" name="name_th" required maxlength="120" value="<?= htmlspecialchars($target['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
            <button class="btn btn-primary" type="submit">บันทึกการแก้ไข</button>
        </form>
        <form class="pp5-form pp5-surface pp5-sensitive" method="post" action="/academic/classrooms/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/status">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <input type="hidden" name="status" value="<?= $target['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
            <button class="btn btn-danger" type="submit"><?= $target['status'] === 'ACTIVE' ? 'ระงับใช้งานห้องเรียน' : 'เปิดใช้งานห้องเรียน' ?></button>
        </form>
    <?php else: ?>
        <p>ปีการศึกษาปิดแล้ว ดูรายละเอียดได้อย่างเดียว</p>
        <dl>
            <dt>ระดับชั้น</dt><dd><?= htmlspecialchars($target['grade_level_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
            <dt>รหัสห้องเรียน</dt><dd><?= htmlspecialchars($target['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
            <dt>ชื่อห้องเรียน</dt><dd><?= htmlspecialchars($target['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
        </dl>
    <?php endif; ?>
<?php endif; ?>
<p class="pp5-actions"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/classrooms">กลับรายการ</a><?php endif; ?></p>
</div>
