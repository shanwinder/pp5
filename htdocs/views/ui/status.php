<?php
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tone = match ($status) {
    'ACTIVE', 'APPLIED' => 'green',
    'DRAFT', 'PREVIEW' => 'azure',
    'CLOSED', 'INACTIVE', 'ENDED', 'EXPIRED' => 'secondary',
    'SUSPENDED', 'WITHDRAWN', 'ERROR', 'CONFLICT' => 'red',
    default => 'blue',
};
?>
<span class="badge bg-<?= $tone ?>-lt text-<?= $tone ?> pp5-badge" data-status="<?= $escape($status) ?>"><?= $escape(App\Support\StatusLabel::text($status, $kind ?? 'entity')) ?></span>
