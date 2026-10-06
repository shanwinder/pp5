<?php
// Only these pinned local Tabler Icons may be inserted into trusted templates.
$icons = ['dots-vertical', 'users', 'books', 'notebook'];
if (!in_array($name, $icons, true)) {
    throw new InvalidArgumentException('Unknown UI icon');
}
$svg = file_get_contents(dirname(__DIR__, 2) . '/assets/vendor/tabler-icons/' . $name . '.svg');
if ($svg === false) {
    throw new RuntimeException('UI icon unavailable');
}
echo preg_replace('/<svg\b/', '<svg class="icon" aria-hidden="true" focusable="false"', $svg, 1);
