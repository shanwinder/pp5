<div class="pp5-admin-page">
<?php if ($permissions['SUBJECT_MANAGE']): ?><p><a class="btn btn-primary" href="/academic/subjects/create">เพิ่มรายวิชา</a></p><?php endif; ?>
<div class="pp5-table-scroll" role="region" aria-label="รายวิชา" tabindex="0">
<table class="table pp5-table">
    <thead><tr><th scope="col">รหัสรายวิชา</th><th scope="col">ชื่อรายวิชา</th><th scope="col">สถานะ</th><th scope="col">รายละเอียด</th></tr></thead>
    <tbody>
    <?php foreach ($subjects as $subject): ?>
        <tr>
            <th scope="row"><?= htmlspecialchars($subject['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
            <td><?= htmlspecialchars($subject['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= App\Support\View::render('ui/status', ['status'=>$subject['status'], 'kind'=>'entity']) ?></td>
            <td><?php if ($permissions['SUBJECT_MANAGE']): ?><a href="/academic/subjects/<?= htmlspecialchars((string) $subject['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/edit">ดูรายละเอียด</a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($subjects === []): ?><p class="pp5-empty-state">ยังไม่มีรายวิชา</p><?php endif; ?>
</div>
