<div class="pp5-admin-page">
<?php if ($permissions['SYSTEM_SCHOOL_CREATE']): ?><p><a class="btn btn-primary" href="/system/schools/create">เพิ่มโรงเรียน</a></p><?php endif; ?>
<?php if ($canChangeStatus): ?><p>การระงับหรือปิดใช้งานโรงเรียนจะหยุดการเข้าถึงข้อมูลในบริบทโรงเรียนนั้น</p><?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="pp5-alert pp5-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if ($schools === []): ?>
    <p class="pp5-empty-state">ยังไม่มีโรงเรียน</p>
  <?php elseif ($schools !== null): ?>
    <div class="pp5-table-scroll" role="region" aria-label="รายการโรงเรียน" tabindex="0">
    <table class="table pp5-table">
      <thead>
        <tr><th scope="col">รหัสโรงเรียน</th><th scope="col">ชื่อโรงเรียน</th><th scope="col">สถานะ</th><th scope="col">เปลี่ยนสถานะ</th></tr>
      </thead>
      <tbody>
      <?php foreach ($schools as $school): ?>
        <tr>
          <th scope="row"><?= htmlspecialchars($school['school_code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
          <td><?= htmlspecialchars($school['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
          <td><?= App\Support\View::render('ui/status', ['status'=>$school['status'], 'kind'=>'school']) ?></td>
          <td>
            <?php if ($canChangeStatus): ?><form class="pp5-form pp5-surface pp5-sensitive" method="post" action="/system/schools/<?= htmlspecialchars((string) $school['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/status" data-confirm="ยืนยันการเปลี่ยนสถานะโรงเรียนหรือไม่? การระงับหรือปิดใช้งานโรงเรียนจะหยุดการเข้าถึงข้อมูลในบริบทโรงเรียนนั้น">
              <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <label class="form-label" for="system-schools-index-status-<?= (int) $school['id'] ?>">สถานะใหม่
                <select class="form-select" id="system-schools-index-status-<?= (int) $school['id'] ?>" name="status">
                  <?php foreach (['ACTIVE', 'SUSPENDED', 'INACTIVE'] as $value): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $school['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars(App\Support\StatusLabel::text($value, 'school'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button class="btn btn-danger" type="submit">เปลี่ยนสถานะโรงเรียน</button>
            </form><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
