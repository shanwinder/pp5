<div class="pp5-admin-page">
<?php if ($canView): ?><p><a class="btn btn-outline-secondary" href="/students">กลับรายการนักเรียน</a></p><?php endif; ?>
<?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<p>นักเรียนใหม่จะมีสถานะใช้งาน</p>
<form class="pp5-form pp5-surface" method="post" action="/students">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <fieldset><legend>ข้อมูลประจำตัวนักเรียน</legend>
    <div class="pp5-field"><label class="form-label" for="students-create-1">รหัสนักเรียน <input class="form-control" id="students-create-1" name="student_code" required maxlength="50" value=""></label></div>
    <div class="pp5-field"><label class="form-label" for="students-create-2">เลขประจำตัวประชาชน (ถ้ามี) <input class="form-control" id="students-create-2" name="national_id" maxlength="13" inputmode="numeric" pattern="[0-9]{13}" autocomplete="off" value=""></label></div>
    <div class="pp5-field"><label class="form-label" for="students-create-3">คำนำหน้า <input class="form-control" id="students-create-3" name="prefix_th" required maxlength="50" value=""></label></div>
    <div class="pp5-field"><label class="form-label" for="students-create-4">ชื่อ <input class="form-control" id="students-create-4" name="first_name_th" required maxlength="100" value=""></label></div>
    <div class="pp5-field"><label class="form-label" for="students-create-5">นามสกุล <input class="form-control" id="students-create-5" name="last_name_th" required maxlength="100" value=""></label></div>
    <div class="pp5-field"><label class="form-label" for="students-create-6">เพศ <select class="form-select" id="students-create-6" name="gender_code">
        <option value="">ไม่ระบุ</option>
        <option value="MALE">ชาย</option>
        <option value="FEMALE">หญิง</option>
        <option value="OTHER">อื่น ๆ</option>
    </select></label></div>
    <div class="pp5-field"><label class="form-label" for="students-create-7">วันเกิด (ถ้ามี) <input class="form-control" id="students-create-7" type="date" name="birth_date" value=""></label></div>
    </fieldset>
    <button class="btn btn-primary" type="submit">บันทึกข้อมูลนักเรียน</button>
    <?php if ($canView): ?><a class="btn btn-outline-secondary" href="/students">กลับรายการนักเรียน</a><?php endif; ?>
</form>
</div>
