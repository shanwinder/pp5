<div class="pp5-admin-page">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/years">ปีการศึกษา</a></li><li class="breadcrumb-item active" aria-current="page">รายละเอียด</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
    <p>สถานะ: <?= App\Support\View::render('ui/status', ['status'=>$target['status'], 'kind'=>'academic-year']) ?></p>
    <p>ปีการศึกษาระบุเป็น พ.ศ. ส่วนวันที่ระบุเป็น ค.ศ. ในรูปแบบ YYYY-MM-DD</p>
    <?php if ($target['status'] === 'DRAFT'): ?>
        <p>ปี ค.ศ. ของวันเริ่มต้นต้องเท่ากับปี พ.ศ. ลบ 543 และวันสิ้นสุดอยู่ในปีเดียวกันหรือปีถัดไป</p>
        <form class="pp5-form pp5-surface" method="post" action="/academic/years/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <div class="pp5-field"><label class="form-label" for="academic-years-edit-year_be">ปีการศึกษา (พ.ศ.) <input class="form-control" id="academic-years-edit-year_be" type="number" name="year_be" required min="2400" max="2700" value="<?= htmlspecialchars((string) $target['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
            <div class="pp5-field"><label class="form-label" for="academic-years-edit-start_date">วันเริ่มต้น (ค.ศ.) <input class="form-control" id="academic-years-edit-start_date" type="date" name="start_date" value="<?= htmlspecialchars($target['start_date'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
            <div class="pp5-field"><label class="form-label" for="academic-years-edit-end_date">วันสิ้นสุด (ค.ศ.) <input class="form-control" id="academic-years-edit-end_date" type="date" name="end_date" value="<?= htmlspecialchars($target['end_date'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
            <button class="btn btn-primary" type="submit">บันทึกการแก้ไข</button>
        </form>
    <?php else: ?>
        <dl>
            <dt>ปีการศึกษา (พ.ศ.)</dt><dd><?= htmlspecialchars((string) $target['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
            <dt>วันเริ่มต้น (ค.ศ.)</dt><dd><?= htmlspecialchars($target['start_date'] ?? 'ยังไม่ระบุ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
            <dt>วันสิ้นสุด (ค.ศ.)</dt><dd><?= htmlspecialchars($target['end_date'] ?? 'ยังไม่ระบุ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
        </dl>
    <?php endif; ?>
    <?php if ($target['status'] === 'DRAFT' || $target['status'] === 'ACTIVE'): ?>
        <?php if ($target['status'] === 'DRAFT'): ?><p>กรุณาบันทึกวันเริ่มต้นและวันสิ้นสุดให้ครบก่อนเปิดใช้งาน</p><?php endif; ?>
        <form class="pp5-form pp5-surface pp5-sensitive" method="post" action="/academic/years/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/status">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <input type="hidden" name="status" value="<?= $target['status'] === 'DRAFT' ? 'ACTIVE' : 'CLOSED' ?>">
            <button class="btn btn-danger" type="submit"><?= $target['status'] === 'DRAFT' ? 'เปิดใช้งานปีการศึกษา' : 'ปิดปีการศึกษา' ?></button>
        </form>
    <?php endif; ?>
<?php endif; ?>
<p class="pp5-actions"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/years">กลับรายการ</a><?php endif; ?></p>
</div>
