<div class="pp5-admin-page pp5-admin-tabler">
<?php if ($permissions['SYSTEM_SCHOOL_CREATE']): ?><div class="d-flex justify-content-end mb-3"><a class="btn btn-primary" href="/system/schools/create">เพิ่มโรงเรียน</a></div><?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if ($schools === []): ?>
    <p class="text-secondary p-3 mb-0">ยังไม่มีโรงเรียน</p>
  <?php elseif ($schools !== null): ?>
    <div class="card pp5-table-scroll" role="region" aria-label="รายการโรงเรียน" tabindex="0">
    <div class="card-status-top bg-blue" aria-hidden="true"></div>
<table class="table table-vcenter table-hover mb-0 pp5-table">
      <thead>
        <tr><th scope="col">รหัสโรงเรียน</th><th scope="col">ชื่อโรงเรียน</th><th scope="col">สถานะ</th><th scope="col" class="pp5-admin-actions">เปลี่ยนสถานะ</th></tr>
      </thead>
      <tbody>
      <?php foreach ($schools as $school): ?>
        <tr>
          <th scope="row"><?= htmlspecialchars($school['school_code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
          <td><?= htmlspecialchars($school['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
          <td><?= App\Support\View::render('ui/status', ['status'=>$school['status'], 'kind'=>'school']) ?></td>
          <td>
            <?php if ($canChangeStatus): ?><details data-admin-detail><summary class="btn btn-sm btn-icon btn-outline-secondary pp5-row-action" aria-label="เปลี่ยนสถานะ <?= htmlspecialchars($school['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="เปลี่ยนสถานะ <?= htmlspecialchars($school['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-tooltip="เปลี่ยนสถานะ <?= htmlspecialchars($school['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= App\Support\View::render('ui/icon', ['name'=>'dots-vertical']) ?></summary><div class="pp5-admin-row-panel pt-3"><h3 class="h4">เปลี่ยนสถานะ <?= htmlspecialchars($school['name_th'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3><form class="card card-body mb-3 border-danger" method="post" action="/system/schools/<?= htmlspecialchars((string) $school['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/status" data-confirm="ยืนยันการเปลี่ยนสถานะโรงเรียนหรือไม่? การระงับหรือปิดใช้งานโรงเรียนจะหยุดการเข้าถึงข้อมูลในบริบทโรงเรียนนั้น"><p class="text-secondary">การระงับหรือปิดใช้งานโรงเรียนจะหยุดการเข้าถึงข้อมูลในบริบทโรงเรียนนั้น</p>
              <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <label class="form-label" for="system-schools-index-status-<?= (int) $school['id'] ?>">สถานะใหม่</label><select class="form-select" id="system-schools-index-status-<?= (int) $school['id'] ?>" name="status" required>
                  <?php foreach (['ACTIVE', 'SUSPENDED', 'INACTIVE'] as $value): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $school['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars(App\Support\StatusLabel::text($value, 'school'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>

              <button class="btn btn-danger align-self-start" type="submit">เปลี่ยนสถานะโรงเรียน</button>
            </form></div></details><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
