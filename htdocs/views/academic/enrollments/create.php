<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<div class="pp5-admin-page">
<?php if ($canView): ?><p><a class="btn btn-outline-secondary" href="/academic/enrollments">กลับรายการลงทะเบียน</a></p><?php endif; ?>
<h2>ปีการศึกษาและระดับชั้น</h2>
<p>เลือกปีการศึกษาและระดับชั้นเพื่อดูห้องเรียนที่เลือกได้</p>
<form class="pp5-filter-bar" method="get" action="/academic/enrollments/create">
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-create-1">ปีการศึกษา <select class="form-select" id="academic-enrollments-create-1" name="academic_year_id" required><option value="">เลือกปีการศึกษา</option>
        <?php foreach ($years as $option): ?><option value="<?= $escape($option['id']) ?>"<?= ($year['id'] ?? null) === $option['id'] ? ' selected' : '' ?>><?= $escape($option['year_be']) ?></option><?php endforeach; ?>
    </select></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-create-2">ระดับชั้น <select class="form-select" id="academic-enrollments-create-2" name="grade_level_id" required><option value="">เลือกระดับชั้น</option>
        <?php foreach ($grades as $option): ?><option value="<?= $escape($option['id']) ?>"<?= ($grade['id'] ?? null) === $option['id'] ? ' selected' : '' ?>><?= $escape($option['name_th']) ?></option><?php endforeach; ?>
    </select></label></div>
    <button class="btn btn-primary" type="submit">เลือกปีและระดับชั้น</button>
</form>
<?php if ($year !== null && $grade !== null): ?>
<h2>นักเรียนและห้องเรียนเริ่มต้น</h2>
<p>ปีการศึกษา <?= $escape($year['year_be']) ?> — <?= $escape($grade['name_th']) ?></p>
<form class="pp5-form pp5-surface" method="post" action="/academic/enrollments">
    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
    <input type="hidden" name="academic_year_id" value="<?= $escape($year['id']) ?>">
    <input type="hidden" name="grade_level_id" value="<?= $escape($grade['id']) ?>">
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-create-3">นักเรียน <select class="form-select" id="academic-enrollments-create-3" name="student_id" required><option value="">เลือกนักเรียน</option>
        <?php foreach ($students as $student): ?><option value="<?= $escape($student['id']) ?>"><?= $escape($student['student_code'].' '.implode(' ',[$student['prefix_th'],$student['first_name_th'],$student['last_name_th']])) ?></option><?php endforeach; ?>
    </select></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-create-4">ห้องเรียนเริ่มต้น <select class="form-select" id="academic-enrollments-create-4" name="classroom_id"><option value="">ยังไม่จัดห้อง</option>
        <?php foreach ($classrooms as $room): ?><option value="<?= $escape($room['id']) ?>"><?= $escape($room['name_th']) ?></option><?php endforeach; ?>
    </select></label></div>
    <div class="pp5-field"><label class="form-label" for="academic-enrollments-create-5">วันที่เข้าเรียน (ถ้ามี) <input class="form-control" id="academic-enrollments-create-5" type="date" name="entry_date"></label></div>
    <button class="btn btn-primary" type="submit">บันทึกการลงทะเบียน</button>
    <?php if ($canView): ?><a class="btn btn-outline-secondary" href="/academic/enrollments">กลับรายการลงทะเบียน</a><?php endif; ?>
</form>
<?php endif; ?>
</div>
