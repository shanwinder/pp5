<?php $escape = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<div class="pp5-admin-page">
<?= App\Support\View::render('academic/student-import/steps', ['step'=>($batch['status'] ?? null) === 'APPLIED' ? 3 : 2]) ?>
<p><a class="btn btn-outline-secondary" href="/academic/student-import">กลับหน้านำเข้า</a></p>
<?php if ($error !== null): ?><p class="pp5-alert pp5-alert--danger" role="alert"><?= $escape($error) ?></p><?php endif; ?>
<?php if ($batch !== null): ?>
<h2>สรุปการตรวจสอบ</h2>
<?php if ($batch['status'] === 'PREVIEW'): ?><p>ตัวอย่างเป็นข้อมูลชั่วคราว โปรดยืนยันก่อนวันหมดอายุที่แสดง</p><?php endif; ?>
<dl class="pp5-surface">
    <dt>ไฟล์</dt><dd><?= $escape($batch['source_name']) ?></dd>
    <dt>สถานะ</dt><dd><?= App\Support\View::render('ui/status', ['status'=>$batch['status'], 'kind'=>'import-batch']) ?></dd>
    <dt>จำนวนแถว</dt><dd><?= $escape($batch['row_count']) ?></dd>
    <dt>นักเรียนใหม่</dt><dd><?= $escape($batch['create_student_count']) ?></dd>
    <dt>ลงทะเบียนใหม่</dt><dd><?= $escape($batch['create_enrollment_count']) ?></dd>
    <dt>ข้อมูลตรงกัน ไม่ต้องเปลี่ยน</dt><dd><?= $escape($batch['noop_count']) ?></dd>
    <dt>แถวที่ต้องแก้ไข</dt><dd><?= $escape($batch['error_count']) ?></dd>
    <dt>หมดอายุ</dt><dd><?= $escape($batch['expires_at']) ?></dd>
</dl>
<?php if ($batch['error_count'] > 0): ?><p class="pp5-alert pp5-alert--danger" role="alert">พบแถวที่ต้องแก้ไข กรุณาแก้ไขไฟล์แล้วอัปโหลดใหม่</p><?php endif; ?>
<?php if ($batch['status'] === 'PREVIEW' && $batch['error_count'] === 0 && $batch['create_student_count'] === 0 && $batch['create_enrollment_count'] === 0): ?><p class="pp5-alert pp5-alert--info">ข้อมูลตรงกันทั้งหมด ไม่มีรายการใหม่ให้ยืนยันนำเข้า</p><?php endif; ?>
<?php if ($rows !== []): ?>
<h2>ผลตรวจสอบรายแถว</h2>
<div class="pp5-table-scroll" role="region" aria-label="ผลตรวจสอบการนำเข้า" tabindex="0"><table class="table pp5-table"><thead><tr><th scope="col">แถว</th><th scope="col">รหัสนักเรียน</th><th scope="col">ชื่อ–นามสกุล</th><th scope="col">ระดับชั้น</th><th scope="col">ห้อง</th><th scope="col">นักเรียน</th><th scope="col">การลงทะเบียน</th><th scope="col">ข้อผิดพลาด</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><th scope="row"><?= $escape($row['row_no']) ?></th><td><?= $escape($row['student_code']) ?></td>
<td><?= $escape(implode(' ', [$row['prefix_th'], $row['first_name_th'], $row['last_name_th']])) ?></td>
<td><?= $escape($row['grade_level_code']) ?></td><td><?= $escape($row['classroom_code']) ?></td>
<td><?= App\Support\View::render('ui/status', ['status'=>$row['student_action'], 'kind'=>'import-action']) ?></td><td><?= App\Support\View::render('ui/status', ['status'=>$row['enrollment_action'], 'kind'=>'import-action']) ?></td><td><?php if ($row['error_code'] !== null): ?><?= App\Support\View::render('ui/status', ['status'=>$row['error_code'], 'kind'=>'import-action']) ?><?php endif; ?> <?= $escape($row['error_message']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php if ($batch['status'] === 'PREVIEW' && !$batch['is_expired']): ?>
<h2>ยืนยันหรือยกเลิกตัวอย่าง</h2>
<?php if ($batch['error_count'] === 0 && ($batch['create_student_count'] > 0 || $batch['create_enrollment_count'] > 0)): ?>
<form class="pp5-form pp5-surface" method="post" action="/academic/student-import/<?= $escape($batch['id']) ?>/apply" data-confirm="ยืนยันนำเข้าข้อมูลนักเรียนและการลงทะเบียนจากตัวอย่างนี้หรือไม่?">
    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
    <button class="btn btn-primary" type="submit">ยืนยันนำเข้า</button>
</form>
<?php endif; ?>
<form class="pp5-form pp5-surface" method="post" action="/academic/student-import/<?= $escape($batch['id']) ?>/cancel" data-confirm="ยืนยันยกเลิกตัวอย่างนี้หรือไม่? หากต้องการนำเข้าภายหลัง จะต้องอัปโหลดไฟล์ใหม่">
    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
    <button class="btn btn-danger" type="submit">ยกเลิกตัวอย่าง</button>
</form>
<?php endif; ?>
<?php endif; ?>
</div>
