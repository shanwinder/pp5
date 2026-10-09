<div class="pp5-admin-page pp5-admin-tabler pp5-admin-editor">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/years">ปีการศึกษา</a></li><li class="breadcrumb-item active" aria-current="page">รายละเอียด</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
    <p>สถานะ: <?= App\Support\View::render('ui/status', ['status'=>$target['status'], 'kind'=>'academic-year']) ?></p>
    <?php if ($target['status'] === 'DRAFT'): ?>
        <form class="card card-body mb-3" method="post" action="/academic/years/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="card-status-top bg-yellow" aria-hidden="true"></div>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <div class="mb-3"><label class="form-label" for="academic-years-edit-year_be">ปีการศึกษา (พ.ศ.) <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="academic-years-edit-year_be" type="number" name="year_be" required min="2400" max="2700" value="<?= htmlspecialchars((string) $target['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
            <div class="form-hint mb-3" id="year-date-hint">วันที่ใช้ ค.ศ. (YYYY-MM-DD): ปีเริ่มต้น = พ.ศ. − 543; วันสิ้นสุดอยู่ปีเดียวกันหรือปีถัดไป เว้นว่างได้ในร่าง แต่ต้องครบก่อนเปิดใช้งาน</div>
            <div class="mb-3"><label class="form-label" for="academic-years-edit-start_date">วันเริ่มต้น (ค.ศ.) <span class="text-secondary small">(ไม่บังคับในร่าง)</span></label><input class="form-control" id="academic-years-edit-start_date" type="date" aria-describedby="year-date-hint" name="start_date" value="<?= htmlspecialchars($target['start_date'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
            <div class="mb-3"><label class="form-label" for="academic-years-edit-end_date">วันสิ้นสุด (ค.ศ.) <span class="text-secondary small">(ไม่บังคับในร่าง)</span></label><input class="form-control" id="academic-years-edit-end_date" type="date" aria-describedby="year-date-hint" name="end_date" value="<?= htmlspecialchars($target['end_date'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
            <button class="btn btn-primary align-self-start" type="submit">บันทึกการแก้ไข</button>
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
        <form class="card card-body mb-3 border-danger" method="post" action="/academic/years/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/status" data-confirm="ยืนยันการเปลี่ยนสถานะปีการศึกษาหรือไม่? เมื่อปิดปีแล้วจะไม่สามารถแก้ไขข้อมูลของปีนี้หรือเปิดกลับได้">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <p class="text-secondary">ปีร่างเปิดใช้งานได้เมื่อระบุวันที่ครบ การปิดปีจะหยุดการแก้ไขข้อมูลของปีนี้ และเปิดกลับไม่ได้</p>
            <input type="hidden" name="status" value="<?= $target['status'] === 'DRAFT' ? 'ACTIVE' : 'CLOSED' ?>">
            <button class="btn btn-danger align-self-start" type="submit"><?= $target['status'] === 'DRAFT' ? 'เปิดใช้งานปีการศึกษา' : 'ปิดปีการศึกษา' ?></button>
        </form>
    <?php endif; ?>
<?php endif; ?>
<p class="d-flex flex-wrap gap-2 mt-3"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/years">กลับรายการ</a><?php endif; ?></p>
</div>
