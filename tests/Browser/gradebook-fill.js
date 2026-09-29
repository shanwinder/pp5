(async () => {
  'use strict';
  const output = document.getElementById('browser-results');
  const checks = [], batches = [], singles = [];
  const assert = (ok, name) => { if (!ok) throw new Error(name); checks.push(name); };
  const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
  const until = async condition => {
    for (let i = 0; i < 200; i++) { if (condition()) return; await wait(20); }
    throw new Error('Timed out waiting for range fill');
  };
  const grid = document.querySelector('.pp5-gradebook');
  const form = document.getElementById('gradebook-range-actions');
  const scalar = document.getElementById('gradebook-fill-value');
  const fill = document.getElementById('gradebook-fill-submit');
  const clear = document.getElementById('gradebook-clear-submit');
  const summary = document.getElementById('gradebook-range-summary');
  const status = document.getElementById('gradebook-batch-status');
  const input = (row, column = 10) => document.getElementById(`score-1-${column}-${row}-input`);
  const td = (row, column = 0) => document.querySelector(`[data-grid-row="${row}"][data-grid-column="${column}"]`);
  const selected = () => [...document.querySelectorAll('[data-grid-selected="true"]')];
  const key = (target, value, options = {}) => {
    const event = new KeyboardEvent('keydown', { key: value, bubbles: true, cancelable: true, ...options });
    target.dispatchEvent(event); return event;
  };
  const submit = value => { scalar.value = value; form.requestSubmit(); };
  const complete = async () => { await until(() => !grid.hasAttribute('aria-busy')); await wait(20); };
  const drag = (start, end) => {
    const down = new PointerEvent('pointerdown', { bubbles: true, cancelable: true, pointerType: 'mouse',
      isPrimary: true, button: 0, buttons: 1, pointerId: 7 });
    start.dispatchEvent(down);
    end.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    const box = end.getBoundingClientRect();
    document.dispatchEvent(new PointerEvent('pointermove', { bubbles: true, pointerType: 'mouse', isPrimary: true,
      pointerId: 7, buttons: 1, clientX: box.left + 3, clientY: box.top + 3 }));
    document.dispatchEvent(new PointerEvent('pointerup', { bubbles: true, pointerType: 'mouse', isPrimary: true,
      pointerId: 7, button: 0 }));
  };
  const originalFetch = window.fetch;
  let mock = null;
  window.fetch = async (url, options) => {
    const matrix = JSON.parse(options.body.get('batch'));
    batches.push({ url, matrix });
    if (mock === 'mixed-max' || mock === 'denied') {
      const kind = mock; mock = null;
      return new Response(JSON.stringify({ committed: false,
        message: kind === 'mixed-max' ? 'นักเรียนทดสอบ 1 · งาน คะแนนเกินคะแนนเต็ม 10.00' : 'ไม่มีสิทธิ์บันทึกคะแนน',
        ...(kind === 'mixed-max' ? { location: { enrollment_id: 1, component_id: 10 } } : {}) }),
      { status: 422, headers: { 'Content-Type': 'application/json' } });
    }
    return originalFetch(url, options);
  };
  document.addEventListener('htmx:beforeRequest', event => singles.push(event.detail.xhr));
  try {
    await wait(50);
    if (!form) {
      assert(!grid.dataset.batchUrl, 'Read-only page has no write URL');
      assert(!document.querySelector('#gradebook-fill-value,#gradebook-fill-submit,#gradebook-clear-submit'),
        'Read-only page has no fill or clear controls');
      drag(td(3), td(3));
      assert(selected().length === 1, 'Read-only historical cell still selects for copy');
      assert(batches.length === 0, 'Read-only selection cannot write');
      output.textContent = `PASS: ${checks.length} fill assertions\n` + checks.join('\n'); return;
    }
    assert(fill.disabled && clear.disabled && summary.textContent.includes('ยังไม่ได้เลือก'), 'No implicit target from active cell');
    input(1).focus();
    key(input(1), 'ArrowDown', { shiftKey: true }); key(input(1), 'ArrowDown', { shiftKey: true });
    assert(selected().length === 3 && summary.textContent.includes('3 แถว × 1') && !fill.disabled,
      'Keyboard Shift+Down creates explicit editable vertical range');
    scalar.focus();
    assert(selected().length === 3 && document.activeElement === scalar, 'Range survives focus in fill controls');
    const noSelectionWrite = batches.length;
    submit('');
    assert(batches.length === noSelectionWrite && status.textContent.includes('กรุณากรอกคะแนน'),
      'Empty scalar never becomes mass clear');
    submit('0');
    assert(batches.length === noSelectionWrite + 1, 'Zero submits one batch');
    assert(fill.disabled && clear.disabled && grid.getAttribute('aria-busy') === 'true',
      'Pending batch disables range actions and exposes busy state');
    form.requestSubmit(); clear.click();
    assert(batches.length === noSelectionWrite + 1, 'Pending batch does not queue duplicate fill or clear');
    assert(JSON.stringify(batches.at(-1).matrix.values) === '[["0"],["0"],["0"]]'
      && JSON.stringify(batches.at(-1).matrix.enrollment_ids) === '[1,2,3]'
      && JSON.stringify(batches.at(-1).matrix.component_ids) === '[10]',
    'Keyboard range maps visual rows to stable IDs with literal zero');
    await complete();
    assert(input(1).value === '0.00' && input(2).value === '0.00' && input(3).value === '0.00',
      'Server normalizes zero without converting it to blank');
    assert(status.textContent.includes('ใส่คะแนน 3 ช่องแล้ว') && status.textContent.includes('เปลี่ยนแปลง 3 ช่อง'),
      'Fill success names the action and authoritative changed count');
    assert(selected().length === 3 && scalar.value === '0' && !fill.disabled, 'Range and scalar remain for deliberate repeat');
    scalar.dispatchEvent(new CompositionEvent('compositionstart', { bubbles: true }));
    const compositionCount = batches.length;
    assert(key(scalar, 'Enter', { isComposing: true }).defaultPrevented, 'IME Enter is not a submit shortcut');
    submit('7');
    assert(batches.length === compositionCount, 'Composition cannot submit fill');
    scalar.dispatchEvent(new CompositionEvent('compositionend', { bubbles: true }));
    assert(document.getElementById('row-1-1-total').textContent === '987.65', 'Server totals remain authoritative');
    scalar.focus(); submit('5.5'); await complete();
    assert(input(1).value === '5.50' && input(3).value === '5.50', 'Decimal normalization comes from batch response');
    const countAfterDecimal = batches.length;
    submit('12,5'); await complete();
    assert(batches.length === countAfterDecimal + 1 && status.dataset.batchState === 'error'
      && input(1).value === '5.50' && scalar.value === '12,5' && selected().length === 3,
    'Invalid literal rejects atomically and retains value and range');
    const beforeClear = batches.length;
    clear.focus(); clear.click();
    assert(batches.length === beforeClear + 1 && JSON.stringify(batches.at(-1).matrix.values) === '[[""],[""],[""]]',
      'Explicit clear uses one all-blank string matrix');
    await complete();
    assert(input(1).value === '' && input(2).value === '' && input(3).value === '', 'Clear displays authoritative NULL as blank');
    assert(status.textContent.includes('ล้างคะแนน 3 ช่องแล้ว') && status.textContent.includes('เปลี่ยนแปลง 3 ช่อง'),
      'Clear success names the action and authoritative changed count');
    assert(clear.textContent.includes('3 ช่อง'), 'Clear action communicates its scope');
    const beforeCopy = new DataTransfer();
    input(1).focus(); document.dispatchEvent(new ClipboardEvent('copy', { bubbles: true, cancelable: true, clipboardData: beforeCopy }));
    assert(beforeCopy.getData('text/plain') === '\n\n', 'Copy after clear reflects server values');
    // Pointer rectangle crosses both columns and retains selection when the scalar receives focus.
    drag(td(0, 0), td(1, 1));
    assert(selected().length === 4 && summary.textContent.includes('2 แถว × 2'), 'Pointer drag selects a 2×2 rectangle');
    scalar.focus(); assert(selected().length === 4, 'Pointer selection survives focus in panel');
    const beforePointer = batches.length;
    submit('5');
    assert(batches.length === beforePointer + 1 && JSON.stringify(batches.at(-1).matrix.values) === '[["5","5"],["5","5"]]'
      && JSON.stringify(batches.at(-1).matrix.component_ids) === '[10,11]', 'Pointer fill is one row-major batch');
    await complete();
    assert(input(1).value === '5.00' && input(2, 11).value === '5.00', 'Pointer fill uses authoritative response');
    const copied = new DataTransfer();
    input(1).focus(); document.dispatchEvent(new ClipboardEvent('copy', { bubbles: true, cancelable: true, clipboardData: copied }));
    assert(copied.getData('text/plain') === '5.00\t5.00\n5.00\t5.00', 'Copy after fill uses canonical server values');
    const prior = [input(1).value, input(1, 11).value];
    mock = 'mixed-max'; submit('15'); await complete();
    assert(status.textContent.includes('งาน') && status.textContent.includes('10.00')
      && input(1).value === prior[0] && input(1, 11).value === prior[1],
    'Mixed maximum error is shown and no column is changed locally');
    mock = 'denied'; submit('6'); await complete();
    assert(status.textContent.includes('ไม่มีสิทธิ์') && input(1).value === prior[0], 'Live revocation keeps authoritative cells');
    // Changing the selection after typing must change the command target.
    scalar.value = '6'; drag(td(1, 0), td(2, 0)); scalar.focus();
    const beforeChange = batches.length;
    form.requestSubmit();
    assert(batches.length === beforeChange + 1 && JSON.stringify(batches.at(-1).matrix.enrollment_ids) === '[2,3]',
      'Submission resolves the current range, not an earlier snapshot');
    await complete();
    const beforeHistory = batches.length;
    drag(td(3, 0), td(3, 1));
    assert(fill.disabled && clear.disabled && summary.textContent.includes('ประวัติ') && batches.length === beforeHistory,
      'Historical-only selection remains selectable but cannot fill');
    drag(td(2, 0), td(3, 0));
    assert(fill.disabled && clear.disabled && summary.textContent.includes('ประวัติ'),
      'Mixed current and historical selection is wholly unwritable');
    drag(td(0, 0), td(1, 0));
    input(2).disabled = true;
    form.requestSubmit();
    assert(batches.length === beforeHistory && fill.disabled, 'Disabled target blocks whole range at submit');
    input(2).disabled = false;
    scalar.focus();
    document.querySelector('.pp5-actions a').focus();
    assert(selected().length === 0 && fill.disabled && clear.disabled, 'Unrelated page focus clears range and actions');
    const noRangeCount = batches.length; form.requestSubmit(); clear.click();
    assert(batches.length === noRangeCount, 'Cleared range cannot use cached targets');
    input(1).focus(); key(input(1), 'ArrowDown', { shiftKey: true }); scalar.focus();
    scalar.value = '<img src=x onerror=alert(1)>'; form.requestSubmit(); await complete();
    assert(!document.querySelector('img') && status.dataset.batchState === 'error', 'Hostile scalar remains text and cannot execute');
    // A dirty single-cell edit keeps its own blur save ownership before range fill may start.
    input(1).focus(); input(1).value = '6'; input(1).dispatchEvent(new Event('input', { bubbles: true }));
    scalar.focus();
    const blockedAt = batches.length;
    submit('5');
    assert(batches.length === blockedAt && status.textContent.includes('รอการบันทึก'),
      'Pending single-cell save blocks fill rather than racing it');
    await until(() => ![...grid.querySelectorAll('[data-score-input]')].some(node => node.readOnly));
    await wait(20);
    form.requestSubmit(); await complete();
    assert(batches.length === blockedAt + 1, 'Teacher may retry fill after single-cell save settles');
    const clipboard = new DataTransfer(); clipboard.setData('text/plain', '3');
    input(1).focus();
    input(1).dispatchEvent(new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData: clipboard }));
    await complete();
    assert(batches.length === blockedAt + 2 && input(1).value === '3.00', 'Task 8 paste remains available after fill');
    key(input(1), 'Enter');
    assert(document.activeElement === input(2), 'Task 6 Enter navigation remains after fill');
    const beforeStale = singles.length;
    input(1).focus(); input(1).blur(); await wait(200);
    assert(singles.length === beforeStale, 'Committed batch suppresses stale blur');
    output.textContent = `PASS: ${checks.length} fill assertions\n` + checks.join('\n');
  } catch (error) {
    output.textContent = `FAIL after ${checks.length}: ${error.message}\n` + checks.join('\n');
  }
})();
