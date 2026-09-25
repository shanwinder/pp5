<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$prefix = 'row-' . $offeringId . '-' . $row['enrollment_id'] . '-';
$values = ['total' => $row['entered_score_total'], 'max' => $row['configured_max_total'],
    'count' => $row['entered_component_count'] . ' / ' . $row['active_component_count'], 'complete' => $row['complete'] ? 'ครบ' : 'ยังไม่ครบ'];
?>
<?php foreach ($values as $suffix => $value): ?>
  <?php if (!($outOfBand ?? false)): ?><td class="pp5-gradebook-summary"><?php endif; ?>
  <span id="<?= $escape($prefix . $suffix) ?>"<?= ($outOfBand ?? false) ? ' hx-swap-oob="outerHTML"' : '' ?>><?= $escape($value) ?></span>
  <?php if (!($outOfBand ?? false)): ?></td><?php endif; ?>
<?php endforeach; ?>
