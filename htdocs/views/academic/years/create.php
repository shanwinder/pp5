<div class="pp5-admin-page pp5-admin-tabler pp5-admin-editor">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/years">ปีการศึกษา</a></li><li class="breadcrumb-item active" aria-current="page">เพิ่มข้อมูล</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<form class="card card-body mb-3" method="post" action="/academic/years">
    <div class="card-status-top bg-yellow" aria-hidden="true"></div>
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="mb-3"><label class="form-label" for="academic-years-create-year_be">ปีการศึกษา (พ.ศ.) <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="academic-years-create-year_be" type="number" name="year_be" required min="2400" max="2700" value="<?= htmlspecialchars($values['year_be'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
    <div class="form-hint mb-3" id="year-date-hint">วันที่ใช้ ค.ศ. (YYYY-MM-DD): ปีเริ่มต้น = พ.ศ. − 543; วันสิ้นสุดอยู่ปีเดียวกันหรือปีถัดไป เว้นว่างได้ในร่าง แต่ต้องครบก่อนเปิดใช้งาน</div>
    <div class="mb-3"><label class="form-label" for="academic-years-create-start_date">วันเริ่มต้น (ค.ศ.) <span class="text-secondary small">(ไม่บังคับในร่าง)</span></label><input class="form-control" id="academic-years-create-start_date" type="date" aria-describedby="year-date-hint" name="start_date" value="<?= htmlspecialchars($values['start_date'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
    <div class="mb-3"><label class="form-label" for="academic-years-create-end_date">วันสิ้นสุด (ค.ศ.) <span class="text-secondary small">(ไม่บังคับในร่าง)</span></label><input class="form-control" id="academic-years-create-end_date" type="date" aria-describedby="year-date-hint" name="end_date" value="<?= htmlspecialchars($values['end_date'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
    <button class="btn btn-primary align-self-start" type="submit">บันทึกปีการศึกษา</button>
</form>
<p class="d-flex flex-wrap gap-2 mt-3"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/years">กลับรายการ</a><?php endif; ?></p>
</div>
