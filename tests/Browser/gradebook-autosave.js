(async () => {
  'use strict';
  const output = document.getElementById('browser-results');
  const checks = [];
  const requests = [];
  let active = 0;
  let maxActive = 0;
  const get = (row = 1, component = 10) => document.getElementById(`score-1-${component}-${row}-input`);
  const assert = (ok, message) => { if (!ok) throw new Error(message); checks.push(message); };
  const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
  const until = async condition => {
    for (let attempt = 0; attempt < 150; attempt++) { if (condition()) return; await wait(20); }
    throw new Error('Timed out waiting for browser state');
  };
  const state = input => input.closest('[data-score-cell]').dataset.saveState;
  const edit = (input, value) => { input.focus(); input.value = value; input.dispatchEvent(new Event('input', { bubbles: true })); };
  const key = (input, value, options = {}) => {
    const event = new KeyboardEvent('keydown', { key: value, bubbles: true, cancelable: true, ...options });
    input.dispatchEvent(event); return event;
  };
  document.addEventListener('htmx:beforeRequest', event => {
    requests.push({ score: event.detail.requestConfig.parameters.score, keys: Object.keys(event.detail.requestConfig.parameters).sort(), xhr: event.detail.xhr });
    active++; maxActive = Math.max(maxActive, active);
  });
  document.addEventListener('htmx:afterRequest', () => { active--; });

  try {
    // Let HTMX process the server-rendered document first.
    await wait(50);
    const first = get(); first.focus();
    for (const value of ['1','12','12.','12.5']) { edit(first, value); key(first, value.slice(-1)); }
    assert(requests.length === 0, 'Typing does not POST');
    key(first, 'Enter');
    assert(document.activeElement === get(2), 'Enter moves down in the same component');
    assert(state(first) === 'saving' && first.readOnly, 'Saving is visible and the in-flight value is frozen');
    await until(() => get() !== first);
    assert(requests.length === 1, 'Enter causes exactly one blur POST');
    assert(get().value === '12.50' && state(get()) === 'saved', 'Server-normalized value and saved state replace the cell');
    assert(document.activeElement === get(2), 'Saved fragment does not steal focus');
    assert(document.getElementById('row-1-1-total').textContent === '19.25', 'OOB summary uses the server value');
    assert(requests[0].keys.join(',') === '_token,score', 'Request body contains only score and CSRF');

    assert(!key(get(2), 'Tab').defaultPrevented, 'Tab is not intercepted');
    assert(!key(get(2), 'Tab', { shiftKey: true }).defaultPrevented, 'Shift+Tab is not intercepted');
    assert(!key(get(2), 'ArrowLeft').defaultPrevented && !key(get(2), 'ArrowRight').defaultPrevented, 'Caret arrows are not intercepted');
    assert(!key(get(2), 'Enter', { isComposing: true }).defaultPrevented, 'IME composition does not submit');

    // Repeated blur on a queued or in-flight field must not stall later cells.
    get(2).blur(); await until(() => active === 0); await wait(30);
    const start = requests.length;
    const a = get(1), b = get(2), c = get(3);
    edit(a, '5'); b.focus();
    edit(b, '6'); a.focus(); b.focus(); c.focus();
    edit(c, '01.50'); c.blur();
    await until(() => get(1) !== a && get(2) !== b && get(3) !== c);
    assert(requests.length - start === 3, 'Repeated blur does not duplicate saves or stall the queue');
    assert(maxActive === 1, 'Table requests are serialized');
    assert(get(1).value === '5.00' && get(2).value === '6.00' && get(3).value === '1.50', 'Queued cells keep their intended values');

    for (const value of ['<img src=x onerror=alert(1)>','csrf','failure','login']) {
      const input = get(); edit(input, value); input.blur();
      await until(() => state(input) === 'error');
      assert(input.value === value && !input.readOnly, `${value}: error retains the exact editable input`);
      assert(!input.closest('[data-score-cell]').textContent.includes('บันทึกแล้ว'), `${value}: no false saved state`);
      assert(!document.querySelector('img'), `${value}: no injected HTML`);
    }
    // Retry without changing the invalid value must still produce a request.
    const retryStart = requests.length; get().focus(); get().blur();
    await until(() => requests.length > retryStart && active === 0);
    assert(state(get()) === 'error', 'Unchanged failed value can be retried');

    const aborted = get(); edit(aborted, '5'); aborted.blur();
    requests[requests.length - 1].xhr.abort();
    await until(() => state(aborted) === 'error');
    assert(!aborted.readOnly && aborted.value === '5', 'Transport abort restores editable input with an error');

    for (const [value, normalized] of [['0','0.00'], ['', '']]) {
      const input = get(); edit(input, value); input.blur(); await until(() => get() !== input);
      assert(get().value === normalized && state(get()) === 'saved', `Saved ${JSON.stringify(value)} displays ${JSON.stringify(normalized)}`);
    }
    const last = get(3); edit(last, '5'); key(last, 'Enter');
    assert(document.activeElement !== last, 'Enter on the last writable row blurs and skips historical rows');
    await until(() => get(3) !== last);
    assert(!document.querySelector('tr[data-enrollment-id="4"] input'), 'Historical row has no editable cells');
    output.textContent = `PASS: ${checks.length} browser assertions\n` + checks.join('\n');
    document.title = `PASS ${checks.length} — Gradebook browser tests`;
  } catch (error) {
    output.textContent = `FAIL after ${checks.length} checks: ${error.message}\n` + checks.join('\n');
    document.title = 'FAIL — Gradebook browser tests';
  }
})();
