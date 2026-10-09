<div class="pp5-admin-page pp5-admin-tabler">
<?php if ($permissions['SCHOOL_USER_CREATE']): ?><div class="d-flex justify-content-end mb-3"><a class="btn btn-primary" href="/admin/users/create">สร้างผู้ใช้</a></div><?php endif; ?>
  <?php if ($members === []): ?>
    <p class="text-secondary p-3 mb-0">ยังไม่มีสมาชิกโรงเรียน</p>
  <?php else: ?>
    <div class="card pp5-table-scroll" role="region" aria-label="ผู้ใช้งาน" tabindex="0">
<div class="card-status-top bg-blue" aria-hidden="true"></div>
<table class="table table-vcenter table-hover mb-0 pp5-table pp5-user-table">
      <thead>
        <tr><th scope="col">ชื่อผู้ใช้</th><th scope="col">ชื่อที่แสดง</th><th scope="col">อีเมล</th><th scope="col">สถานะสมาชิก</th><th scope="col">บทบาท</th><th scope="col" class="pp5-admin-actions">จัดการ</th></tr>
      </thead>
      <tbody>
      <?php foreach ($members as $member): ?>
        <tr>
          <th scope="row"><?= htmlspecialchars($member['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
          <td><?= htmlspecialchars($member['display_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($member['email'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
          <td><?= App\Support\View::render('ui/status', ['status'=>$member['status'], 'kind'=>'membership']) ?></td>
          <td><?= htmlspecialchars(implode(', ', $member['role_codes']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
          <td><a class="btn btn-sm btn-icon btn-outline-primary pp5-row-action" href="/admin/users/<?= htmlspecialchars((string) $member['user_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/edit" aria-label="จัดการผู้ใช้ <?= htmlspecialchars((string) ($member['display_name']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="จัดการผู้ใช้ <?= htmlspecialchars((string) ($member['display_name']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-tooltip="จัดการผู้ใช้ <?= htmlspecialchars((string) ($member['display_name']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= App\Support\View::render('ui/icon', ['name'=>'dots-vertical']) ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
</div>
  <?php endif; ?>
</div>
