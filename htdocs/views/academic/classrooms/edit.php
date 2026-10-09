<div class="pp5-admin-page pp5-admin-tabler pp5-admin-editor">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/classrooms">ห้องเรียน</a></li><li class="breadcrumb-item active" aria-current="page">รายละเอียด</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
    <p>ปีการศึกษา (พ.ศ.): <?= htmlspecialchars((string) $target['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= App\Support\View::render('ui/status', ['status'=>$target['academic_year_status'], 'kind'=>'academic-year']) ?>)</p>
    <p>สถานะห้องเรียน: <?= App\Support\View::render('ui/status', ['status'=>$target['status'], 'kind'=>'entity']) ?></p>
    <?php if (in_array($target['academic_year_status'], ['DRAFT', 'ACTIVE'], true)): ?>
        <form class="card card-body mb-3" method="post" action="/academic/classrooms/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="card-status-top bg-cyan" aria-hidden="true"></div>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <div class="mb-3"><label class="form-label" for="academic-classrooms-edit-grade_level_id">ระดับชั้น <span class="text-secondary small">(จำเป็น)</span></label><select class="form-select" id="academic-classrooms-edit-grade_level_id" name="grade_level_id" required>
                    <option value="">เลือกระดับชั้น</option>
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?= htmlspecialchars((string) $grade['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $target['grade_level_id'] === $grade['id'] ? ' selected' : '' ?>><?= htmlspecialchars($grade['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label" for="academic-classrooms-edit-code">รหัสห้องเรียน <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="academic-classrooms-edit-code" type="text" name="code" required maxlength="50" value="<?= htmlspecialchars($target['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
            <div class="mb-3"><label class="form-label" for="academic-classrooms-edit-name_th">ชื่อห้องเรียน <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="academic-classrooms-edit-name_th" type="text" name="name_th" required maxlength="120" value="<?= htmlspecialchars($target['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
            <button class="btn btn-primary align-self-start" type="submit">บันทึกการแก้ไข</button>
        </form>
        <form class="card card-body mb-3 border-danger" method="post" action="/academic/classrooms/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/status" data-confirm="ยืนยันการเปลี่ยนสถานะห้องเรียนหรือไม่? ห้องที่ระงับจะไม่พร้อมสำหรับงานใหม่">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <p class="text-secondary">ห้องที่ระงับจะไม่พร้อมสำหรับงานใหม่</p>
        <input type="hidden" name="status" value="<?= $target['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
            <button class="btn btn-danger align-self-start" type="submit"><?= $target['status'] === 'ACTIVE' ? 'ระงับใช้งานห้องเรียน' : 'เปิดใช้งานห้องเรียน' ?></button>
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
<p class="d-flex flex-wrap gap-2 mt-3"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/classrooms">กลับรายการ</a><?php endif; ?></p>
</div>
