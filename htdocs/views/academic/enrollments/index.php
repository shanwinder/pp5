<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<div class="pp5-admin-page">
<?php if ($canManage): ?><p><a class="btn btn-primary" href="/academic/enrollments/create">เพิ่มการลงทะเบียน</a></p><?php endif; ?>
<form class="pp5-filter-bar" method="get" action="/academic/enrollments">
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-index-1">ปีการศึกษา <select class="form-select" id="academic-enrollments-index-1" name="academic_year_id"><option value="">เลือกปีการศึกษา</option>
        <?php foreach ($years as $year): ?><option value="<?= $escape($year['id']) ?>"<?= $yearId === $year['id'] ? ' selected' : '' ?>><?= $escape($year['year_be']) ?></option><?php endforeach; ?>
    </select></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-index-2">ระดับชั้น <select class="form-select" id="academic-enrollments-index-2" name="grade_level_id"><option value="">ทุกระดับชั้น</option>
        <?php foreach ($grades as $grade): ?><option value="<?= $escape($grade['id']) ?>"<?= $gradeId === $grade['id'] ? ' selected' : '' ?>><?= $escape($grade['name_th']) ?></option><?php endforeach; ?>
    </select></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-index-3">ห้องเรียน <select class="form-select" id="academic-enrollments-index-3" name="classroom_id"><option value="">ทุกห้อง / ยังไม่จัดห้อง</option>
        <?php foreach ($classrooms as $room): ?><option value="<?= $escape($room['id']) ?>"<?= $classroomId === $room['id'] ? ' selected' : '' ?>><?= $escape($room['name_th']) ?></option><?php endforeach; ?>
    </select></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-index-4">สถานะ <select class="form-select" id="academic-enrollments-index-4" name="status"><option value="">ทุกสถานะ</option>
        <?php foreach (['ACTIVE','TRANSFERRED_OUT','WITHDRAWN'] as $state): ?><option value="<?= $escape($state) ?>"<?= $status === $state ? ' selected' : '' ?>><?= $escape(App\Support\StatusLabel::text($state, 'enrollment')) ?></option><?php endforeach; ?>
    </select></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-index-5">ค้นหารหัสหรือชื่อนักเรียน <input class="form-control" id="academic-enrollments-index-5" type="search" name="q" maxlength="100" value="<?= $escape($search) ?>"></label></div>
    <button class="btn btn-primary" type="submit">ค้นหา</button>
<a class="btn btn-outline-secondary" href="/academic/enrollments">ล้างตัวกรอง</a>
</form>
<?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= $escape($error) ?></p><?php endif; ?>
<div class="pp5-table-scroll" role="region" aria-label="การลงทะเบียน" tabindex="0"><table class="table pp5-table">
<thead><tr><th scope="col">รหัสนักเรียน</th><th scope="col">ชื่อ–นามสกุล</th><th scope="col">ปีการศึกษา</th><th scope="col">ระดับชั้น</th><th scope="col">ห้องปัจจุบัน</th><th scope="col">สถานะ</th><th scope="col">วันที่เข้าเรียน / สิ้นสุด</th><th scope="col">รายละเอียด</th></tr></thead>
<tbody>
<?php foreach ($enrollments as $row): ?>
<tr>
    <th scope="row"><?= $escape($row['student_code']) ?></th>
    <td><?= $escape(implode(' ',[$row['prefix_th'],$row['first_name_th'],$row['last_name_th']])) ?></td>
    <td><?= $escape($row['year_be']) ?></td><td><?= $escape($row['grade_level_name']) ?></td>
    <td><?= $escape($row['classroom_name'] ?? 'ยังไม่จัดห้อง') ?></td><td><?= App\Support\View::render('ui/status', ['status'=>$row['status'], 'kind'=>'enrollment']) ?></td>
    <td><?= $escape($row['entry_date'] ?? 'ไม่ระบุ') ?> / <?= $escape($row['exit_date'] ?? 'ยังไม่สิ้นสุด') ?></td>
    <td><a class="btn btn-outline-secondary" href="/students/<?= $escape($row['student_id']) ?>">ประวัตินักเรียน</a>
        <?php if ($canManage): ?><a class="btn btn-outline-secondary" href="/academic/enrollments/<?= $escape($row['id']) ?>/edit">รายละเอียดการลงทะเบียน</a><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($enrollments === [] && $error === null): ?><p class="pp5-empty-state">ไม่พบการลงทะเบียน ลองเปลี่ยนตัวกรองหรือเพิ่มการลงทะเบียนเมื่อมีสิทธิ์</p><?php endif; ?>
</div>
