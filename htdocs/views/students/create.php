<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>เพิ่มนักเรียน — PP5</title></head>
<body>
<h1>เพิ่มนักเรียน</h1>
<?php if ($canView): ?><p><a href="/students">กลับรายการนักเรียน</a></p><?php endif; ?>
<?php if ($error !== null): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<p>นักเรียนใหม่จะมีสถานะ ACTIVE</p>
<form method="post" action="/students">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <p><label>รหัสนักเรียน <input name="student_code" required maxlength="50" value=""></label></p>
    <p><label>เลขประจำตัวประชาชน (ถ้ามี) <input name="national_id" maxlength="13" inputmode="numeric" pattern="[0-9]{13}" autocomplete="off" value=""></label></p>
    <p><label>คำนำหน้า <input name="prefix_th" required maxlength="50" value=""></label></p>
    <p><label>ชื่อ <input name="first_name_th" required maxlength="100" value=""></label></p>
    <p><label>นามสกุล <input name="last_name_th" required maxlength="100" value=""></label></p>
    <p><label>เพศ <select name="gender_code">
        <option value="">ไม่ระบุ</option>
        <option value="MALE">ชาย</option>
        <option value="FEMALE">หญิง</option>
        <option value="OTHER">อื่น ๆ</option>
    </select></label></p>
    <p><label>วันเกิด (ถ้ามี) <input type="date" name="birth_date" value=""></label></p>
    <button type="submit">บันทึกข้อมูลนักเรียน</button>
</form>
</body>
</html>
