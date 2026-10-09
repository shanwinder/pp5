<div class="pp5-admin-page pp5-admin-tabler">
<?php if ($canManageYears): ?><div class="d-flex justify-content-end mb-3"><a class="btn btn-primary" href="/academic/years/create">เพิ่มปีการศึกษา</a></div><?php endif; ?>
<div class="card pp5-table-scroll" role="region" aria-label="ปีการศึกษา" tabindex="0">
<div class="card-status-top bg-yellow" aria-hidden="true"></div>
<table class="table table-vcenter table-hover mb-0 pp5-table">
    <thead><tr><th scope="col">ปีการศึกษา (พ.ศ.)</th><th scope="col">วันเริ่มต้น (ค.ศ.)</th><th scope="col">วันสิ้นสุด (ค.ศ.)</th><th scope="col">สถานะ</th><th scope="col" class="pp5-admin-actions">รายละเอียด</th></tr></thead>
    <tbody>
    <?php foreach ($years as $year): ?>
        <tr>
            <th scope="row"><?= htmlspecialchars((string) $year['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
            <td><?= htmlspecialchars($year['start_date'] ?? 'ยังไม่ระบุ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($year['end_date'] ?? 'ยังไม่ระบุ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= App\Support\View::render('ui/status', ['status'=>$year['status'], 'kind'=>'academic-year']) ?></td>
            <td><?php if ($canManageYears): ?><a class="btn btn-sm btn-icon btn-outline-primary pp5-row-action" href="/academic/years/<?= htmlspecialchars((string) $year['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/edit" aria-label="ดูรายละเอียดปีการศึกษา <?= htmlspecialchars((string) ($year['year_be']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="ดูรายละเอียดปีการศึกษา <?= htmlspecialchars((string) ($year['year_be']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-tooltip="ดูรายละเอียดปีการศึกษา <?= htmlspecialchars((string) ($year['year_be']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= App\Support\View::render('ui/icon', ['name'=>'dots-vertical']) ?></a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($years === []): ?><p class="text-secondary p-3 mb-0">ยังไม่มีปีการศึกษา</p><?php endif; ?>
</div>
