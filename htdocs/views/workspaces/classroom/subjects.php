<?php
use App\Support\View;
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$room = $workspace['classroom'];
$year = $workspace['academicYear'];
$lastSubject = null;
?>
<div class="pp5-admin-page">
  <p>รายวิชาที่เปิดสอนใน <?= $escape($room['name']) ?> · ปีการศึกษา <?= $escape($year['year_be']) ?> แต่ละภาคเรียนเป็นรายการแยกกัน</p>
  <?php if (!$openYear): ?><p class="pp5-alert pp5-alert--info">ปีการศึกษานี้ปิดแล้ว ดูข้อมูลเดิมได้ แต่แก้ไขการเปิดรายวิชาและการมอบหมายไม่ได้</p><?php endif; ?>
  <div class="pp5-actions">
    <?php if ($canManageSubjects): ?><a href="/academic/subjects/create">เพิ่มรายวิชาของโรงเรียน</a><?php endif; ?>
    <?php if ($canOpenOffering): ?><a class="btn btn-primary" href="/academic/offerings/create?<?= $escape(http_build_query(['workspace_classroom_id' => $room['id']])) ?>">เปิดรายวิชาในห้องนี้</a><?php endif; ?>
    <?php if ($canViewAll): ?><a href="/academic/offerings?<?= $escape(http_build_query(['academic_year_id' => $year['id'], 'workspace_classroom_id' => $room['id']])) ?>">ดูรายการรายวิชาทั้งปี</a><?php endif; ?>
    <?php if ($canManageAssignment): ?><a href="/academic/teaching-assignments?<?= $escape(http_build_query(['academic_year_id' => $year['id'], 'workspace_classroom_id' => $room['id']])) ?>">ดูประวัติการมอบหมายทั้งปี</a><?php endif; ?>
  </div>
  <?php if ($offerings === []): ?>
    <p class="pp5-empty-state">ยังไม่มีรายวิชาที่คุณเข้าถึงได้ในห้องนี้</p>
  <?php else: ?>
    <div class="pp5-table-scroll" role="region" aria-label="รายวิชาและครูในห้องนี้" tabindex="0">
      <table class="table pp5-table" id="classroom-subjects">
        <caption>รายวิชาและครู · <?= $escape($room['name']) ?> · ปีการศึกษา <?= $escape($year['year_be']) ?></caption>
        <thead><tr><th scope="col">รหัส / รายวิชา</th><th scope="col">ภาคเรียน</th><th scope="col">สถานะ</th><th scope="col">ครูผู้สอน</th><th scope="col">การเก็บคะแนน</th><th scope="col">การทำงาน</th></tr></thead>
        <?php foreach ($offerings as $offering): ?>
          <?php if ($lastSubject !== $offering['subjectId']): ?>
            <?php if ($lastSubject !== null): ?></tbody><?php endif; ?>
            <tbody>
            <?php $lastSubject = $offering['subjectId']; ?>
          <?php endif; ?>
          <tr data-offering-id="<?= $escape($offering['id']) ?>">
            <th scope="row"><?= $escape($offering['code']) ?><br><?= $escape($offering['name']) ?></th>
            <td>ภาคเรียน <?= $escape($offering['term']) ?></td>
            <td><?= View::render('ui/status', ['status' => $offering['status'], 'kind' => 'entity']) ?></td>
            <td>
              <?php if (!$offering['teacherNamesVisible']): ?>ไม่มีสิทธิ์ดูข้อมูลครูผู้สอน
              <?php elseif ($offering['teachers'] === []): ?>ยังไม่มีครูที่กำลังสอน
              <?php else: ?>
                <ul class="list-unstyled">
                <?php foreach ($offering['teachers'] as $teacher): ?><li><?= $escape($teacher['name']) ?>
                  <?php if ($canManageAssignment && $openYear): ?>
                    <form method="post" action="/academic/teaching-assignments/<?= $escape($teacher['id']) ?>/status?workspace_classroom_id=<?= $escape($room['id']) ?>" class="pp5-sensitive">
                      <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                      <input type="hidden" name="status" value="INACTIVE">
                      <button class="btn btn-outline-secondary" type="submit" aria-label="<?= $escape('หยุดมอบหมาย ' . $teacher['name'] . ' ภาคเรียน ' . $offering['term']) ?>">หยุดมอบหมาย</button>
                    </form>
                  <?php endif; ?>
                </li><?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($offering['scoreSummary']['active_count'] === 0): ?>ยังไม่ได้ตั้งค่าการเก็บคะแนนที่ใช้งานอยู่
              <?php else: ?><?= $escape($offering['scoreSummary']['active_count']) ?> รายการ · คะแนนเต็มรวม <?= $escape($offering['scoreSummary']['active_max_total']) ?><?php endif; ?>
              <?php if ($offering['scoreSummary']['inactive_count'] > 0): ?><br><?= $escape($offering['scoreSummary']['inactive_count']) ?> รายการปิดใช้งาน<?php endif; ?>
            </td>
            <td>
              <div class="pp5-actions">
                <?php if ($offering['canEdit']): ?><a href="/academic/offerings/<?= $escape($offering['id']) ?>/edit" aria-label="<?= $escape('จัดการรายวิชา ' . $offering['name'] . ' ภาคเรียน ' . $offering['term']) ?>">จัดการรายวิชา / สถานะ</a><?php endif; ?>
                <?php if ($offering['canSetup']): ?><a href="/gradebook/<?= $escape($offering['id']) ?>/setup" aria-label="<?= $escape('ตั้งค่าการเก็บคะแนน ' . $offering['name'] . ' ภาคเรียน ' . $offering['term']) ?>">ตั้งค่าการเก็บคะแนน</a><?php endif; ?>
                <?php if ($offering['canOpenGradebook']): ?><a href="/gradebook/<?= $escape($offering['id']) ?>" aria-label="<?= $escape('เปิดสมุดคะแนน ' . $offering['name'] . ' ภาคเรียน ' . $offering['term']) ?>">เปิดสมุดคะแนน</a><?php endif; ?>
              </div>
              <?php if ($offering['canAssign'] && $teacherChoices !== []): ?>
                <form method="post" action="/academic/teaching-assignments?workspace_classroom_id=<?= $escape($room['id']) ?>" class="pp5-form">
                  <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                  <input type="hidden" name="subject_offering_id" value="<?= $escape($offering['id']) ?>">
                  <label for="teacher-offering-<?= $escape($offering['id']) ?>">มอบหมายครู · ภาคเรียน <?= $escape($offering['term']) ?></label>
                  <select class="form-select" id="teacher-offering-<?= $escape($offering['id']) ?>" name="user_role_assignment_id" required>
                    <option value="">เลือกครู</option>
                    <?php foreach ($teacherChoices as $choice): ?><option value="<?= $escape($choice['user_role_assignment_id']) ?>"><?= $escape($choice['display_name']) ?></option><?php endforeach; ?>
                  </select>
                  <button class="btn btn-outline-secondary" type="submit">มอบหมาย</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
