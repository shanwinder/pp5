<?php if ($canManageYears): ?><p><a href="/academic/years/create">เพิ่มปีการศึกษา</a></p><?php endif; ?>
<p>ปีการศึกษาระบุเป็น พ.ศ. ส่วนวันที่ระบุเป็น ค.ศ.</p>
<div class="pp5-table-scroll" role="region" aria-label="ปีการศึกษา" tabindex="0">
<table>
    <thead><tr><th>ปีการศึกษา (พ.ศ.)</th><th>วันเริ่มต้น (ค.ศ.)</th><th>วันสิ้นสุด (ค.ศ.)</th><th>สถานะ</th><th>รายละเอียด</th></tr></thead>
    <tbody>
    <?php foreach ($years as $year): ?>
        <tr>
            <td><?= htmlspecialchars((string) $year['year_be'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($year['start_date'] ?? 'ยังไม่ระบุ', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($year['end_date'] ?? 'ยังไม่ระบุ', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($year['status'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?php if ($canManageYears): ?><a href="/academic/years/<?= htmlspecialchars((string) $year['id'], ENT_QUOTES, 'UTF-8') ?>/edit">ดูรายละเอียด</a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($years === []): ?><p>ยังไม่มีปีการศึกษา</p><?php endif; ?>
