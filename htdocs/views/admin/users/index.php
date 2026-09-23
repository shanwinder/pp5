<div class="pp5-admin-page">
<?php if ($permissions['SCHOOL_USER_CREATE']): ?><p><a class="btn btn-primary" href="/admin/users/create">สร้างผู้ใช้</a></p><?php endif; ?>
  <?php if ($members === []): ?>
    <p class="pp5-empty-state">ยังไม่มีสมาชิกโรงเรียน</p>
  <?php else: ?>
    <div class="pp5-table-scroll" role="region" aria-label="ผู้ใช้งาน" tabindex="0">
<table class="table pp5-table">
      <thead>
        <tr><th scope="col">ชื่อผู้ใช้</th><th scope="col">ชื่อที่แสดง</th><th scope="col">อีเมล</th><th scope="col">สถานะสมาชิก</th><th scope="col">บทบาท</th><th scope="col">จัดการ</th></tr>
      </thead>
      <tbody>
      <?php foreach ($members as $member): ?>
        <tr>
          <th scope="row"><?= htmlspecialchars($member['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
          <td><?= htmlspecialchars($member['display_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($member['email'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
          <td><?= App\Support\View::render('ui/status', ['status'=>$member['status'], 'kind'=>'membership']) ?></td>
          <td><?= htmlspecialchars(implode(', ', $member['role_codes']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
          <td><a href="/admin/users/<?= htmlspecialchars((string) $member['user_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/edit">จัดการผู้ใช้</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
</div>
  <?php endif; ?>
</div>
