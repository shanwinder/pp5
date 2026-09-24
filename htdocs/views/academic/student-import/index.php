<div class="pp5-admin-page">
<?= App\Support\View::render('academic/student-import/steps', ['step'=>1]) ?>
<p>เลือกไฟล์ canonical CSV แบบ UTF-8 ขนาดไม่เกิน 2 MiB และข้อมูลไม่เกิน 1,000 แถว ตรวจสอบตัวอย่างก่อนยืนยันนำเข้า</p>
<p>หัวตารางตามลำดับ:</p>
<pre class="pp5-csv-header">student_code,national_id,prefix_th,first_name_th,last_name_th,gender_code,birth_date,grade_level_code,classroom_code,entry_date</pre>
<p>รองรับไฟล์ canonical CSV เท่านั้น ไม่รองรับไฟล์ Excel หรือไฟล์ DMC โดยตรง ตัวอย่างเป็นข้อมูลชั่วคราว โปรดตรวจสอบวันหมดอายุในหน้าตัวอย่าง</p>
<h2>อัปโหลดไฟล์นักเรียน</h2>
<form class="pp5-form pp5-surface" method="post" action="/academic/student-import/preview" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="pp5-field"><label class="form-label" for="academic-student-import-index-1">ปีการศึกษา <select class="form-select" id="academic-student-import-index-1" name="academic_year_id" required><option value="">เลือกปีการศึกษา</option>
    <?php foreach ($years as $year): ?>
        <option value="<?= htmlspecialchars((string)$year['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string)$year['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
    <?php endforeach; ?>
    </select></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-student-import-index-2">ไฟล์ CSV <input class="form-control" id="academic-student-import-index-2" type="file" name="student_file" accept=".csv" required></label></div>
    <button class="btn btn-primary" type="submit">ตรวจสอบตัวอย่าง</button>
</form>
</div>
