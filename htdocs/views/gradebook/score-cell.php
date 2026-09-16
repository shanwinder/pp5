<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$cellId = 'score-' . $offeringId . '-' . $componentId . '-' . $enrollmentId;
?>
<div id="<?= $escape($cellId) ?>" data-score-cell data-save-state="<?= ($saved ?? false) ? 'saved' : 'idle' ?>">
  <input id="<?= $escape($cellId . '-input') ?>" name="score" type="text" inputmode="decimal" autocomplete="off"
    value="<?= $escape($score) ?>" size="7"
    aria-labelledby="<?= $escape('student-' . $enrollmentId . ' component-' . $componentId) ?>"
    aria-describedby="<?= $escape($cellId . '-status') ?>"
    data-score-input data-offering-id="<?= $escape($offeringId) ?>"
    data-component-id="<?= $escape($componentId) ?>" data-enrollment-id="<?= $escape($enrollmentId) ?>"
    hx-post="<?= $escape('/hx/gradebook/' . $offeringId . '/components/' . $componentId . '/enrollments/' . $enrollmentId . '/score') ?>"
    hx-trigger="blur" hx-include="#gradebook-csrf" hx-params="score,_token"
    hx-target="closest [data-score-cell]" hx-swap="outerHTML" hx-sync="closest table:queue all">
  <small id="<?= $escape($cellId . '-status') ?>" role="status" aria-live="polite" style="display: block"><?= ($saved ?? false) ? 'บันทึกแล้ว' : '' ?></small>
</div>
