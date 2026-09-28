(() => {
  'use strict';
  const output = document.getElementById('browser-results');
  const checks = [];
  const assert = (condition, label) => { if (!condition) throw new Error(label); checks.push(label); };
  const cell = (row, column) => document.querySelector(`td[data-grid-row="${row}"][data-grid-column="${column}"]`);
  try {
    const start = cell(0, 0), end = cell(3, 1);
    assert(!!start && !!end && !document.querySelector('[data-score-input], [hx-post]'), 'Read-only Gradebook has score TDs and no edit inputs or POST targets');
    end.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'instant' });
    start.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, cancelable: true,
      pointerId: 5, pointerType: 'mouse', isPrimary: true, button: 0 }));
    const box = end.getBoundingClientRect();
    document.dispatchEvent(new PointerEvent('pointermove', { bubbles: true, pointerId: 5,
      pointerType: 'mouse', isPrimary: true, button: 0, buttons: 1, clientX: box.left + box.width / 2,
      clientY: box.top + box.height / 2 }));
    document.dispatchEvent(new PointerEvent('pointerup', { bubbles: true, pointerId: 5,
      pointerType: 'mouse', isPrimary: true, button: 0 }));
    assert(document.querySelectorAll('td[data-grid-selected="true"]').length === 8, 'Read-only drag selects an eight-cell rectangle');
    const clipboardData = new DataTransfer();
    const event = new ClipboardEvent('copy', { bubbles: true, cancelable: true, clipboardData });
    document.dispatchEvent(event);
    assert(event.defaultPrevented && clipboardData.getData('text/plain') === '\t0.00\n1.50\t6.00\n5.00\t\n7.25\t',
      'Read-only copy includes visible current and historical values with blank and zero');
    assert(!document.querySelector('td[data-grid-editable="true"]'), 'Selection never makes a read-only cell editable');
    output.textContent = `PASS: ${checks.length} read-only browser assertions\n` + checks.join('\n');
    document.title = `PASS ${checks.length} — Read-only Gradebook range`;
  } catch (error) {
    output.textContent = `FAIL after ${checks.length}: ${error.message}\n` + checks.join('\n');
    document.title = 'FAIL — Read-only Gradebook range';
  }
})();
