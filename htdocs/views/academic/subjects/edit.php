<div class="pp5-admin-page pp5-admin-tabler pp5-admin-editor">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/subjects">รายวิชาโรงเรียน</a></li><li class="breadcrumb-item active" aria-current="page">รายละเอียด</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
    <p>สถานะรายวิชา: <?= App\Support\View::render('ui/status', ['status'=>$target['status'], 'kind'=>'entity']) ?></p>
    <form class="card card-body mb-3" method="post" action="/academic/subjects/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="card-status-top bg-purple" aria-hidden="true"></div>
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <div class="mb-3"><label class="form-label" for="academic-subjects-edit-code">รหัสรายวิชา <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="academic-subjects-edit-code" type="text" name="code" required maxlength="50" value="<?= htmlspecialchars($target['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
        <div class="mb-3"><label class="form-label" for="academic-subjects-edit-name_th">ชื่อรายวิชา <span class="text-secondary small">(จำเป็น)</span></label><input class="form-control" id="academic-subjects-edit-name_th" type="text" name="name_th" required maxlength="190" value="<?= htmlspecialchars($target['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>
        <button class="btn btn-primary align-self-start" type="submit">บันทึกการแก้ไข</button>
    </form>
    <form class="card card-body mb-3 border-danger" method="post" action="/academic/subjects/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/status" data-confirm="ยืนยันการเปลี่ยนสถานะรายวิชาหรือไม่? วิชาที่ระงับจะไม่พร้อมสำหรับการเปิดสอนใหม่">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <p class="text-secondary">วิชาที่ระงับจะไม่พร้อมสำหรับการเปิดสอนใหม่</p>
        <input type="hidden" name="status" value="<?= $target['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
        <button class="btn btn-danger align-self-start" type="submit"><?= $target['status'] === 'ACTIVE' ? 'ระงับใช้งานรายวิชา' : 'เปิดใช้งานรายวิชา' ?></button>
    </form>
<?php endif; ?>
<p class="d-flex flex-wrap gap-2 mt-3"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/subjects">กลับรายการ</a><?php endif; ?></p>
</div>
