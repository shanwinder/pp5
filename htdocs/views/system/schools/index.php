  <?php if (is_string($error) && $error !== ''): ?>
    <div role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if ($schools === []): ?>
    <p>ยังไม่มีโรงเรียน</p>
  <?php elseif ($schools !== null): ?>
    <div class="pp5-table-scroll" role="region" aria-label="รายการโรงเรียน" tabindex="0">
    <table>
      <thead>
        <tr><th scope="col">รหัสโรงเรียน</th><th scope="col">ชื่อโรงเรียน</th><th scope="col">สถานะ</th><th scope="col">เปลี่ยนสถานะ</th></tr>
      </thead>
      <tbody>
      <?php foreach ($schools as $school): ?>
        <tr>
          <td><?= htmlspecialchars($school['school_code'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($school['name_th'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($school['status'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <?php if ($canChangeStatus): ?><form method="post" action="/system/schools/<?= htmlspecialchars((string) $school['id'], ENT_QUOTES, 'UTF-8') ?>/status">
              <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
              <label>สถานะใหม่
                <select name="status">
                  <?php foreach (['ACTIVE' => 'ใช้งาน', 'SUSPENDED' => 'ระงับชั่วคราว', 'INACTIVE' => 'ไม่ใช้งาน'] as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"<?= $school['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button type="submit">บันทึกสถานะ</button>
            </form><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
