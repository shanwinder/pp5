<?php
use App\Support\View;
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$legacyQuery = http_build_query(['academic_year_id' => $workspace['academicYear']['id'],
    'grade_level_id' => $workspace['gradeLevel']['id'], 'classroom_id' => $workspace['classroom']['id'],
    'workspace_classroom_id' => $workspace['classroom']['id']]);
?>
<div class="pp5-admin-page pp5-student-workspace" id="student-workspace-content">
  <div class="pp5-workflow-result" role="status" aria-live="polite" tabindex="-1" data-workflow-result>
    <?php if (($workflowSuccess ?? null) !== null): ?><div class="alert alert-success mb-3" tabindex="-1" data-workflow-focus><?= $escape($workflowSuccess) ?></div><?php endif; ?>
    <?php if (($workflowError ?? null) !== null): ?><div class="alert alert-danger mb-3" tabindex="-1" data-workflow-focus><?= $escape($workflowError) ?></div><?php endif; ?>
  </div>
  <section class="card pp5-hero mb-4" aria-labelledby="student-workspace-heading">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div><div class="subheader mb-2">งานนักเรียน / <?= $escape($workspace['classroom']['name']) ?></div><h2 class="card-title h2 mb-2" id="student-workspace-heading">รายชื่อนักเรียน <span class="badge bg-blue-lt text-blue ms-2"><?= $escape(count($students)) ?> คน</span></h2><p class="text-secondary mb-2">เลือกปุ่มตัวเลือกที่แถวนักเรียนเพื่อดูข้อมูลและงานที่ทำได้ในหน้าเดิม</p><p class="pp5-student-context-status mb-0">ปีการศึกษา <?= View::render('ui/status', ['status' => $workspace['academicYear']['status'], 'kind' => 'academic-year']) ?> · ห้องเรียน <?= View::render('ui/status', ['status' => $workspace['classroom']['status']]) ?></p></div>
      <div class="pp5-student-top-actions d-flex flex-wrap gap-2">
        <?php if ($canAdd): ?><a class="btn btn-primary" href="/academic/enrollments/create?<?= $escape(http_build_query(['academic_year_id' => $workspace['academicYear']['id'], 'grade_level_id' => $workspace['gradeLevel']['id']])) ?>">เพิ่มนักเรียนเข้าปีนี้</a><?php endif; ?>
        <?php if ($canImport): ?><a class="btn btn-outline-secondary" href="/academic/student-import">นำเข้านักเรียนจาก CSV</a><?php endif; ?>
      </div>
    </div>
  </section>
  <?php if (!$openYear): ?>
    <div class="alert alert-info" role="status">ปีการศึกษานี้ปิดแล้ว แสดงรายชื่อที่คงอยู่ในปีนั้นเพื่ออ่านเท่านั้น</div>
  <?php elseif ($workspace['classroom']['status'] !== 'ACTIVE'): ?>
    <div class="alert alert-info" role="status">ห้องนี้ปิดใช้งานแล้ว ยังดูรายชื่อเดิมได้ ผู้มีสิทธิ์สามารถย้ายนักเรียนไปห้องที่เปิดใช้งานหรือเปลี่ยนสถานะได้</div>
  <?php endif; ?>
  <section class="card mb-4" aria-labelledby="student-roster-heading">
    <div class="card-header pp5-roster-heading d-flex flex-wrap justify-content-between align-items-center gap-2"><div><div class="subheader">รายชื่อปัจจุบัน</div><h2 class="card-title mb-0" id="student-roster-heading">นักเรียนใน <?= $escape($workspace['classroom']['name']) ?></h2></div><a href="/academic/enrollments?<?= $escape($legacyQuery) ?>">ตัวกรองและรายชื่อเพิ่มเติม</a></div>
    <div class="card-body py-2"><p class="text-secondary mb-0 pp5-roster-note">แสดงนักเรียนที่กำลังเรียนและยังจัดอยู่ในห้องนี้ของปีการศึกษาที่เลือก เลือกนักเรียนเพื่อดูประวัติห้องเรียนและงานที่ทำได้ <span class="d-md-none">เลื่อนตารางแนวนอนเพื่อดูชื่อและสถานะ</span></p></div>
    <div class="pp5-table-scroll pp5-roster-scroll table-responsive" role="region" aria-label="นักเรียนในห้องนี้" tabindex="0">
      <table class="table table-vcenter table-hover card-table pp5-table" id="classroom-roster" aria-describedby="student-roster-heading">
        <caption>นักเรียน · <?= $escape($workspace['classroom']['name']) ?> · ปีการศึกษา <?= $escape($workspace['academicYear']['year_be']) ?></caption>
        <thead><tr><th scope="col">รหัสนักเรียน</th><th scope="col">ชื่อ–สกุล</th><th scope="col">สถานะ</th><th scope="col">ตัวเลือก</th></tr></thead><tbody>
        <?php foreach ($students as $student): ?>
        <tr class="pp5-roster-row"><td class="text-secondary"><?= $escape($student['code']) ?></td><th scope="row"><?= $escape($student['name']) ?></th><td><?= View::render('ui/status', ['status' => $student['status'], 'kind' => 'enrollment']) ?></td><td>
          <a class="btn btn-outline-primary btn-sm btn-icon pp5-row-action" href="<?= $canManage ? '/academic/enrollments/' . $escape($student['enrollmentId']) . '/edit' : '/students/' . $escape($student['studentId']) ?>" hx-get="/hx/workspaces/classrooms/<?= $escape($workspace['classroom']['id']) ?>/students/<?= $escape($student['enrollmentId']) ?>" hx-target="#student-context" hx-swap="innerHTML" data-student-trigger aria-label="<?= $escape('จัดการนักเรียน ' . $student['name']) ?>" title="จัดการนักเรียน" data-tooltip="จัดการนักเรียน"><?= View::render('ui/icon', ['name' => 'dots-vertical']) ?></a>
        </td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($students === []): ?><div class="card-body text-secondary pp5-empty-state">ยังไม่มีนักเรียนที่กำลังเรียนและจัดอยู่ในห้องนี้</div><?php endif; ?>
  </section>
  <div id="student-context" class="pp5-student-context mb-4" aria-live="off"><?= $selectedPanel ?? '' ?></div>
  <?php if ($canAdd || $canImport): ?><p class="text-secondary pp5-help">การเพิ่มและนำเข้าใช้ขั้นตอนเดิม โปรดตรวจสอบปีการศึกษาและเลือกห้องให้ตรงกับงานนี้ก่อนบันทึก</p><?php endif; ?>
</div>
