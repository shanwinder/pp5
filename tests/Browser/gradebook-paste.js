(async () => {
  'use strict';
  const output = document.getElementById('browser-results');
  const checks = [], batches = [], singles = [];
  const assert = (ok, message) => { if (!ok) throw new Error(message); checks.push(message); };
  const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
  const until = async condition => {
    for (let i = 0; i < 200; i++) { if (condition()) return; await wait(20); }
    throw new Error('Timed out waiting for paste state');
  };
  const get = (row = 1, column = 10) => document.getElementById(`score-1-${column}-${row}-input`);
  const status = () => document.getElementById('gradebook-batch-status');
  const grid = document.querySelector('.pp5-gradebook');
  let transport = null;
  const originalFetch = window.fetch;
  window.fetch = async (url, options) => {
    batches.push({url, matrix: JSON.parse(options.body.get('batch')), token: options.body.get('_token')});
    if (transport === 'abort') { transport = null; throw new DOMException('Aborted', 'AbortError'); }
    const response = await originalFetch(url, options);
    if (transport) {
      const mode = transport; transport = null;
      if (mode === 'bad-json') return new Response('{', {status:200,headers:{'Content-Type':'application/json'}});
      if (mode === 'missing-marker') return new Response(await response.text(), {status:200,headers:{'Content-Type':'application/json'}});
      const data = await response.json();
      if (mode === 'duplicate') data.cells[1] = data.cells[0];
      if (mode === 'bad-total') data.rows[0].entered_score_total = '<img src=x>';
      if (mode === 'bad-cell') data.cells[0].score = '<img src=x>';
      if (mode === 'missing-cell') data.cells.pop();
      if (mode === 'missing-dom') get().closest('td').removeChild(get().closest('[data-score-cell]'));
      return new Response(JSON.stringify(data), {status:200,headers:{'Content-Type':'application/json','X-Gradebook-Batch-Saved':'1'}});
    }
    return response;
  };
  document.addEventListener('htmx:beforeRequest', event => singles.push(event.detail.xhr));
  const paste = (text, target = get()) => {
    const clipboardData = new DataTransfer(); clipboardData.setData('text/plain', text);
    const event = new ClipboardEvent('paste', {bubbles:true,cancelable:true,clipboardData});
    target.dispatchEvent(event); return event;
  };
  const copy = () => {
    const clipboardData = new DataTransfer();
    document.dispatchEvent(new ClipboardEvent('copy',{bubbles:true,cancelable:true,clipboardData}));
    return clipboardData.getData('text/plain');
  };
  const idle = () => !grid.hasAttribute('aria-busy');
  const completed = async () => { await until(idle); await wait(25); };
  const key = (target, value, options = {}) => target.dispatchEvent(new KeyboardEvent('keydown',{key:value,bubbles:true,cancelable:true,...options}));
  try {
    await wait(75);
    if (!get()) {
      const before = batches.length;
      assert(!paste('5', grid).defaultPrevented, 'Read-only Gradebook does not intercept paste');
      assert(!status() && !grid.dataset.batchUrl, 'Read-only page has no batch control or write URL');
      assert(batches.length === before, 'Read-only page sends no batch request');
      output.textContent = `PASS: ${checks.length} paste assertions\n` + checks.join('\n'); return;
    }
    get().focus();
    assert(paste('5').defaultPrevented, '1×1 native paste event is intercepted in active score input');
    assert(get().value === '' && get().readOnly, 'Pending paste freezes editing without optimistic value replacement');
    assert(status().textContent.includes('กำลังบันทึก'), 'Pending status is visible and accessible');
    const duplicateBefore = batches.length;
    paste('6'); assert(batches.length === duplicateBefore, 'Double paste does not queue another batch');
    await completed();
    assert(get().value === '5.00' && status().dataset.batchState === 'saved', '1×1 gets canonical server value');
    assert(batches[0].token === 'browser-fixture-token' && batches[0].url === '/hx/gradebook/1/scores/batch', 'Batch uses existing CSRF and dedicated endpoint');
    assert(document.activeElement === get(), 'Paste preserves focused top-left input');
    const posts = singles.length;
    get().blur(); await wait(200);
    assert(singles.length === posts, 'Confirmed 1×1 paste has no duplicate blur POST');
    get().focus();
    const cases = [
      ['1\r\n2\r\n3\r\n', [['1'],['2'],['3']], 'Windows CRLF plus one terminator'],
      ['1\n2\n3', [['1'],['2'],['3']], 'Unix vertical N×1'],
      ['1\r2\r3\r', [['1'],['2'],['3']], 'CR line endings'],
      ['1\t2\n3\t4\n', [['1','2'],['3','4']], '2×2 with terminal newline'],
      ['\t0\n5\t', [['','0'],['5','']], 'Blank first and trailing fields with real zero'],
      ['1\t', [['1','']], '1×N trailing empty field'],
      ['1\n\n3', [['1'],[''],['3']], 'Meaningful blank interior row in one column'],
      ['1\n\n', [['1'],['']], 'Only one final newline removed; final blank row preserved'],
      ['\t\n\t\n', [['',''],['','']], 'Explicit blank two-column rows preserved'],
      ['', [['']], 'Empty plain-text field clears exactly one cell'],
    ];
    for (const [text, expected, name] of cases) {
      const count = batches.length;
      paste(text); await completed();
      assert(batches.length === count + 1 && JSON.stringify(batches.at(-1).matrix.values) === JSON.stringify(expected), name);
      assert(status().dataset.batchState === 'saved' && !get().readOnly, `${name}: server-confirmed usable state`);
    }
    paste('1\t2\n3\t4'); await completed();
    const matrix = batches.at(-1).matrix;
    assert(JSON.stringify(matrix.enrollment_ids) === '[1,2]' && JSON.stringify(matrix.component_ids) === '[10,11]', 'Rectangle maps stable enrollment/component IDs');
    assert(document.querySelectorAll('[data-grid-selected="true"]').length === 4, 'Successful paste selects the pasted rectangle');
    get().setSelectionRange(0,0);
    assert(copy() === '1.00\t2.00\n3.00\t4.00', 'Task 7 copy uses authoritative values after paste');
    assert(document.getElementById('row-1-1-total').textContent === '987.65'
      && document.getElementById('row-1-1-max').textContent === '432.10'
      && document.getElementById('row-1-1-count').textContent === '2 / 2'
      && document.getElementById('row-1-1-complete').textContent === 'ครบ', 'All summary fields use recognizable server data, not client totals');
    for (const text of ['1\t2\n3', '1\t2\t3', '1\n2\n3\n4', '1\n2\n3\n4\n5', '1\t2\n\n3\t4']) {
      const count = batches.length, value = get().value;
      paste(text); await completed();
      assert(batches.length === count && get().value === value && status().dataset.batchState === 'error', `Preflight rejects whole ragged/spill matrix ${JSON.stringify(text)}`);
    }
    const countAtLimit = batches.length;
    paste(Array(2001).fill('1').join('\n'));
    assert(batches.length === countAtLimit && status().textContent.includes('2000'), 'Client maximum size enforced');
    get(2,11).disabled = true;
    paste('1\t2\n3\t4'); assert(batches.length === countAtLimit, 'Disabled destination rejects all targets'); get(2,11).disabled = false;
    // Dirty active text is not submitted by blur while batch owns the grid.
    get().value = 'dirty-old'; get().dispatchEvent(new Event('input',{bubbles:true}));
    const beforeDirty = singles.length;
    paste('5'); get(2).focus(); await completed();
    assert(singles.length === beforeDirty && get().value === '5.00', 'Dirty starting value cannot race the batch through blur');
    assert(document.activeElement === get(2), 'Response does not steal newer focus');
    get().focus(); await until(() => ![...grid.querySelectorAll('input')].some(x=>x.readOnly)); await wait(50);
    for (const value of ['21', '1.234', '=SUM(A1:B1)', '<img src=x onerror=alert(1)>','revoked','csrf','failure','login','refresh']) {
      const before = [...grid.querySelectorAll('input')].map(x=>x.value).join('|');
      paste(value); await completed();
      assert([...grid.querySelectorAll('input')].map(x=>x.value).join('|') === before, `${value}: failure preserves every displayed cell`);
      assert(status().dataset.batchState === 'error' && !get().readOnly, `${value}: no false success and editability restored`);
      assert(!document.querySelector('img'), `${value}: no HTML injection`);
    }
    paste('1\t2\n21\t4'); await completed();
    assert(get(2).getAttribute('aria-invalid') === 'true' && status().textContent.includes('นักเรียนทดสอบ 2'), 'Safe offending-cell identity and human message survive failure');
    assert(document.querySelectorAll('[data-grid-selected="true"]').length === 4, 'Failed paste retains intended rectangle');
    for (const mode of ['abort','bad-json','duplicate','bad-total','bad-cell','missing-cell','missing-marker']) {
      const before = [...grid.querySelectorAll('input')].map(x=>x.value).join('|');
      transport = mode; paste('5\t6'); await completed();
      assert(status().dataset.batchState === 'error' && !get().readOnly, `${mode}: transport/response failure restores state`);
      assert([...grid.querySelectorAll('input')].map(x=>x.value).join('|') === before, `${mode}: malformed response never partially applies`);
    }
    paste('5'); await completed();
    assert(status().dataset.batchState === 'saved', 'Explicit retry works after failures');
    get().value = '6'; get().dispatchEvent(new Event('input',{bubbles:true})); get(2).focus();
    get(2).value='5'; get(2).dispatchEvent(new Event('input',{bubbles:true})); get(3).focus();
    const pendingCount=batches.length;
    paste('1',get(3));
    assert(batches.length === pendingCount && status().textContent.includes('รอการบันทึก'), 'Active and queued single-cell requests block paste');
    await until(() => ![...grid.querySelectorAll('input')].some(x=>x.readOnly)); await wait(50);
    get().focus(); await until(() => ![...grid.querySelectorAll('input')].some(x=>x.readOnly)); await wait(50);
    paste('5'); await completed();
    const beforeNavigation=singles.length;
    key(get(),'Enter'); assert(document.activeElement === get(2), 'Task 6 Enter navigation remains after batch');
    await wait(200); assert(singles.length===beforeNavigation,'Navigation after confirmed batch does not duplicate its save');
    key(get(2),'ArrowUp'); assert(document.activeElement === get(), 'Task 6 ArrowUp remains after batch');
    await until(() => ![...grid.querySelectorAll('input')].some(x=>x.readOnly));
    const unrelated=document.createElement('input'); document.body.append(unrelated); unrelated.focus();
    const outsideCount=batches.length; assert(!paste('1\t2',unrelated).defaultPrevented && batches.length===outsideCount, 'Unrelated input paste remains native'); unrelated.remove();
    await until(() => ![...grid.querySelectorAll('input')].some(x=>x.readOnly));
    get().focus(); get().dispatchEvent(new CompositionEvent('compositionstart',{bubbles:true}));
    assert(!paste('5').defaultPrevented,'Composition does not start custom paste'); get().dispatchEvent(new CompositionEvent('compositionend',{bubbles:true}));
    const noText = new DataTransfer(); noText.setData('text/html','<b>5</b>');
    const noTextCount = batches.length;
    get().dispatchEvent(new ClipboardEvent('paste',{bubbles:true,cancelable:true,clipboardData:noText}));
    assert(batches.length===noTextCount,'HTML-only clipboard does not invent score values');
    const failedPosts=singles.length;
    get().value='dirty-before-transport'; get().dispatchEvent(new Event('input',{bubbles:true}));
    transport='abort'; paste('6'); await completed(); get().blur(); await wait(200);
    assert(singles.length===failedPosts,'Unconfirmed batch cannot autosave stale dirty text through blur');
    get().focus(); paste('5'); await completed();
    const untouched=get(1,11).value;
    transport='missing-dom'; paste('5\t6'); await completed();
    assert(status().dataset.batchState==='error' && status().textContent.includes('บันทึกคะแนนแล้ว'),'Missing destination reports committed refresh failure');
    assert(get(1,11).value===untouched,'Missing DOM target never partially updates other score cells');
    output.textContent = `PASS: ${checks.length} paste assertions\n` + checks.join('\n');
  } catch (error) { output.textContent = `FAIL after ${checks.length} paste assertions: ${error.message}\n` + checks.join('\n'); throw error; }
})();
