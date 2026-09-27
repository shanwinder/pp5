<?php
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$groups = [];
foreach ($targets as $target) { $groups[$target['year_be']][] = $target; }
?>
<div class="pp5-workspace-choices">
<?php foreach ($groups as $year => $rooms): ?>
  <div><strong>ปีการศึกษา <?= $escape($year) ?></strong>
    <ul>
    <?php foreach ($rooms as $room): ?>
      <li><a href="<?= $escape($room['url']) ?>"<?= ($currentId ?? null) === $room['id'] ? ' aria-current="location"' : '' ?>><?= $escape($room['code'] . ' · ' . $room['name']) ?></a></li>
    <?php endforeach; ?>
    </ul>
  </div>
<?php endforeach; ?>
</div>
