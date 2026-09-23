<div class="pp5-admin-page">
<?php if ($permissions['SUBJECT_OFFERING_MANAGE']): ?><p><a class="btn btn-primary" href="/academic/offerings/create">เพิ่มการเปิดรายวิชา</a></p><?php endif; ?>
<form class="pp5-filter-bar" method="get" action="/academic/offerings">
    <div class="pp5-field"><label class="form-label" for="offerings-year">ปีการศึกษา (พ.ศ.)</label>
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
<div class="pp5-table-scroll" role="region" aria-label="การเปิดรายวิชา" tabindex="0">
<table class="table pp5-table">
    <thead><tr><th scope="col">ปีการศึกษา (พ.ศ.)</th><th scope="col">ห้องเรียน</th><th scope="col">รายวิชา</th><th scope="col">ภาคเรียน</th><th scope="col">สถานะ</th><th scope="col">รายละเอียด</th></tr></thead>
    <tbody>
    <?php foreach ($offerings as $offering): ?>
        <tr>
            <th scope="row"><?= htmlspecialchars((string) $offering['year_be'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
            <td><?= htmlspecialchars($offering['classroom_code'] . ' — ' . $offering['classroom_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($offering['subject_code'] . ' — ' . $offering['subject_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $offering['term_no'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= App\Support\View::render('ui/status', ['status'=>$offering['status'], 'kind'=>'entity']) ?></td>
            <td><?php if ($permissions['SUBJECT_OFFERING_MANAGE']): ?><a href="/academic/offerings/<?= htmlspecialchars((string) $offering['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/edit">ดูรายละเอียด</a><?php endif; ?>
                <?php if ($canManageGradebookComponents): ?>
                    <a href="/gradebook/<?= htmlspecialchars((string) $offering['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/setup">ตั้งค่าโครงสร้างคะแนน</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($offerings === []): ?><p class="pp5-empty-state">ยังไม่มีการเปิดรายวิชา</p><?php endif; ?>
</div>
