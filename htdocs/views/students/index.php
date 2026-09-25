<div class="pp5-admin-page">
<?php if ($canManage): ?><p><a class="btn btn-primary" href="/students/create">เพิ่มนักเรียน</a></p><?php endif; ?>
<form class="pp5-filter-bar" method="get" action="/students">
    <div class="pp5-field"><label class="form-label" for="students-index-1">ค้นหารหัสหรือชื่อนักเรียน <input class="form-control" id="students-index-1" type="search" name="q" maxlength="100" value="<?= htmlspecialchars($search, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label></div>
    <button class="btn btn-primary" type="submit">ค้นหา</button>
<a class="btn btn-outline-secondary" href="/students">ล้างคำค้น</a>
</form>
<?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
<div class="pp5-table-scroll" role="region" aria-label="รายการนักเรียน" tabindex="0"><table class="table pp5-table">
    <thead><tr><th scope="col">รหัสนักเรียน</th><th scope="col">ชื่อ–นามสกุล</th><th scope="col">สถานะ</th><th scope="col">จัดการ</th></tr></thead>
    <tbody>
    <?php foreach ($students as $student): ?>
        <tr>
            <th scope="row"><?= htmlspecialchars($student['student_code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
            <td><?= htmlspecialchars(implode(' ', [$student['prefix_th'], $student['first_name_th'], $student['last_name_th']]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
            <td><?= App\Support\View::render('ui/status', ['status'=>$student['status'], 'kind'=>'student']) ?></td>
            <td>
                <a class="btn btn-outline-secondary" href="/students/<?= htmlspecialchars((string) $student['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">ดูรายละเอียด</a>
                <?php if ($canManage): ?><a class="btn btn-outline-secondary" href="/students/<?= htmlspecialchars((string) $student['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/edit">แก้ไข</a><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php if ($students === [] && $error === null): ?><p class="pp5-empty-state">ไม่พบนักเรียน ลองเปลี่ยนคำค้นหรือเพิ่มนักเรียนใหม่เมื่อมีสิทธิ์</p><?php endif; ?>
</div>
