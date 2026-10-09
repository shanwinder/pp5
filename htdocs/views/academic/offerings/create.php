<div class="pp5-admin-page pp5-admin-tabler pp5-admin-editor">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/offerings">การเปิดรายวิชา</a></li><li class="breadcrumb-item active" aria-current="page">เพิ่มข้อมูล</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<div class="form-hint mb-2">เลือกปีร่างหรือกำลังใช้งาน เพื่อแสดงห้องเรียนที่เปิดใช้งาน</div>
<?php if (($workspace ?? null) !== null): ?><p>เลือกปีการศึกษาและห้องเรียนจากงานชั้นเรียนแล้ว ตรวจสอบรายวิชาและภาคเรียนก่อนบันทึก</p><?php endif; ?>
<?php if (($workspace ?? null) === null): ?>
<form class="d-flex flex-wrap align-items-end gap-2 mb-3 pp5-admin-filter" method="get" action="/academic/offerings/create">
    <div class="mb-3"><label class="form-label" for="academic-offerings-create-academic_year_id">ปีการศึกษา (พ.ศ.) <span class="text-secondary small">(จำเป็น)</span></label><select class="form-select" id="academic-offerings-create-academic_year_id" name="academic_year_id" required>
            <option value="">เลือกปีการศึกษา</option>
            <?php foreach ($years as $year): ?>
                <option value="<?= htmlspecialchars((string) $year['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= ($selectedYear['id'] ?? null) === $year['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $year['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= htmlspecialchars(App\Support\StatusLabel::text($year['status'], 'academic-year'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-outline-primary" type="submit">เลือกปีการศึกษา</button>
</form>
<?php endif; ?>
<?php if ($selectedYear !== null): ?>
    <p>ปีการศึกษา (พ.ศ.): <?= htmlspecialchars((string) $selectedYear['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> เมื่อบันทึกแล้วจะเปลี่ยนปีการศึกษาไม่ได้</p>

    <form class="card card-body mb-3" method="post" action="/academic/offerings<?= ($workspace ?? null) === null ? '' : '?workspace_classroom_id=' . (int) $workspace['classroom']['id'] ?>">
    <div class="card-status-top bg-purple" aria-hidden="true"></div>
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="academic_year_id" value="<?= htmlspecialchars((string) $selectedYear['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <div class="mb-3"><label class="form-label" for="academic-offerings-create-classroom_id">ห้องเรียน <span class="text-secondary small">(จำเป็น)</span></label><select class="form-select" id="academic-offerings-create-classroom_id" name="classroom_id" required>
                <option value="">เลือกห้องเรียน</option>
                <?php foreach ($classrooms as $room): ?>
                    <option value="<?= htmlspecialchars((string) $room['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= ($values['classroom_id'] ?? null) === $room['id'] ? ' selected' : '' ?>><?= htmlspecialchars($room['code'] . ' — ' . $room['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label" for="academic-offerings-create-subject_id">รายวิชา <span class="text-secondary small">(จำเป็น)</span></label><select class="form-select" id="academic-offerings-create-subject_id" name="subject_id" required>
                <option value="">เลือกรายวิชา</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= htmlspecialchars((string) $subject['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= ($values['subject_id'] ?? null) === $subject['id'] ? ' selected' : '' ?>><?= htmlspecialchars($subject['code'] . ' — ' . $subject['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label" for="academic-offerings-create-term_no">ภาคเรียน <span class="text-secondary small">(จำเป็น)</span></label><select class="form-select" id="academic-offerings-create-term_no" name="term_no" required>
                <option value="">เลือกภาคเรียน</option>
                <?php foreach ([1, 2] as $term): ?>
                    <option value="<?= htmlspecialchars((string) $term, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= ($values['term_no'] ?? null) === $term ? ' selected' : '' ?>><?= htmlspecialchars((string) $term, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary align-self-start" type="submit">บันทึกการเปิดรายวิชา</button>
    </form>
<?php endif; ?>
<p class="d-flex flex-wrap gap-2 mt-3"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/offerings">กลับรายการ</a><?php endif; ?></p>
</div>
