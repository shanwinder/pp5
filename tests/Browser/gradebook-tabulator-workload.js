(async () => {
  'use strict';
  const output = document.getElementById('browser-results'), checks = [];
  const assert = (condition, label) => { if (!condition) throw new Error(label); checks.push(label); };
  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
  const until = async condition => {
    for (let i = 0; i < 150; i++) { if (condition()) return; await sleep(20); }
    throw new Error('Timed out waiting for workload result');
  };
  try {
    for (let i = 0; i < 150 && !document.querySelector('.pp5-gradebook').hidden; i++) await sleep(20);
    const data = JSON.parse(document.getElementById('gradebook-grid-data').textContent);
    const stress = new URLSearchParams(location.search).get('mode') === 'stress';
    const expectedRows = stress ? 100 : 35, expectedColumns = stress ? 40 : 20;
    const host = document.getElementById('gradebook-tabulator');
    const holder = host.querySelector('.tabulator-tableholder');
    assert(document.querySelector('.pp5-gradebook').hidden && !host.hidden, 'Tabulator initialized');
    assert(data.rows.length === expectedRows && data.components.length === expectedColumns, 'Representative matrix dimensions');
    assert(host.querySelectorAll('.tabulator-col[tabulator-field^="score_"]').length === expectedColumns, 'Every score component has a stable field');
    const renderedRows = host.querySelectorAll('.tabulator-row').length;
    assert(stress ? renderedRows < expectedRows : renderedRows <= expectedRows, 'Row renderer stays bounded for the matrix size');
    assert(holder.scrollWidth > holder.clientWidth, 'Wide scores scroll internally');
    assert(document.documentElement.scrollWidth <= innerWidth + 1, 'Wide grid does not overflow document');
    if (!stress) {
      const requests = [], originalFetch = window.fetch;
      window.fetch = (...args) => { requests.push({ url: String(args[0]), body: args[1]?.body }); return originalFetch(...args); };
      try {
        const first = host.querySelector('.tabulator-row [tabulator-field="score_1000"]');
        first.click(); first.focus();
        const data = new DataTransfer(); data.setData('text/plain', '1\t2\t3\n4\t5\t6\n7\t8\t9');
        const paste = new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true });
        first.dispatchEvent(paste);
        assert(paste.defaultPrevented, 'Three by three paste intercepts native mutation');
        assert(first.textContent === '', 'Three by three paste waits for server authority');
        await until(() => first.textContent === '1.00' && !host.hasAttribute('aria-busy'));
        const sent = JSON.parse(requests[0].body.get('batch'));
        assert(requests.length === 1 && requests[0].url.endsWith('/scores/batch')
          && sent.enrollment_ids.join(',') === '1,2,3'
          && sent.component_ids.join(',') === '1000,1001,1002'
          && sent.values[2][2] === '9', 'Three by three paste uses one stable-ID batch');
        const remove = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true, cancelable: true });
        first.dispatchEvent(remove);
        await until(() => requests.length === 2 && first.textContent === '' && !host.hasAttribute('aria-busy'));
        const clear = JSON.parse(requests[1].body.get('batch'));
        assert(remove.defaultPrevented && clear.values.length === 3
          && clear.values.every(row => row.length === 3 && row.every(value => value === '')),
          'Three by three clear uses one blank batch');
      } finally { window.fetch = originalFetch; }
    }
    const before = host.querySelectorAll('.tabulator-cell').length;
    holder.scrollTop = holder.scrollHeight;
    await sleep(100);
    assert(host.querySelectorAll('.tabulator-cell').length < expectedRows * (expectedColumns + 6), 'Scrolling retains bounded DOM cells');
    assert(host.querySelectorAll('.tabulator-cell').length > 0 && before > 0, 'Grid remains rendered after scroll');
    output.textContent = `PASS: ${checks.length} ${stress ? '100x40' : '35x20'} workload checks\n` + checks.join('\n');
    document.title = `PASS ${checks.length} — workload`;
  } catch (error) {
    output.textContent = `FAIL after ${checks.length}: ${error.message}\n` + checks.join('\n');
    document.title = 'FAIL — workload';
  }
})();
