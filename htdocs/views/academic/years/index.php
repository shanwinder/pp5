<div class="pp5-admin-page">
<?php if ($canManageYears): ?><p><a class="btn btn-primary" href="/academic/years/create">เพิ่มปีการศึกษา</a></p><?php endif; ?>
<p>ปีการศึกษาระบุเป็น พ.ศ. ส่วนวันที่ระบุเป็น ค.ศ.</p>
<div class="pp5-table-scroll" role="region" aria-label="ปีการศึกษา" tabindex="0">
<table class="table pp5-table">
    <thead><tr><th scope="col">ปีการศึกษา (พ.ศ.)</th><th scope="col">วันเริ่มต้น (ค.ศ.)</th><th scope="col">วันสิ้นสุด (ค.ศ.)</th><th scope="col">สถานะ</th><th scope="col">รายละเอียด</th></tr></thead>
    <tbody>
    <?php foreach ($years as $year): ?>
        <tr>
            <th scope="row"><?= htmlspecialchars((string) $year['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
            <td><?= htmlspecialchars($year['start_date'] ?? 'ยังไม่ระบุ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($year['end_date'] ?? 'ยังไม่ระบุ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= App\Support\View::render('ui/status', ['status'=>$year['status'], 'kind'=>'academic-year']) ?></td>
            <td><?php if ($canManageYears): ?><a href="/academic/years/<?= htmlspecialchars((string) $year['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/edit">ดูรายละเอียด</a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($years === []): ?><p class="pp5-empty-state">ยังไม่มีปีการศึกษา</p><?php endif; ?>
</div>
