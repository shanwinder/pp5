<div class="pp5-admin-page">
<?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><nav aria-label="เส้นทางหน้า"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/academic/subjects">รายวิชา</a></li><li class="breadcrumb-item active" aria-current="page">รายละเอียด</li></ol></nav><?php endif; ?>
<?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<?php if ($target !== null): ?>
    <p>สถานะรายวิชา: <?= App\Support\View::render('ui/status', ['status'=>$target['status'], 'kind'=>'entity']) ?></p>
    <form class="pp5-form pp5-surface" method="post" action="/academic/subjects/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <div class="pp5-field"><label class="form-label" for="academic-subjects-edit-code">รหัสรายวิชา <input class="form-control" id="academic-subjects-edit-code" type="text" name="code" required maxlength="50" value="<?= htmlspecialchars($target['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
        <div class="pp5-field"><label class="form-label" for="academic-subjects-edit-name_th">ชื่อรายวิชา <input class="form-control" id="academic-subjects-edit-name_th" type="text" name="name_th" required maxlength="190" value="<?= htmlspecialchars($target['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
        <button class="btn btn-primary" type="submit">บันทึกการแก้ไข</button>
    </form>
    <form class="pp5-form pp5-surface pp5-sensitive" method="post" action="/academic/subjects/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/status">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="status" value="<?= $target['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
        <button class="btn btn-danger" type="submit"><?= $target['status'] === 'ACTIVE' ? 'ระงับใช้งานรายวิชา' : 'เปิดใช้งานรายวิชา' ?></button>
    </form>
<?php endif; ?>
<p class="pp5-actions"><?php if ($permissions['ACADEMIC_SETUP_VIEW']): ?><a class="btn btn-outline-secondary" href="/academic/subjects">กลับรายการ</a><?php endif; ?></p>
</div>
