// SYNTHETIC regression checks on the isolated fixture. Real pointer and keyboard
// sequences are run separately in the browser; this suite does not claim MAMP acceptance.
(async () => {
  'use strict';
  const output = document.getElementById('browser-results');
  if (!output) return;
  const grid = document.getElementById('gradebook-tabulator');
  const checks = [], writes = [];
  const assert = (condition, label) => { if (!condition) throw new Error(label); checks.push(label); };
  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
  const until = async condition => {
    for (let n = 0; n < 200; n++) { if (condition()) return; await sleep(20); }
    throw new Error('Timed out waiting for direct-entry state');
  };
  const cell = (row, component) => [...grid.querySelectorAll('.tabulator-row')][row]
    ?.querySelector(`[tabulator-field="score_${component}"]`);
  const focusedCell = (row, component) => document.activeElement === cell(row, component);
  const choose = async (row, component) => {
    const target = cell(row, component);
    target.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    target.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    target.click();
    await until(() => document.activeElement === target);
  };
  const key = (value, options = {}) => {
    const event = new KeyboardEvent('keydown', { key: value, bubbles: true, cancelable: true, ...options });
    document.activeElement.dispatchEvent(event);
    return event;
  };
  const enter = value => { key(value); assert(grid.querySelector('input')?.value === value, `Direct entry starts with ${value}`); };
  const saved = async count => until(() => writes.length === count && !grid.querySelector('[data-pp5-saving]'));
  const originalFetch = window.fetch;
  window.fetch = (...args) => {
    if (String(args[0]).includes('/enrollments/')) writes.push({ url: String(args[0]), value: args[1]?.body?.get('score') });
    return originalFetch(...args);
  };
  try {
    await until(() => document.querySelector('.pp5-gradebook').hidden && cell(2, 12));
    await choose(1, 10);
    cell(1, 10).dispatchEvent(new MouseEvent('dblclick', { bubbles: true }));
    await until(() => grid.querySelector('input'));
    assert(grid.querySelector('input').value === '12.50', 'Double-click explicitly opens existing 12.50');
    const beforeCaret = writes.length;
    assert(!key('ArrowLeft').defaultPrevented && !key('ArrowRight').defaultPrevented
      && grid.querySelector('input')?.value === '12.50' && writes.length === beforeCaret,
    'Explicit Left and Right remain caret keys without save or navigation');
    key('Escape'); assert(focusedCell(1, 10), 'GB-EDIT-009 — Explicit Escape restores cell focus');
    key('Enter'); assert(grid.querySelector('input')?.value === '12.50', 'GB-EDIT-002 — Enter opens explicit existing-value editor');
    assert(!key('ArrowRight').defaultPrevented && grid.querySelector('input') && writes.length === beforeCaret,
      'Enter-opened explicit editor keeps ArrowRight as caret movement');
    key('Escape');
    key('F2'); assert(grid.querySelector('input')?.value === '12.50', 'GB-EDIT-003 — F2 opens explicit existing-value editor');
    assert(!key('ArrowLeft').defaultPrevented && grid.querySelector('input') && writes.length === beforeCaret,
      'F2-opened explicit editor keeps ArrowLeft as caret movement');
    const copyData = new DataTransfer();
    const editorCopy = new ClipboardEvent('copy', { bubbles: true, cancelable: true, clipboardData: copyData });
    grid.querySelector('input').dispatchEvent(editorCopy);
    assert(!editorCopy.defaultPrevented && writes.length === beforeCaret,
      'GB-EDIT-007 — Explicit editor copy remains a native text operation');
    key('Escape');
    enter('5'); key('Escape');
    assert(focusedCell(1, 10) && cell(1, 10).textContent === '12.50' && writes.length === beforeCaret,
      'GB-DIRECT-008 — Direct Escape restores original value without request');
    key('ArrowRight'); assert(focusedCell(1, 11), 'After Escape, ArrowRight navigates outside editor');

    await choose(0, 10);
    enter('5'); key('ArrowRight'); await until(() => focusedCell(0, 11));
    assert(!grid.querySelector('input'), 'Direct ArrowRight closes editor and focuses next component');
    enter('6'); key('ArrowRight'); await until(() => focusedCell(0, 12));
    enter('7'); key('ArrowRight'); await until(() => focusedCell(0, 12));
    await saved(3);
    assert(writes.map(write => write.value).join(',') === '5,6,7', 'Rapid horizontal entry has three ordered values');
    assert(writes.map(write => write.url.match(/components\/(\d+)/)?.[1]).join(',') === '10,11,12',
      'Rapid horizontal entry uses stable component IDs without duplicate writes');
    assert(cell(0, 10).textContent === '5.00' && cell(0, 11).textContent === '6.00' && cell(0, 12).textContent === '7.00',
      'Delayed horizontal responses reconcile all three cells');

    await choose(1, 11); enter('5'); key('ArrowLeft'); await until(() => focusedCell(1, 10)); await saved(4);
    assert(writes[3].url.includes('/components/11/enrollments/2/') && cell(1, 11).textContent === '5.00',
      'GB-DIRECT-003 — Direct ArrowLeft commits and moves to previous component');
    enter('5'); key('ArrowUp'); await until(() => focusedCell(0, 10)); await saved(5);
    assert(cell(1, 10).textContent === '5.00', 'GB-DIRECT-005 — Direct ArrowUp commits and moves to previous writable row');
    enter('5'); key('ArrowDown'); await until(() => focusedCell(1, 10)); await saved(6);
    assert(cell(0, 10).textContent === '5.00', 'Direct ArrowDown commits and moves to next writable row');

    await choose(0, 10);
    const verticalStart = writes.length;
    enter('5'); key('ArrowDown'); await until(() => focusedCell(1, 10));
    enter('6'); key('ArrowDown'); await until(() => focusedCell(2, 10));
    enter('7'); key('ArrowDown'); await until(() => focusedCell(2, 10));
    await saved(verticalStart + 3);
    assert(writes.slice(verticalStart).map(write => write.value).join(',') === '5,6,7'
      && writes.slice(verticalStart).map(write => write.url.match(/enrollments\/(\d+)/)?.[1]).join(',') === '1,2,3',
    'Rapid vertical entry saves once per writable row with stable enrollment IDs');
    assert(focusedCell(2, 10) && !grid.querySelector('input'),
      'Down boundary stays on last writable row rather than historical row');

    await choose(0, 12); const rightBoundaryStart = writes.length;
    enter('5'); key('ArrowRight'); await saved(rightBoundaryStart + 1);
    assert(focusedCell(0, 12), 'Right Arrow boundary stays on last score component');
    await choose(0, 10); const leftBoundaryStart = writes.length;
    enter('5'); key('ArrowLeft'); await saved(leftBoundaryStart + 1);
    assert(focusedCell(0, 10), 'Left Arrow boundary stays on first score component');

    await choose(1, 12); enter('5'); key('Enter'); await until(() => focusedCell(2, 12));
    assert(!grid.querySelector('input'), 'GB-DIRECT-006 — Direct Enter commits and moves down');
    enter('6'); key('Enter', { shiftKey: true }); await until(() => focusedCell(1, 12));
    assert(!grid.querySelector('input'), 'Direct Shift+Enter commits and moves up');
    await choose(1, 10); enter('7'); key('Tab'); await until(() => focusedCell(1, 11));
    assert(!grid.querySelector('input'), 'GB-DIRECT-007 — Direct Tab commits and moves right');
    enter('6'); key('Tab', { shiftKey: true }); await until(() => focusedCell(1, 10));
    assert(!grid.querySelector('input'), 'Direct Shift+Tab commits and moves left');
    await until(() => !grid.querySelector('[data-pp5-saving]'));

    key('Enter'); assert(grid.querySelector('input')?.value === '7.00', 'Explicit Enter retains current value');
    key('ArrowDown'); await until(() => focusedCell(2, 10));
    assert(!grid.querySelector('input'), 'Explicit ArrowDown commits and moves down');
    key('F2'); assert(grid.querySelector('input')?.value === '7.00', 'Explicit F2 retains current value');
    key('ArrowUp'); await until(() => focusedCell(1, 10));
    assert(!grid.querySelector('input'), 'Explicit ArrowUp commits and moves up');
    key('Enter'); key('Enter'); await until(() => focusedCell(2, 10));
    assert(!grid.querySelector('input'), 'Explicit Enter commits and moves down');
    key('F2'); key('Tab'); await until(() => focusedCell(2, 11));
    assert(!grid.querySelector('input'), 'Explicit Tab commits and moves right');
    key('F2'); key('Tab', { shiftKey: true }); await until(() => focusedCell(2, 10));
    assert(!grid.querySelector('input'), 'Explicit Shift+Tab commits and moves left');

    await choose(2, 11); enter('1');
    const beforePaste = writes.length, pasteData = new DataTransfer();
    pasteData.setData('text/plain', '2.5');
    const paste = new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData: pasteData });
    document.activeElement.dispatchEvent(paste);
    assert(!paste.defaultPrevented && writes.length === beforePaste && grid.querySelector('input')?.value === '1',
      'GB-EDIT-008 — Direct editor paste remains native and starts no grid batch');
    key('Escape');

    await choose(0, 12); enter('6'); key('Tab');
    await until(() => !grid.contains(document.activeElement));
    assert(!grid.querySelector('input'), 'Direct Tab at last component commits and exits grid');
    await choose(0, 10); enter('6'); key('Tab', { shiftKey: true });
    await until(() => !grid.contains(document.activeElement));
    assert(!grid.querySelector('input'), 'Direct Shift+Tab at first component commits and exits grid');
    await until(() => !grid.querySelector('[data-pp5-saving]'));

    output.textContent = `PASS: ${checks.length} SYNTHETIC direct-entry checks\n` + checks.join('\n');
    document.title = `PASS ${checks.length} — synthetic direct entry`;
  } catch (error) {
    output.textContent = `FAIL after ${checks.length}: ${error.message}\n` + checks.join('\n');
    document.title = 'FAIL — synthetic direct entry';
  } finally { window.fetch = originalFetch; }
})();
