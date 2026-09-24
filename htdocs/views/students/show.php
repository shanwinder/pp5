<div class="pp5-admin-page">
<p><a class="btn btn-outline-secondary" href="/students">กลับรายการนักเรียน</a></p>
<h2>ข้อมูลนักเรียน</h2>
<dl class="pp5-surface">
    <dt>รหัสนักเรียน</dt><dd><?= htmlspecialchars($target['student_code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
    <dt>ชื่อ–นามสกุล</dt><dd><?= htmlspecialchars(implode(' ', [$target['prefix_th'], $target['first_name_th'], $target['last_name_th']]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
    <dt>เลขประจำตัวประชาชน</dt><dd><?= htmlspecialchars($maskedNationalId ?? 'ไม่ระบุ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
    <dt>เพศ</dt><dd><?= htmlspecialchars($target['gender_code'] ?? 'ไม่ระบุ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
    <dt>วันเกิด</dt><dd><?= htmlspecialchars($target['birth_date'] ?? 'ไม่ระบุ', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
    <dt>สถานะ</dt><dd><?= App\Support\View::render('ui/status', ['status'=>$target['status'], 'kind'=>'student']) ?></dd>
</dl>
<?php if ($canManage): ?><p><a class="btn btn-outline-secondary" href="/students/<?= htmlspecialchars((string) $target['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/edit">แก้ไขข้อมูลนักเรียน</a></p><?php endif; ?>
<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<h2>ประวัติการลงทะเบียน</h2>
<?php if ($history === []): ?><p class="pp5-empty-state">ยังไม่มีประวัติการลงทะเบียน</p><?php endif; ?>
<?php foreach ($history as $enrollment): ?>
<section class="enrollment-history pp5-surface">
<h3>ปีการศึกษา <?= $escape($enrollment['year_be']) ?></h3>
<p>ระดับชั้น: <?= $escape($enrollment['grade_level_name']) ?> — สถานะ: <?= App\Support\View::render('ui/status', ['status'=>$enrollment['status'], 'kind'=>'enrollment']) ?></p>
<p>วันที่เริ่ม: <?= $escape($enrollment['entry_date'] ?? 'ไม่ระบุ') ?> — วันที่สิ้นสุด: <?= $escape($enrollment['exit_date'] ?? 'ไม่ระบุ') ?></p>
<p>ห้องปัจจุบัน: <?= $escape($enrollment['classroom_name'] ?? 'ยังไม่จัดห้อง') ?></p>
<?php $lastPlacement = $enrollment['placements'][array_key_last($enrollment['placements'])] ?? null; ?>
<?php if ($enrollment['classroom_id'] === null && $lastPlacement !== null): ?><p>ห้องล่าสุด: <?= $escape($lastPlacement['classroom_name']) ?> (สิ้นสุดแล้ว)</p><?php endif; ?>
<h4>ประวัติการจัดห้องเรียน</h4>
<div class="pp5-table-scroll" role="region" aria-label="ประวัติการจัดห้องเรียน" tabindex="0"><table class="table pp5-table"><thead><tr><th scope="col">ประวัติห้องเรียน</th><th scope="col">สถานะ</th><th scope="col">เริ่ม</th><th scope="col">สิ้นสุด</th></tr></thead><tbody>
<?php foreach ($enrollment['placements'] as $placement): ?><tr><th scope="row"><?= $escape($placement['classroom_name']) ?></th><td><?= App\Support\View::render('ui/status', ['status'=>$placement['status'], 'kind'=>'placement']) ?></td><td><?= $escape($placement['started_at']) ?></td><td><?= $escape($placement['ended_at'] ?? 'ยังไม่สิ้นสุด') ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($enrollment['placements'] === []): ?><p class="pp5-empty-state">ยังไม่มีประวัติห้องเรียน</p><?php endif; ?>
<?php if ($canManageEnrollment): ?><p><a class="btn btn-outline-secondary" href="/academic/enrollments/<?= $escape($enrollment['id']) ?>/edit">รายละเอียดการลงทะเบียน</a></p><?php endif; ?>
</section>
<?php endforeach; ?>
</div>
