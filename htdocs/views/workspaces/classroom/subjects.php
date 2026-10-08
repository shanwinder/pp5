<?php
use App\Support\View;
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$room = $workspace['classroom'];
$year = $workspace['academicYear'];
?>
<div class="pp5-admin-page pp5-subject-workspace" id="subject-workspace-content">
  <section class="card pp5-subject-hero mb-4" aria-labelledby="subject-workspace-heading">
    <div class="card-status-start bg-purple"></div>
    <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div><div class="subheader mb-2">งานรายวิชา / <?= $escape($room['name']) ?></div>
        <h2 class="card-title h2 mb-2" id="subject-workspace-heading">จัดการรายวิชาในห้องนี้ <span class="badge bg-purple-lt text-purple ms-2"><?= $escape(count($offerings)) ?> รายการ</span></h2>
        <p class="text-secondary mb-2">เลือกตัวเลือกในแถวรายวิชาเพื่อดูครูผู้สอนและการเก็บคะแนนในหน้านี้ แต่ละภาคเรียนเป็นรายการแยกกัน</p>
        <p class="mb-0">ปีการศึกษา <?= View::render('ui/status', ['status' => $year['status'], 'kind' => 'academic-year']) ?> · ห้องเรียน <?= View::render('ui/status', ['status' => $room['status'], 'kind' => 'entity']) ?></p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?php if ($canOpenOffering): ?><a class="btn btn-primary" href="/academic/offerings/create?<?= $escape(http_build_query(['workspace_classroom_id' => $room['id']])) ?>">เปิดรายวิชาในห้องนี้</a><?php endif; ?>
        <?php if ($canManageSubjects): ?><a class="btn btn-outline-secondary" href="/academic/subjects/create">เพิ่มรายวิชาของโรงเรียน</a><?php endif; ?>
      </div>
    </div>
  </section>
  <?php if (!$openYear): ?><div class="alert alert-info" role="status">ปีการศึกษานี้ปิดแล้ว แสดงข้อมูลเดิมเพื่ออ่านเท่านั้น</div><?php endif; ?>
  <section class="card mb-4" aria-labelledby="classroom-subjects-heading">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><div><div class="subheader">รายการที่เปิดสอน</div><h2 class="card-title mb-0" id="classroom-subjects-heading">รายวิชาใน <?= $escape($room['name']) ?></h2></div>
      <div class="d-flex flex-wrap gap-3">
        <?php if ($canViewAll): ?><a href="/academic/offerings?<?= $escape(http_build_query(['academic_year_id' => $year['id'], 'workspace_classroom_id' => $room['id']])) ?>">รายวิชาทั้งปี</a><?php endif; ?>
        <?php if ($canManageAssignment): ?><a href="/academic/teaching-assignments?<?= $escape(http_build_query(['academic_year_id' => $year['id'], 'workspace_classroom_id' => $room['id']])) ?>">ประวัติการมอบหมาย</a><?php endif; ?>
      </div>
    </div>
    <div class="pp5-table-scroll pp5-subject-scroll table-responsive" role="region" aria-label="รายวิชาและครูในห้องนี้" tabindex="0">
      <table class="table table-vcenter table-hover card-table pp5-table" id="classroom-subjects" aria-describedby="classroom-subjects-heading">
        <caption>รายวิชาและครู · <?= $escape($room['name']) ?> · ปีการศึกษา <?= $escape($year['year_be']) ?></caption>
        <thead><tr><th scope="col">รหัส / รายวิชา</th><th scope="col">ภาคเรียน</th><th scope="col">สถานะ</th><th scope="col">ครูผู้สอน</th><th scope="col">การเก็บคะแนน</th><th scope="col">ตัวเลือก</th></tr></thead><tbody>
        <?php foreach ($offerings as $offering): ?>
          <tr data-offering-id="<?= $escape($offering['id']) ?>">
            <th scope="row"><span class="text-secondary d-block small"><?= $escape($offering['code']) ?></span><?= $escape($offering['name']) ?></th>
            <td>ภาคเรียน <?= $escape($offering['term']) ?></td>
            <td><?= View::render('ui/status', ['status' => $offering['status'], 'kind' => 'entity']) ?></td>
            <td><?php if (!$offering['teacherNamesVisible']): ?>ไม่มีสิทธิ์ดูข้อมูลครูผู้สอน<?php elseif ($offering['teachers'] === []): ?>ยังไม่มีครูที่กำลังสอน<?php else: ?><?= $escape(implode(', ', array_column($offering['teachers'], 'name'))) ?><?php endif; ?></td>
            <td><?php if ($offering['scoreSummary']['active_count'] === 0): ?>ยังไม่ได้ตั้งค่าการเก็บคะแนนที่ใช้งานอยู่<?php else: ?><?= $escape($offering['scoreSummary']['active_count']) ?> รายการ · คะแนนเต็มรวม <?= $escape($offering['scoreSummary']['active_max_total']) ?><?php endif; ?><?php if ($offering['scoreSummary']['inactive_count'] > 0): ?><span class="text-secondary d-block small"><?= $escape($offering['scoreSummary']['inactive_count']) ?> รายการปิดใช้งาน</span><?php endif; ?></td>
            <td><a class="btn btn-outline-primary btn-sm btn-icon pp5-row-action" href="/workspaces/classrooms/<?= $escape($room['id']) ?>/subjects?offering_id=<?= $escape($offering['id']) ?>" hx-get="/hx/workspaces/classrooms/<?= $escape($room['id']) ?>/subjects/<?= $escape($offering['id']) ?>" hx-target="#subject-context" hx-swap="innerHTML" data-subject-trigger="<?= $escape($offering['id']) ?>" aria-label="<?= $escape('จัดการ' . $offering['name'] . ' ภาคเรียน ' . $offering['term']) ?>" title="จัดการรายวิชา" data-tooltip="จัดการรายวิชา"><?= View::render('ui/icon', ['name' => 'dots-vertical']) ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($offerings === []): ?><div class="card-body text-secondary">ยังไม่มีรายวิชาที่คุณเข้าถึงได้ในห้องนี้</div><?php endif; ?>
  </section>
  <div id="subject-context" class="pp5-subject-context mb-4" aria-live="off"><?= $selectedPanel ?? '' ?></div>
</div>
