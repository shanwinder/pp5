(async () => {
  'use strict';
  const output = document.getElementById('browser-results'), checks = [], requests = [];
  const assert = (condition, label) => { if (!condition) throw new Error(label); checks.push(label); };
  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
  const until = async condition => { for (let i = 0; i < 150; i++) { if (condition()) return; await sleep(20); } throw new Error('Timed out'); };
  const grid = document.getElementById('gradebook-tabulator');
  const rows = () => [...grid.querySelectorAll('.tabulator-row')];
  const cell = (row, field) => rows()[row]?.querySelector(`[tabulator-field="${field}"]`);
  const choose = target => { target.click(); target.focus(); };
  const key = (target, value, options = {}) => target.dispatchEvent(new KeyboardEvent('keydown', { key: value, bubbles: true, cancelable: true, ...options }));
  const paste = (target, value) => { const data = new DataTransfer(); data.setData('text/plain', value);
    const event = new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true }); target.dispatchEvent(event); return event; };
  const originalFetch = window.fetch;
  window.fetch = (...args) => { requests.push({ url: String(args[0]), body: args[1]?.body }); return originalFetch(...args); };
  try {
    await until(() => document.querySelector('.pp5-gradebook').hidden && rows().length >= 4);
    if (new URLSearchParams(location.search).get('mode') === 'large') {
      choose(cell(0, 'score_10'));
      const tsv = Array.from({ length: 2001 }, () => '5').join('\n');
      assert(paste(cell(0, 'score_10'), tsv).defaultPrevented, 'Oversize paste intercepted');
      assert(requests.length === 0, '2,001 cells rejected before server');
      assert(document.getElementById('gradebook-batch-status').textContent.includes('2000'), 'Limit feedback visible');
      assert(cell(0, 'score_10').textContent === '', 'Oversize paste does not mutate visible score');
      output.textContent = `PASS: ${checks.length} limit checks\n` + checks.join('\n'); document.title = `PASS ${checks.length} — limit`; return;
    }
    choose(cell(0, 'score_10'));
    key(cell(0, 'score_10'), '5'); key(grid.querySelector('input'), 'ArrowDown');
    await until(() => document.activeElement === cell(1, 'score_10'));
    assert(requests.length === 1, 'First rapid entry submits one single-cell POST');
    assert(paste(cell(1, 'score_10'), '9').defaultPrevented, 'Pending save blocks paste command');
    assert(requests.length === 1 && document.getElementById('gradebook-batch-status').textContent.includes('รอการบันทึก'), 'No batch races pending single save');
    key(cell(1, 'score_10'), '6'); key(grid.querySelector('input'), 'ArrowDown');
    await until(() => document.activeElement === cell(2, 'score_10'));
    key(cell(2, 'score_10'), '7'); key(grid.querySelector('input'), 'ArrowDown');
    await until(() => requests.length === 3 && cell(0, 'score_10').textContent === '5.00'
      && !grid.querySelector('[data-pp5-saving]'));
    assert(requests.length === 3 && requests.every(r => r.url.includes('/components/10/enrollments/')), 'Rapid vertical entry sends exactly three single writes');
    assert(requests.map(r => r.url.match(/enrollments\/(\d+)/)?.[1]).join(',') === '1,2,3', 'Rapid writes stay in roster order with stable IDs');
    assert(document.activeElement === cell(2, 'score_10'), 'Late responses do not steal newest focus');
    assert(cell(0, 'score_10').textContent === '5.00' && cell(1, 'score_10').textContent === '6.00', 'Queued responses reconcile independently');
    choose(cell(2, 'score_10'));
    const oneStart = requests.length;
    key(cell(2, 'score_10'), 'Delete');
    await until(() => requests.length === oneStart + 1 && cell(2, 'score_10').textContent === '');
    assert(requests.at(-1).url.endsWith('/scores/batch'), '1 by 1 selected Delete uses batch endpoint');
    assert(JSON.stringify(JSON.parse(requests.at(-1).body.get('batch')).values) === '[[""]]', '1 by 1 clear sends explicit blank');
    choose(cell(2, 'score_10'));
    const backspaceStart = requests.length;
    key(cell(2, 'score_10'), 'Backspace');
    await until(() => requests.length === backspaceStart + 1 && !grid.hasAttribute('aria-busy'));
    assert(requests.at(-1).url.endsWith('/scores/batch'), 'Selected Backspace also uses batch endpoint');
    choose(cell(1, 'score_10')); key(cell(1, 'score_10'), 'Enter');
    const editor = grid.querySelector('input'), editorStart = requests.length;
    assert(editor?.value === '6.00', 'Explicit edit preserves existing value');
    assert(!key(editor, 'Backspace').defaultPrevented && requests.length === editorStart, 'Editor Backspace remains text editing');
    assert(paste(editor, '5').defaultPrevented && requests.length === editorStart, 'Paste while editor active cannot start batch');
    key(editor, 'Escape');
    assert(requests.length === editorStart && cell(1, 'score_10').textContent === '6.00', 'Escape after editor paste sends no write');
    key(cell(1, 'score_10'), 'ArrowUp');
    assert(document.activeElement === cell(0, 'score_10'), 'Arrow Up selects previous score row');
    key(cell(0, 'score_10'), 'ArrowDown', { shiftKey: true });
    await until(() => grid.querySelectorAll('.tabulator-range-selected').length >= 2);
    assert(grid.querySelectorAll('.tabulator-range-selected').length >= 2, 'Keyboard range has visible cells');
    choose(cell(1, 'score_10')); key(cell(1, 'score_10'), 'Tab');
    assert(document.activeElement === cell(1, 'score_11'), 'Tab moves to next score component');
    key(cell(1, 'score_11'), 'Tab', { shiftKey: true });
    assert(document.activeElement === cell(1, 'score_10'), 'Shift Tab moves to prior score component');
    choose(cell(1, 'score_11')); key(cell(1, 'score_11'), 'Tab');
    assert(!grid.contains(document.activeElement), 'Tab from last score exits grid');
    output.textContent = `PASS: ${checks.length} race and keyboard checks\n` + checks.join('\n'); document.title = `PASS ${checks.length} — races`;
  } catch (error) {
    output.textContent = `FAIL after ${checks.length}: ${error.message}\n` + checks.join('\n'); document.title = 'FAIL — races';
  } finally { window.fetch = originalFetch; }
})();
