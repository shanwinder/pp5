<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>นำเข้านักเรียน — PP5</title></head>
<body>
<h1>นำเข้านักเรียน</h1>
<p><a href="/dashboard">กลับแดชบอร์ด</a></p>
<p>เลือกไฟล์ canonical CSV แบบ UTF-8 ขนาดไม่เกิน 2 MiB และข้อมูลไม่เกิน 1,000 แถว ตรวจสอบตัวอย่างก่อนยืนยันนำเข้า</p>
<p>หัวตารางตามลำดับ:</p>
<pre>student_code,national_id,prefix_th,first_name_th,last_name_th,gender_code,birth_date,grade_level_code,classroom_code,entry_date</pre>
<p>รองรับไฟล์ canonical CSV เท่านั้น ไม่รองรับไฟล์ Excel หรือไฟล์ DMC โดยตรง ตัวอย่างจะหมดอายุใน 24 ชั่วโมง</p>
<form method="post" action="/academic/student-import/preview" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <label>ปีการศึกษา <select name="academic_year_id" required><option value="">เลือกปีการศึกษา</option>
    <?php foreach ($years as $year): ?>
        <option value="<?= htmlspecialchars((string)$year['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$year['year_be'], ENT_QUOTES, 'UTF-8') ?></option>
    <?php endforeach; ?>
    </select></label>
    <label>ไฟล์ CSV <input type="file" name="student_file" accept=".csv" required></label>
    <button type="submit">ตรวจสอบตัวอย่าง</button>
</form>
</body></html>
