<div class="pp5-admin-page pp5-admin-tabler">
<?php if ($permissions['SUBJECT_OFFERING_MANAGE']): ?><div class="d-flex justify-content-end mb-3"><a class="btn btn-primary" href="/academic/offerings/create">เพิ่มการเปิดรายวิชา</a></div><?php endif; ?>
<?php if (($workspace ?? null) !== null): ?>
<p class="text-muted">หน้านี้แสดงทุกห้องในปีการศึกษา <?= (int) $workspace['academicYear']['year_be'] ?> <a href="/academic/offerings">เลือกปีนอกงานชั้นเรียน</a></p>
<?php else: ?>
<form class="d-flex flex-wrap align-items-end gap-2 mb-3 pp5-admin-filter" method="get" action="/academic/offerings">
    <div class="mb-3"><label class="form-label" for="offerings-year">ปีการศึกษา (พ.ศ.)</label>
        <select class="form-select" id="offerings-year" name="academic_year_id" required>
            <option value="" disabled<?= $selectedYear === null ? ' selected' : '' ?>>เลือกปีการศึกษา</option>
            <?php foreach ($years as $year): ?>
                <option value="<?= (int) $year['id'] ?>"<?= ($selectedYear['id'] ?? null) === $year['id'] ? ' selected' : '' ?>><?= (int) $year['year_be'] ?> — <?= htmlspecialchars(App\Support\StatusLabel::text($year['status'], 'academic-year'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-outline-secondary" type="submit">แสดงปีที่เลือก</button>
    <a class="btn btn-link" href="/academic/offerings">แสดงทุกปี</a>
</form>
<?php endif; ?>
<div class="card pp5-table-scroll" role="region" aria-label="การเปิดรายวิชา" tabindex="0">
<div class="card-status-top bg-purple" aria-hidden="true"></div>
<table class="table table-vcenter table-hover mb-0 pp5-table">
    <thead><tr><th scope="col">ปีการศึกษา (พ.ศ.)</th><th scope="col">ห้องเรียน</th><th scope="col">รายวิชา</th><th scope="col">ภาคเรียน</th><th scope="col">สถานะ</th><th scope="col" class="pp5-admin-actions">รายละเอียด</th></tr></thead>
    <tbody>
    <?php foreach ($offerings as $offering): ?>
        <tr>
            <th scope="row"><?= htmlspecialchars((string) $offering['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
            <td><?= htmlspecialchars($offering['classroom_code'] . ' — ' . $offering['classroom_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($offering['subject_code'] . ' — ' . $offering['subject_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $offering['term_no'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= App\Support\View::render('ui/status', ['status'=>$offering['status'], 'kind'=>'entity']) ?></td>
            <td><?php if ($permissions['SUBJECT_OFFERING_MANAGE']): ?><a class="btn btn-sm btn-icon btn-outline-primary pp5-row-action" href="/academic/offerings/<?= htmlspecialchars((string) $offering['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/edit" aria-label="ดูรายละเอียดการเปิดรายวิชา <?= htmlspecialchars((string) ($offering['subject_code'] . ' ' . $offering['subject_name']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="ดูรายละเอียดการเปิดรายวิชา <?= htmlspecialchars((string) ($offering['subject_code'] . ' ' . $offering['subject_name']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-tooltip="ดูรายละเอียดการเปิดรายวิชา <?= htmlspecialchars((string) ($offering['subject_code'] . ' ' . $offering['subject_name']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= App\Support\View::render('ui/icon', ['name'=>'dots-vertical']) ?></a><?php endif; ?>
                <?php if ($canManageGradebookComponents): ?>
                    <a class="btn btn-sm btn-icon btn-outline-secondary pp5-row-action" href="/gradebook/<?= htmlspecialchars((string) $offering['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/setup" aria-label="ตั้งค่าโครงสร้างคะแนน <?= htmlspecialchars($offering['subject_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="ตั้งค่าโครงสร้างคะแนน" data-tooltip="ตั้งค่าโครงสร้างคะแนน"><?= App\Support\View::render('ui/icon', ['name'=>'notebook']) ?></a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($offerings === []): ?><p class="text-secondary p-3 mb-0">ยังไม่มีการเปิดรายวิชา</p><?php endif; ?>
</div>
