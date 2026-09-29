(async () => {
  'use strict';
  const output = document.getElementById('browser-results');
  const grid = document.querySelector('.pp5-gradebook');
  const first = grid.querySelector('[data-grid-row="0"][data-grid-column="0"]');
  const last = grid.querySelector('[data-grid-row="2000"][data-grid-column="0"]');
  const form = document.getElementById('gradebook-range-actions');
  const checks = [];
  const assert = (valid, message) => { if (!valid) throw new Error(message); checks.push(message); };
  let requests = 0;
  window.fetch = () => { requests++; throw new Error('Oversized fill must not fetch'); };
  try {
    assert(grid.dataset.batchLimit === '2000', 'Rendered Task 8 limit is 2,000');
    assert(first && last, 'Large fixture has 2,001 score rows');
    const input = first.querySelector('input');
    assert(input, 'First score cell has an editable input');
    input.focus();
    for (let row = 1; row <= 2000; row++) {
      input.dispatchEvent(new KeyboardEvent('keydown', {
        key: 'ArrowDown', shiftKey: true, bubbles: true, cancelable: true,
      }));
    }
    const count = document.querySelectorAll('[data-grid-selected="true"]').length;
    assert(count === 2001, `Expected 2,001 selected cells, got ${count}`);
    const summary = document.getElementById('gradebook-range-summary').textContent;
    assert(summary.includes('2001 ช่อง') && summary.includes('2000 ช่อง'), 'Range summary explains limit');
    assert(document.getElementById('gradebook-fill-submit').disabled
      && document.getElementById('gradebook-clear-submit').disabled, 'Over-limit actions disabled');
    document.getElementById('gradebook-fill-value').value = '5';
    form.requestSubmit();
    assert(requests === 0 && document.getElementById('gradebook-batch-status').textContent.includes('2000'),
      'Over-limit fill sends no request');
    output.textContent = `PASS: ${checks.length} fill-limit assertions`;
  } catch (error) { output.textContent = `FAIL: ${error.message}`; }
})();
