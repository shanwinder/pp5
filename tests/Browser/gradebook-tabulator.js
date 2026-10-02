(async () => {
  'use strict';
  const output = document.getElementById('browser-results');
  if (!output) return;
  const checks = [], requests = [];
  const assert = (condition, label) => { if (!condition) throw new Error(label); checks.push(label); };
  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
  const until = async condition => {
    for (let i = 0; i < 150; i++) { if (condition()) return; await sleep(20); }
    throw new Error('Timed out waiting for Gradebook result');
  };
  const grid = document.getElementById('gradebook-tabulator');
  const fallback = document.querySelector('.pp5-gradebook');
  const status = document.getElementById('gradebook-batch-status');
  const rows = () => [...grid.querySelectorAll('.tabulator-row')];
  const cell = (row, field) => rows()[row]?.querySelector(`[tabulator-field="${field}"]`);
  const key = (target, value, options = {}) => {
    const event = new KeyboardEvent('keydown', { key: value, bubbles: true, cancelable: true, ...options });
    target.dispatchEvent(event); return event;
  };
  const choose = async target => {
    const box = target.getBoundingClientRect();
    target.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true,
      clientX: box.left + box.width / 2, clientY: box.top + box.height / 2 }));
    await until(() => document.activeElement === target);
    assert(document.activeElement === target && grid.querySelectorAll('.tabulator-cell[tabindex="0"]').length === 1,
      'Click event without manual focus establishes one active grid cell');
  };
  const geometry = target => {
    const holder = grid.querySelector('.tabulator-tableholder');
    return { height: target.closest('.tabulator-row').getBoundingClientRect().height,
      width: target.getBoundingClientRect().width, left: holder.scrollLeft, top: holder.scrollTop };
  };
  const stableGeometry = (before, target) => {
    const after = geometry(target);
    return Math.abs(after.height - before.height) <= 0.5 && Math.abs(after.width - before.width) <= 0.5
      && Math.abs(after.left - before.left) <= 0.5 && Math.abs(after.top - before.top) <= 0.5;
  };
  const editorPresentation = target => {
    const input = target.querySelector('input'), cellBox = target.getBoundingClientRect();
    if (!input) return false;
    const inputBox = input.getBoundingClientRect(), inputStyle = getComputedStyle(input), cellStyle = getComputedStyle(target);
    return grid.querySelectorAll('.tabulator-cell input').length === 1 && fallback.hidden && !grid.hidden
      && inputBox.left >= cellBox.left - 0.5 && inputBox.right <= cellBox.right + 0.5
      && inputBox.top >= cellBox.top - 0.5 && inputBox.bottom <= cellBox.bottom + 0.5
      && inputStyle.borderTopWidth === '0px' && inputStyle.outlineStyle === 'none'
      && inputStyle.marginLeft === '0px' && inputStyle.boxSizing === 'border-box'
      && cellStyle.outlineStyle !== 'none' && cellStyle.boxShadow === 'none';
  };
  const clipboard = (target, type, value = '') => {
    const data = new DataTransfer();
    if (type === 'paste') data.setData('text/plain', value);
    const event = new ClipboardEvent(type, { bubbles: true, cancelable: true, clipboardData: data });
    target.dispatchEvent(event); return { event, text: data.getData('text/plain') };
  };
  const originalFetch = window.fetch;
  window.fetch = (...args) => { requests.push({ url: String(args[0]), body: args[1]?.body }); return originalFetch(...args); };
  try {
    await until(() => fallback.hidden && rows().length >= 4);
    assert(grid.hidden === false && fallback.hidden, 'Tabulator is visible and semantic fallback is hidden after build');
    assert([...fallback.querySelectorAll('input[data-score-input]')].every(input => input.disabled), 'Hidden fallback cannot write twice');
    assert(rows().length === 4, 'Current and historical roster rows are rendered');
    assert(cell(0, 'score_10').textContent === '' && cell(0, 'score_11').textContent === '0.00', 'Blank and zero are distinct');
    assert(cell(3, 'rowType').textContent.includes('ประวัติ'), 'Historical state is visible');
    assert(document.querySelectorAll('script[src^="http"],link[href^="http"]').length === 0, 'Runtime assets are local');
    assert(!document.body.textContent.includes('national_id'), 'No national ID appears in the page');
    assert(cell(0, 'total').textContent === '0.00', 'Initial summary comes from read model');
    if (!document.getElementById('gradebook-csrf')) {
      assert(!document.querySelector('#gradebook-tabulator .tabulator-editable'), 'Read-only page has no score editor');
      await choose(cell(0, 'score_10'));
      assert(!key(cell(0, 'score_10'), '5').defaultPrevented && !grid.querySelector('input'), 'Read-only cell rejects type to edit');
      clipboard(cell(0, 'score_10'), 'paste', '5'); key(cell(0, 'score_10'), 'Delete');
      assert(requests.length === 0, 'Read-only paste and clear make no request');
      output.textContent = `PASS: ${checks.length} read-only Tabulator checks\n` + checks.join('\n');
      document.title = `PASS ${checks.length} — read-only Tabulator`;
      return;
    }
    assert(document.querySelectorAll('#gradebook-tabulator .tabulator-editable').length >= 6, 'Current score cells are editable');
    assert(!cell(3, 'score_10').classList.contains('tabulator-editable'), 'Historical score is not editable');
    await choose(cell(0, 'score_10'));
    const blankGeometry = geometry(cell(0, 'score_10'));
    assert(key(cell(0, 'score_10'), '5').defaultPrevented, 'Printable key starts replace edit');
    const editor = grid.querySelector('input');
    assert(editor?.value === '5', 'Type to edit starts with only the typed value');
    assert(editorPresentation(cell(0, 'score_10')), 'Editor fills one cell without a nested border or duplicate focus boundary');
    assert(stableGeometry(blankGeometry, cell(0, 'score_10')), 'Blank editor preserves row, column, and internal scroll geometry');
    editor.dispatchEvent(new CompositionEvent('compositionstart', { bubbles: true }));
    assert(!key(editor, 'Enter').defaultPrevented && grid.querySelector('input') === editor, 'IME Enter does not commit');
    editor.dispatchEvent(new CompositionEvent('compositionend', { bubbles: true }));
    assert(key(editor, 'Enter').defaultPrevented, 'Enter commits and moves down');
    await until(() => document.activeElement === cell(1, 'score_10'));
    assert(!grid.querySelector('.tabulator-cell input') && fallback.hidden, 'Commit removes editor and leaves fallback hidden');
    assert(document.activeElement === cell(1, 'score_10'), 'Enter focuses next enrollment in same component');
    await until(() => cell(0, 'score_10').textContent === '5.00');
    assert(requests.length === 1 && requests[0].url.endsWith('/components/10/enrollments/1/score'), 'Single edit uses stable component and enrollment IDs');
    assert(cell(0, 'total').textContent === '19.25', 'Single summary uses authoritative response');
    await choose(cell(1, 'score_10')); key(cell(1, 'score_10'), 'Enter');
    const caret = grid.querySelector('input');
    assert(caret.value === '1.50', 'Enter opens the existing score for caret editing');
    assert(!key(caret, 'ArrowLeft').defaultPrevented && !key(caret, 'ArrowRight').defaultPrevented,
      'Explicit-edit Left and Right remain caret keys');
    assert(editorPresentation(cell(1, 'score_10')), 'Existing-value editor has the same single-cell presentation');
    key(caret, 'Escape');
    assert(!grid.querySelector('input') && cell(1, 'score_10').textContent === '1.50', 'Escape cancels edit without changing value');
    assert(fallback.hidden, 'Escape does not reveal the fallback');
    const afterCancel = requests.length;
    await sleep(180); assert(requests.length === afterCancel, 'Escape sends no write');
    await choose(cell(0, 'score_10')); key(cell(0, 'score_10'), 'x'); key(grid.querySelector('input'), 'Enter');
    await until(() => cell(0, 'score_10').dataset.pp5Error === 'true');
    assert(cell(0, 'score_10').textContent === 'x', 'Invalid typed text remains visible');
    assert(!grid.querySelector('.tabulator-cell input') && fallback.hidden
      && getComputedStyle(cell(0, 'score_10')).boxShadow.includes('2px inset'), 'Rejected value has a visible error boundary and no stale editor');
    await choose(cell(0, 'score_10'));
    assert(getComputedStyle(cell(0, 'score_10')).outlineStyle === 'dashed'
      && getComputedStyle(cell(0, 'score_10')).boxShadow === 'none', 'Active rejected cell has one dashed error boundary');
    assert(cell(0, 'total').textContent === '19.25', 'Invalid score does not change authoritative summary');
    assert(status.textContent.includes('คะแนนไม่ถูกต้อง'), 'Validation error is announced');
    await choose(cell(0, 'score_10')); key(cell(0, 'score_10'), '0'); key(grid.querySelector('input'), 'Enter');
    await until(() => cell(0, 'score_10').textContent === '0.00' && !cell(0, 'score_10').dataset.pp5Saving);
    assert(cell(0, 'score_10').textContent === '0.00', 'Saved zero is a real score');
    await choose(cell(0, 'score_10')); key(cell(0, 'score_10'), 'Enter');
    const blankEditor = grid.querySelector('input'); blankEditor.value = ''; key(blankEditor, 'Enter');
    await until(() => cell(0, 'score_10').textContent === '' && !cell(0, 'score_10').dataset.pp5Saving);
    assert(cell(0, 'score_10').textContent === '', 'Blank single-cell request reconciles to NULL display');
    await choose(cell(0, 'score_10'));
    const batchStart = requests.length;
    const paste = clipboard(cell(0, 'score_10'), 'paste', '5\t0\n\t12.5');
    assert(paste.event.defaultPrevented, 'Native paste is intercepted before mutation');
    assert(cell(0, 'score_10').textContent === '', 'Paste is not optimistically committed');
    await until(() => cell(1, 'score_11').textContent === '12.50');
    assert(requests.length === batchStart + 1 && requests.at(-1).url.endsWith('/scores/batch'), 'Paste uses one Task 8 batch request');
    const sent = JSON.parse(requests.at(-1).body.get('batch'));
    assert(JSON.stringify(sent.enrollment_ids) === '[1,2]' && JSON.stringify(sent.component_ids) === '[10,11]', 'Batch maps stable IDs');
    assert(JSON.stringify(sent.values) === JSON.stringify([['5','0'],['','12.5']]), 'TSV preserves blank and zero geometry');
    assert(cell(0, 'total').textContent === '987.65' && cell(1, 'total').textContent === '987.65', 'Batch summaries use server values');
    const copied = clipboard(cell(0, 'score_10'), 'copy');
    assert(copied.event.defaultPrevented && copied.text === '5.00\t0.00\n\t12.50', 'Copy emits exact rectangular TSV');
    assert(!copied.text.includes('STUDENT-'), 'Score copy excludes identity');
    const clearStart = requests.length; key(cell(0, 'score_10'), 'Delete');
    await until(() => requests.length === clearStart + 1 && cell(1, 'score_11').textContent === '');
    const clear = JSON.parse(requests.at(-1).body.get('batch'));
    assert(clear.values.length === 2 && clear.values.every(row => row.every(value => value === '')), 'Delete clears full range through batch');
    assert(cell(0, 'score_10').textContent === '' && cell(0, 'score_11').textContent === '', 'Clear reconciles all scores to blank');
    const fillStart = requests.length;
    const fill = document.getElementById('gradebook-fill-value'); fill.value = '5';
    document.getElementById('gradebook-range-actions').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await until(() => requests.length === fillStart + 1 && cell(1, 'score_11').textContent === '5.00');
    assert(JSON.parse(requests.at(-1).body.get('batch')).values.every(row => row.every(value => value === '5')), 'Explicit fill sends a scalar matrix');
    const emptyStart = requests.length; fill.value = '';
    document.getElementById('gradebook-range-actions').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    assert(requests.length === emptyStart && status.textContent.includes('ปุ่มล้างคะแนน'), 'Empty fill cannot implicitly clear');
    await choose(cell(3, 'score_10')); const historyStart = requests.length;
    key(cell(3, 'score_10'), '5'); key(cell(3, 'score_10'), 'Delete'); clipboard(cell(3, 'score_10'), 'paste', '5');
    assert(requests.length === historyStart, 'Historical edit, clear and paste do not write');
    output.textContent = `PASS: ${checks.length} Tabulator checks\n` + checks.join('\n');
    document.title = `PASS ${checks.length} — Tabulator`;
  } catch (error) {
    output.textContent = `FAIL after ${checks.length}: ${error.message}\n` + checks.join('\n');
    document.title = 'FAIL — Tabulator';
  } finally { window.fetch = originalFetch; }
})();
