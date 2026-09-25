<div class="pp5-admin-page">
<?php if ($canView): ?><p><a class="btn btn-outline-secondary" href="/students">กลับรายการนักเรียน</a></p><?php endif; ?>
<?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
<p>สถานะ: <?= App\Support\View::render('ui/status', ['status'=>$target['status'], 'kind'=>'student']) ?></p>
<form class="pp5-form pp5-surface" method="post" action="/students/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <fieldset><legend>ข้อมูลประจำตัวนักเรียน</legend>
    <div class="pp5-field"><label class="form-label" for="students-edit-1">รหัสนักเรียน <input class="form-control" id="students-edit-1" name="student_code" required maxlength="50" value="<?= htmlspecialchars($target['student_code'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <div class="pp5-field"><label class="form-label" for="students-edit-2">เลขประจำตัวประชาชน (ถ้ามี) <input class="form-control" id="students-edit-2" name="national_id" maxlength="13" inputmode="numeric" pattern="[0-9]{13}" autocomplete="off" value="<?= htmlspecialchars($target['national_id'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <div class="pp5-field"><label class="form-label" for="students-edit-3">คำนำหน้า <input class="form-control" id="students-edit-3" name="prefix_th" required maxlength="50" value="<?= htmlspecialchars($target['prefix_th'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <div class="pp5-field"><label class="form-label" for="students-edit-4">ชื่อ <input class="form-control" id="students-edit-4" name="first_name_th" required maxlength="100" value="<?= htmlspecialchars($target['first_name_th'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <div class="pp5-field"><label class="form-label" for="students-edit-5">นามสกุล <input class="form-control" id="students-edit-5" name="last_name_th" required maxlength="100" value="<?= htmlspecialchars($target['last_name_th'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <div class="pp5-field"><label class="form-label" for="students-edit-6">เพศ <select class="form-select" id="students-edit-6" name="gender_code">
        <option value=""<?= ($target['gender_code'] ?? '') === '' ? ' selected' : '' ?>>ไม่ระบุ</option>
        <option value="MALE"<?= ($target['gender_code'] ?? '') === 'MALE' ? ' selected' : '' ?>>ชาย</option>
        <option value="FEMALE"<?= ($target['gender_code'] ?? '') === 'FEMALE' ? ' selected' : '' ?>>หญิง</option>
        <option value="OTHER"<?= ($target['gender_code'] ?? '') === 'OTHER' ? ' selected' : '' ?>>อื่น ๆ</option>
    </select></label></div>
    <div class="pp5-field"><label class="form-label" for="students-edit-7">วันเกิด (ถ้ามี) <input class="form-control" id="students-edit-7" type="date" name="birth_date" value="<?= htmlspecialchars($target['birth_date'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    </fieldset>
    <button class="btn btn-primary" type="submit">บันทึกข้อมูลนักเรียน</button>
    <?php if ($canView): ?><a class="btn btn-outline-secondary" href="/students">กลับรายการนักเรียน</a><?php endif; ?>
</form>
<section class="pp5-surface pp5-sensitive"><h2>สถานะนักเรียน</h2>
<p>นักเรียนที่ปิดใช้งานยังดูประวัติได้ แต่ไม่สามารถลงทะเบียนใหม่ได้</p>
<form class="pp5-form" method="post" action="/students/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/status"<?php if ($target['status'] === 'ACTIVE'): ?> data-confirm="ยืนยันการปิดใช้งานนักเรียนหรือไม่? นักเรียนจะไม่สามารถลงทะเบียนใหม่ได้ แต่ประวัติเดิมยังคงอยู่"<?php endif; ?>>
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="status" value="<?= $target['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
    <button class="btn <?= $target['status'] === 'ACTIVE' ? 'btn-danger' : 'btn-primary' ?>" type="submit"><?= $target['status'] === 'ACTIVE' ? 'ปิดใช้งานนักเรียน' : 'เปิดใช้งานนักเรียน' ?></button>
</form>
</section>
<?php endif; ?>
</div>
