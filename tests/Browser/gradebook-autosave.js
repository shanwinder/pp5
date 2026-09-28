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
    const live = first.closest('[data-score-cell]').querySelector('[role="status"]');
    const observer = new MutationObserver(() => {});
    observer.observe(live, {childList:true, characterData:true, subtree:true});
    for (const value of ['1','12','12.','12.5']) { edit(first, value); key(first, value.slice(-1)); }
    assert(requests.length === 0, 'Typing does not POST');
    const announcements = observer.takeRecords(); observer.disconnect();
    assert(announcements.length === 1, 'Repeated keystrokes do not repeat identical live announcements');
    key(first, 'Enter');
    assert(document.activeElement === get(2), 'Enter moves down in the same component');
    assert(state(first) === 'saving' && first.readOnly, 'Saving is visible and the in-flight value is frozen');
    await until(() => get() !== first);
    assert(requests.length === 1, 'Enter causes exactly one blur POST');
    assert(get().value === '12.50' && state(get()) === 'saved', 'Server-normalized value and saved state replace the cell');
    assert(document.activeElement === get(2), 'Saved fragment does not steal focus');
    assert(document.getElementById('row-1-1-total').textContent === '19.25', 'OOB summary uses the server value');
    assert(requests[0].keys.join(',') === '_token,score', 'Request body contains only score and CSRF');

    assert(document.querySelectorAll('[data-active-cell="true"]').length === 1 && get(2).closest('[data-score-cell]').dataset.activeCell === 'true', 'Exactly one logical cell is active');
    const activeStyle = get(2).ownerDocument.defaultView.getComputedStyle(get(2).closest('[data-score-cell]'));
    assert(activeStyle.outlineStyle !== 'none' && parseFloat(activeStyle.outlineWidth) > 0, 'Active cell has a non-color outline');
    assert(!key(get(2), 'Tab').defaultPrevented, 'Tab is not intercepted');
    assert(!key(get(2), 'Tab', { shiftKey: true }).defaultPrevented, 'Shift+Tab is not intercepted');
    for (const arrow of ['ArrowLeft','ArrowRight']) assert(!key(get(2), arrow).defaultPrevented, `${arrow} preserves text caret navigation`);
    for (const modifier of [{ctrlKey:true},{metaKey:true},{altKey:true}]) {
      const before = document.activeElement;
      assert(!key(get(2), 'ArrowDown', modifier).defaultPrevented && document.activeElement === before, 'Modified arrow remains a browser shortcut');
    }
    const composingInput = get(2);
    composingInput.dispatchEvent(new CompositionEvent('compositionstart', { bubbles: true }));
    assert(!key(composingInput, 'Enter').defaultPrevented && !key(composingInput, 'ArrowDown').defaultPrevented && document.activeElement === composingInput, 'IME composition does not move or submit');
    composingInput.dispatchEvent(new CompositionEvent('compositionend', { bubbles: true }));
    assert(!key(get(2), 'Enter', { isComposing: true }).defaultPrevented, 'IME-marked key event does not submit');
    assert(!key(get(2), 'ArrowDown', { shiftKey: true }).defaultPrevented, 'Shift+arrow does not create a range');
    assert(!key(get(2), 'Enter', { repeat: true }).defaultPrevented, 'Key repeat cannot queue a second Enter save');

    const beforeShift = requests.length;
    assert(key(get(2), 'Enter', { shiftKey: true }).defaultPrevented && document.activeElement === get(1), 'Shift+Enter moves to the prior current row in the same component');
    await until(() => requests.length > beforeShift && active === 0);
    assert(document.activeElement === get(1) && document.querySelectorAll('[data-active-cell="true"]').length === 1, 'Older response cannot steal focus from the new active cell');
    assert(!composingInput.isConnected && get(2) !== composingInput, 'Active identity survives replacement without retaining the old input');
    assert(key(get(1), 'ArrowUp').defaultPrevented && document.activeElement === get(1), 'ArrowUp at first row does not wrap');
    assert(key(get(1), 'Enter', { shiftKey: true }).defaultPrevented && document.activeElement !== get(1), 'Shift+Enter at first row blurs without wrapping');
    await until(() => active === 0);
    get(2).focus();
    const beforeDown = get(2);
    assert(key(get(2), 'ArrowDown').defaultPrevented && document.activeElement === get(3), 'ArrowDown moves to next current row');
    assert(key(get(3), 'ArrowDown').defaultPrevented && document.activeElement === get(3), 'ArrowDown at last current row does not enter history');
    await until(() => get(2) !== beforeDown && active === 0);
    assert(key(get(3), 'ArrowUp').defaultPrevented && document.activeElement === get(2), 'ArrowUp moves to prior current row');
    await until(() => active === 0);
    assert(!key(get(2), 'ArrowUp', { isComposing: true }).defaultPrevented, 'IME-marked arrow stays native');

    const priorSecond = get(2);
    get(1).focus();
    await until(() => get(2) !== priorSecond && active === 0);
    const rapidStart = requests.length;
    assert(key(get(1), 'Enter').defaultPrevented && document.activeElement === get(2), 'Rapid Enter first step moves by logical row');
    assert(key(get(2), 'Enter').defaultPrevented && document.activeElement === get(3), 'Rapid Enter second step keeps newest focus');
    await until(() => requests.length - rapidStart === 2 && active === 0);
    assert(document.activeElement === get(3), 'Queued Enter responses do not reclaim focus');
    document.querySelector('details summary').focus();
    assert(document.querySelectorAll('[data-active-cell="true"]').length === 0, 'Leaving score inputs clears active styling');

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

    for (const value of ['20.01','1.234','<img src=x onerror=alert(1)>','csrf','revoked','failure','login']) {
      const input = get(); edit(input, value); input.blur();
      await until(() => state(input) === 'error');
      assert(input.value === value && !input.readOnly, `${value}: error retains the exact editable input`);
      assert(!input.closest('[data-score-cell]').textContent.includes('บันทึกแล้ว'), `${value}: no false saved state`);
      assert(input.getAttribute('aria-invalid') === 'true' && document.getElementById(input.getAttribute('aria-describedby')).textContent.includes('ผิดพลาด'), `${value}: error is associated with the input`);
      assert(document.getElementById('row-1-1-total').textContent === '19.25', `${value}: failed save does not change server total`);
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
    const returning = get(); edit(returning, '5'); returning.blur(); returning.focus();
    await until(() => get() !== returning);
    await until(() => document.activeElement === get());
    assert(get().value === '5.00' && document.querySelectorAll('[data-active-cell="true"]').length === 1, 'Focused in-flight logical cell restores focus to server replacement');
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
