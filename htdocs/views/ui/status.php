<?php
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<span class="pp5-badge" data-status="<?= $escape($status) ?>"><?= $escape(App\Support\StatusLabel::text($status, $kind ?? 'entity')) ?></span>
