<div class="pp5-admin-page">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/years">ปีการศึกษา</a></li><li class="breadcrumb-item active" aria-current="page">เพิ่มข้อมูล</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<p>ปีการศึกษาระบุเป็น พ.ศ. ส่วนวันที่ระบุเป็น ค.ศ. ในรูปแบบ YYYY-MM-DD</p>
<p>ปีใหม่จะอยู่ในสถานะร่าง และเว้นวันที่ว่างได้ ก่อนเปิดใช้งานต้องระบุทั้งสองวันที่</p>
<p>ปี ค.ศ. ของวันเริ่มต้นต้องเท่ากับปี พ.ศ. ลบ 543 และวันสิ้นสุดอยู่ในปีเดียวกันหรือปีถัดไป</p>
<form class="pp5-form pp5-surface" method="post" action="/academic/years">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="pp5-field"><label class="form-label" for="academic-years-create-year_be">ปีการศึกษา (พ.ศ.) <input class="form-control" id="academic-years-create-year_be" type="number" name="year_be" required min="2400" max="2700" value="<?= htmlspecialchars($values['year_be'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-years-create-start_date">วันเริ่มต้น (ค.ศ.) <input class="form-control" id="academic-years-create-start_date" type="date" name="start_date" value="<?= htmlspecialchars($values['start_date'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-years-create-end_date">วันสิ้นสุด (ค.ศ.) <input class="form-control" id="academic-years-create-end_date" type="date" name="end_date" value="<?= htmlspecialchars($values['end_date'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <button class="btn btn-primary" type="submit">บันทึกปีการศึกษา</button>
</form>
<p class="pp5-actions"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/years">กลับรายการ</a><?php endif; ?></p>
</div>
