<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>แก้ไขข้อมูลนักเรียน — PP5</title></head>
<body>
<h1>แก้ไขข้อมูลนักเรียน</h1>
<?php if ($canView): ?><p><a href="/students">กลับรายการนักเรียน</a></p><?php endif; ?>
<?php if ($error !== null): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
<p>สถานะ: <?= htmlspecialchars($target['status'], ENT_QUOTES, 'UTF-8') ?></p>
<form method="post" action="/students/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <p><label>รหัสนักเรียน <input name="student_code" required maxlength="50" value="<?= htmlspecialchars($target['student_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <p><label>เลขประจำตัวประชาชน (ถ้ามี) <input name="national_id" maxlength="13" inputmode="numeric" pattern="[0-9]{13}" autocomplete="off" value="<?= htmlspecialchars($target['national_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <p><label>คำนำหน้า <input name="prefix_th" required maxlength="50" value="<?= htmlspecialchars($target['prefix_th'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <p><label>ชื่อ <input name="first_name_th" required maxlength="100" value="<?= htmlspecialchars($target['first_name_th'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <p><label>นามสกุล <input name="last_name_th" required maxlength="100" value="<?= htmlspecialchars($target['last_name_th'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <p><label>เพศ <select name="gender_code">
        <option value=""<?= ($target['gender_code'] ?? '') === '' ? ' selected' : '' ?>>ไม่ระบุ</option>
        <option value="MALE"<?= ($target['gender_code'] ?? '') === 'MALE' ? ' selected' : '' ?>>ชาย</option>
        <option value="FEMALE"<?= ($target['gender_code'] ?? '') === 'FEMALE' ? ' selected' : '' ?>>หญิง</option>
        <option value="OTHER"<?= ($target['gender_code'] ?? '') === 'OTHER' ? ' selected' : '' ?>>อื่น ๆ</option>
    </select></label></p>
    <p><label>วันเกิด (ถ้ามี) <input type="date" name="birth_date" value="<?= htmlspecialchars($target['birth_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label></p>
    <button type="submit">บันทึกข้อมูลนักเรียน</button>
</form>
<form method="post" action="/students/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES, 'UTF-8') ?>/status">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="status" value="<?= $target['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
    <button type="submit"><?= $target['status'] === 'ACTIVE' ? 'ปิดใช้งานนักเรียน' : 'เปิดใช้งานนักเรียน' ?></button>
</form>
<?php endif; ?>
</body>
</html>
