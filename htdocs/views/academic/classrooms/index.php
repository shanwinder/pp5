<div class="pp5-admin-page pp5-admin-tabler">
<?php if ($permissions['CLASSROOM_MANAGE']): ?><div class="d-flex justify-content-end mb-3"><a class="btn btn-primary" href="/academic/classrooms/create">เพิ่มห้องเรียน</a></div><?php endif; ?>
<form class="d-flex flex-wrap align-items-end gap-2 mb-3 pp5-admin-filter" method="get" action="/academic/classrooms">
    <div class="mb-3"><label class="form-label" for="classrooms-year">ปีการศึกษา (พ.ศ.)</label>
        <select class="form-select" id="classrooms-year" name="academic_year_id" required>
            <option value="" disabled<?= $selectedYear === null ? ' selected' : '' ?>>เลือกปีการศึกษา</option>
            <?php foreach ($years as $year): ?>
                <option value="<?= (int) $year['id'] ?>"<?= ($selectedYear['id'] ?? null) === $year['id'] ? ' selected' : '' ?>><?= (int) $year['year_be'] ?> — <?= htmlspecialchars(App\Support\StatusLabel::text($year['status'], 'academic-year'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-outline-secondary" type="submit">แสดงปีที่เลือก</button>
    <a class="btn btn-link" href="/academic/classrooms">แสดงทุกปี</a>
</form>
<div class="card pp5-table-scroll" role="region" aria-label="ห้องเรียน" tabindex="0">
<div class="card-status-top bg-cyan" aria-hidden="true"></div>
<table class="table table-vcenter table-hover mb-0 pp5-table">
    <thead><tr><th scope="col">ปีการศึกษา (พ.ศ.)</th><th scope="col">ระดับชั้น</th><th scope="col">รหัสห้องเรียน</th><th scope="col">ชื่อห้องเรียน</th><th scope="col">สถานะ</th><th scope="col" class="pp5-admin-actions">รายละเอียด</th></tr></thead>
    <tbody>
    <?php foreach ($classrooms as $classroom): ?>
        <tr>
            <th scope="row"><?= htmlspecialchars((string) $classroom['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
            <td><?= htmlspecialchars($classroom['grade_level_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($classroom['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($classroom['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= App\Support\View::render('ui/status', ['status'=>$classroom['status'], 'kind'=>'entity']) ?></td>
            <td><?php if ($permissions['CLASSROOM_MANAGE']): ?><a class="btn btn-sm btn-icon btn-outline-primary pp5-row-action" href="/academic/classrooms/<?= htmlspecialchars((string) $classroom['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/edit" aria-label="ดูรายละเอียดห้องเรียน <?= htmlspecialchars((string) ($classroom['code'] . ' ' . $classroom['name_th']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="ดูรายละเอียดห้องเรียน <?= htmlspecialchars((string) ($classroom['code'] . ' ' . $classroom['name_th']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-tooltip="ดูรายละเอียดห้องเรียน <?= htmlspecialchars((string) ($classroom['code'] . ' ' . $classroom['name_th']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= App\Support\View::render('ui/icon', ['name'=>'dots-vertical']) ?></a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($classrooms === []): ?><p class="text-secondary p-3 mb-0">ยังไม่มีห้องเรียน</p><?php endif; ?>
</div>
