<div class="pp5-admin-page pp5-admin-tabler pp5-admin-editor">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/offerings">การเปิดรายวิชา</a></li><li class="breadcrumb-item active" aria-current="page">รายละเอียด</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
    <dl>
        <dt>ปีการศึกษา (พ.ศ.)</dt><dd><?= htmlspecialchars((string) $target['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= App\Support\View::render('ui/status', ['status'=>$target['academic_year_status'], 'kind'=>'academic-year']) ?>)</dd>
        <dt>ห้องเรียนปัจจุบัน</dt><dd><?= htmlspecialchars($target['classroom_code'] . ' — ' . $target['classroom_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= App\Support\View::render('ui/status', ['status'=>$target['classroom_status'], 'kind'=>'entity']) ?>)</dd>
        <dt>รายวิชาปัจจุบัน</dt><dd><?= htmlspecialchars($target['subject_code'] . ' — ' . $target['subject_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= App\Support\View::render('ui/status', ['status'=>$target['subject_status'], 'kind'=>'entity']) ?>)</dd>
        <dt>ภาคเรียน</dt><dd><?= htmlspecialchars((string) $target['term_no'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
        <dt>สถานะการเปิดรายวิชา</dt><dd><?= App\Support\View::render('ui/status', ['status'=>$target['status'], 'kind'=>'entity']) ?></dd>
    </dl>
    <?php if (in_array($target['academic_year_status'], ['DRAFT', 'ACTIVE'], true)): ?>
        <form class="card card-body mb-3" method="post" action="/academic/offerings/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="card-status-top bg-purple" aria-hidden="true"></div>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <div class="mb-3"><label class="form-label" for="academic-offerings-edit-classroom_id">ห้องเรียน <span class="text-secondary small">(จำเป็น)</span></label><select class="form-select" id="academic-offerings-edit-classroom_id" name="classroom_id" required>
                    <option value="">เลือกห้องเรียน</option>
                    <?php foreach ($classrooms as $room): ?>
                        <option value="<?= htmlspecialchars((string) $room['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $target['classroom_id'] === $room['id'] ? ' selected' : '' ?>><?= htmlspecialchars($room['code'] . ' — ' . $room['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label" for="academic-offerings-edit-subject_id">รายวิชา <span class="text-secondary small">(จำเป็น)</span></label><select class="form-select" id="academic-offerings-edit-subject_id" name="subject_id" required>
                    <option value="">เลือกรายวิชา</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= htmlspecialchars((string) $subject['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $target['subject_id'] === $subject['id'] ? ' selected' : '' ?>><?= htmlspecialchars($subject['code'] . ' — ' . $subject['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label" for="academic-offerings-edit-term_no">ภาคเรียน <span class="text-secondary small">(จำเป็น)</span></label><select class="form-select" id="academic-offerings-edit-term_no" name="term_no" required>
                    <?php foreach ([1, 2] as $term): ?>
                        <option value="<?= htmlspecialchars((string) $term, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $target['term_no'] === $term ? ' selected' : '' ?>><?= htmlspecialchars((string) $term, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary align-self-start" type="submit">บันทึกการแก้ไข</button>
        </form>
        <form class="card card-body mb-3 border-danger" method="post" action="/academic/offerings/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/status" data-confirm="ยืนยันการเปลี่ยนสถานะการเปิดรายวิชาหรือไม่? การระงับมีผลต่อการมอบหมายและการบันทึกคะแนน">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <p class="text-secondary">การระงับมีผลต่อการมอบหมายและการบันทึกคะแนน</p>
        <input type="hidden" name="status" value="<?= $target['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
            <button class="btn btn-danger align-self-start" type="submit"><?= $target['status'] === 'ACTIVE' ? 'ระงับการเปิดรายวิชา' : 'เปิดใช้งานรายวิชาอีกครั้ง' ?></button>
        </form>
    <?php else: ?>
        <p>ปีการศึกษาปิดแล้ว ดูรายละเอียดได้อย่างเดียว</p>
    <?php endif; ?>
<?php endif; ?>
<p class="d-flex flex-wrap gap-2 mt-3"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/offerings">กลับรายการ</a><?php endif; ?></p>
</div>
