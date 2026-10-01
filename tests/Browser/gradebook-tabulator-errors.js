(async () => {
  'use strict';
  const output = document.getElementById('browser-results');
  const scenario = new URLSearchParams(location.search).get('scenario') || 'single-invalid';
  const checks = [], requests = [];
  const assert = (yes, label) => { if (!yes) throw new Error(label); checks.push(label); };
  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
  const until = async predicate => {
    for (let i = 0; i < 150; i++) { if (predicate()) return; await sleep(20); }
    throw new Error('Timed out waiting for scenario');
  };
  const grid = document.getElementById('gradebook-tabulator');
  const cell = () => grid.querySelector('.tabulator-row [tabulator-field="score_10"]');
  const status = document.getElementById('gradebook-batch-status');
  const syntheticClick = target => { const box = target.getBoundingClientRect();
    target.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true,
      clientX: box.left + box.width / 2, clientY: box.top + box.height / 2 })); };
  const key = (target, value) => target.dispatchEvent(new KeyboardEvent('keydown', { key: value, bubbles: true, cancelable: true }));
  const paste = value => {
    const data = new DataTransfer(); data.setData('text/plain', value);
    const event = new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true });
    cell().dispatchEvent(event); return event;
  };
  const originalFetch = window.fetch;
  window.fetch = (...args) => {
    requests.push(String(args[0]));
    if (scenario.endsWith('-network')) return Promise.reject(new TypeError('Synthetic connection loss'));
    return originalFetch(...args);
  };
  try {
    await until(() => document.querySelector('.pp5-gradebook').hidden);
    const values = { 'single-invalid':'20.01', 'single-over-max':'999', 'single-nonnumeric':'abc',
      'single-unmarked':'unmarked', 'single-login':'login',
      'single-malformed':'malformed', 'single-409':'refresh', 'single-500':'failure',
      'single-revoked':'revoked', 'single-network':'5', 'batch-invalid':'21',
      'batch-unmarked':'unmarked', 'batch-login':'login', 'batch-409':'refresh',
      'batch-500':'failure', 'batch-revoked':'revoked', 'batch-csrf':'csrf', 'batch-network':'5' };
    const value = values[scenario];
    assert(value !== undefined, 'Known error scenario');
    syntheticClick(cell());
    await until(() => document.activeElement === cell());
    assert(document.activeElement === cell(), 'Click event without manual focus gives error scenario grid focus');
    const before = cell().textContent;
    if (scenario.startsWith('single-')) {
      key(cell(), '5');
      const editor = grid.querySelector('input');
      assert(Boolean(editor), 'Score editor opens');
      assert(grid.querySelectorAll('.tabulator-cell input').length === 1 && document.querySelector('.pp5-gradebook').hidden
        && getComputedStyle(editor).outlineStyle === 'none', 'Rejected-value editor has one borderless input and hidden fallback');
      editor.value = value; key(editor, 'Enter');
    } else {
      assert(paste(value).defaultPrevented, 'Batch paste is intercepted');
    }
    await until(() => status.dataset.batchState === 'error' && !cell().dataset.pp5Saving && !grid.hasAttribute('aria-busy'));
    assert(requests.length === 1, 'Exactly one server request was sent');
    assert(scenario.startsWith('single-') ? requests[0].includes('/components/10/enrollments/1/score') : requests[0].endsWith('/scores/batch'),
      'Existing endpoint owns the write');
    if (scenario.startsWith('single-')) {
      assert(cell().textContent === value, 'Rejected or unconfirmed typed text remains visible');
      assert(cell().dataset.pp5Error === 'true', 'Cell is marked as an error');
      assert(!grid.querySelector('.tabulator-cell input') && document.querySelector('.pp5-gradebook').hidden,
        'Rejected value leaves no editor or visible fallback');
      assert(cell().closest('.tabulator-row').querySelector('[tabulator-field="total"]').textContent === '0.00', 'No false summary reconciliation');
    } else {
      assert(cell().textContent === before, 'Batch failure does not speculatively mutate cell');
      assert(cell().closest('.tabulator-row').querySelector('[tabulator-field="total"]').textContent === '0.00', 'Batch failure does not show false total');
    }
    const uncertain = scenario.includes('unmarked') || scenario.includes('login') || scenario.includes('malformed')
      || scenario.endsWith('-409') || scenario.endsWith('-500') || scenario.endsWith('-network');
    if (uncertain) {
      assert(status.textContent.includes('ไม่แน่นอน') && status.textContent.includes('โหลดหน้าใหม่'), 'Uncertain result asks for reload');
      syntheticClick(cell()); await until(() => document.activeElement === cell()); paste('5'); key(cell(), 'Delete');
      await sleep(60);
      assert(requests.length === 1, 'Uncertain write is never blindly retried');
    } else if (scenario.endsWith('-revoked')) {
      syntheticClick(cell()); await until(() => document.activeElement === cell()); paste('5');
      assert(requests.length === 1, 'Live permission revocation disables further write attempts');
    } else {
      assert(!status.textContent.includes('แล้ว (เปลี่ยนแปลง'), 'Known rejection never claims save');
    }
    output.textContent = `PASS: ${checks.length} ${scenario} checks\n` + checks.join('\n');
    document.title = `PASS ${checks.length} — ${scenario}`;
  } catch (error) {
    output.textContent = `FAIL after ${checks.length}: ${error.message}\n` + checks.join('\n');
    document.title = `FAIL — ${scenario}`;
  } finally { window.fetch = originalFetch; }
})();
