<div class="pp5-admin-page pp5-admin-tabler">
<?php if ($permissions['SUBJECT_MANAGE']): ?><div class="d-flex justify-content-end mb-3"><a class="btn btn-primary" href="/academic/subjects/create">เพิ่มรายวิชา</a></div><?php endif; ?>
<div class="card pp5-table-scroll" role="region" aria-label="รายวิชาโรงเรียน" tabindex="0">
<div class="card-status-top bg-purple" aria-hidden="true"></div>
<table class="table table-vcenter table-hover mb-0 pp5-table">
    <thead><tr><th scope="col">รหัสรายวิชา</th><th scope="col">ชื่อรายวิชาโรงเรียน</th><th scope="col">สถานะ</th><th scope="col" class="pp5-admin-actions">รายละเอียด</th></tr></thead>
    <tbody>
    <?php foreach ($subjects as $subject): ?>
        <tr>
            <th scope="row"><?= htmlspecialchars($subject['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
            <td><?= htmlspecialchars($subject['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= App\Support\View::render('ui/status', ['status'=>$subject['status'], 'kind'=>'entity']) ?></td>
            <td><?php if ($permissions['SUBJECT_MANAGE']): ?><a class="btn btn-sm btn-icon btn-outline-primary pp5-row-action" href="/academic/subjects/<?= htmlspecialchars((string) $subject['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/edit" aria-label="ดูรายละเอียดรายวิชาโรงเรียน <?= htmlspecialchars((string) ($subject['code'] . ' ' . $subject['name_th']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="ดูรายละเอียดรายวิชาโรงเรียน <?= htmlspecialchars((string) ($subject['code'] . ' ' . $subject['name_th']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-tooltip="ดูรายละเอียดรายวิชาโรงเรียน <?= htmlspecialchars((string) ($subject['code'] . ' ' . $subject['name_th']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= App\Support\View::render('ui/icon', ['name'=>'dots-vertical']) ?></a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($subjects === []): ?><p class="text-secondary p-3 mb-0">ยังไม่มีรายวิชา</p><?php endif; ?>
</div>
