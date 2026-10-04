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
    assert(data.rows.length === expectedRows && data.components.length === expectedColumns,
      stress ? 'GB-SCALE-002 — 100x40 representative matrix dimensions' : 'GB-SCALE-001 — 35x20 representative matrix dimensions');
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
        const nextBox = first.getBoundingClientRect();
        first.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true,
          clientX: nextBox.left + nextBox.width / 2, clientY: nextBox.top + nextBox.height / 2 }));
        await until(() => document.activeElement === first);
        assert(document.activeElement === first, 'Click event without manual focus focuses workload score cell');
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
          && sent.values[2][2] === '9', 'GB-PASTE-005 — Three by three paste uses one stable-ID batch');
        const remove = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true, cancelable: true });
        first.dispatchEvent(remove);
        await until(() => requests.length === 2 && first.textContent === '' && !host.hasAttribute('aria-busy'));
        const clear = JSON.parse(requests[1].body.get('batch'));
        assert(remove.defaultPrevented && clear.values.length === 3
          && clear.values.every(row => row.length === 3 && row.every(value => value === '')),
          'Three by three clear uses one blank batch');
        const box = first.getBoundingClientRect();
        first.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true,
          clientX: box.left + box.width / 2, clientY: box.top + box.height / 2 }));
        await until(() => document.activeElement === first);
        first.dispatchEvent(new KeyboardEvent('keydown', { key: '5', bubbles: true, cancelable: true }));
        const editor = first.querySelector('input');
        assert(editor?.value === '5' && host.querySelectorAll('.tabulator-cell input').length === 1
          && document.querySelector('.pp5-gradebook').hidden, 'Top-left cell edits immediately after 3x3 clear');
        editor.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
        assert(!host.querySelector('.tabulator-cell input') && first.textContent === '', 'Cancel after range clear removes editor');
        await sleep(30);
        first.dispatchEvent(new KeyboardEvent('keydown',
          { key: 'ArrowDown', shiftKey: true, bubbles: true, cancelable: true }));
        await until(() => host.querySelectorAll('.tabulator-range-selected').length === 2);
        holder.scrollTop = holder.scrollHeight;
        await sleep(100);
        holder.scrollTop = 0;
        await until(() => [...host.querySelectorAll('.tabulator-row')].slice(0, 2)
          .every(row => row.querySelector('[tabulator-field="score_1000"]')?.classList.contains('tabulator-range-selected')));
        const firstTwo = [...host.querySelectorAll('.tabulator-row')].slice(0, 2)
          .map(row => row.querySelector('[tabulator-field="score_1000"]'));
        assert(firstTwo.length === 2 && firstTwo.every(node => node?.classList.contains('tabulator-range-selected')),
          'GB-RANGE-008 GB-SCALE-003 — selected score identities repaint after virtual rows return');
      } finally { window.fetch = originalFetch; }
    }
    const before = host.querySelectorAll('.tabulator-cell').length;
    holder.scrollTop = holder.scrollHeight;
    await sleep(100);
    assert(host.querySelectorAll('.tabulator-cell').length < expectedRows * (expectedColumns + 6), 'Scrolling retains bounded DOM cells');
    assert(host.querySelectorAll('.tabulator-cell').length > 0 && before > 0, 'Grid remains rendered after scroll');
    if (stress) {
      const lastRow = [...host.querySelectorAll('.tabulator-row')].at(-1);
      const target = lastRow?.querySelector('[tabulator-field="score_1000"]');
      const box = target?.getBoundingClientRect();
      target?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true,
        clientX: box.left + box.width / 2, clientY: box.top + box.height / 2 }));
      await until(() => document.activeElement === target);
      target.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true, cancelable: true }));
      assert(document.activeElement?.getAttribute('tabulator-field') === 'score_1000'
        && document.activeElement !== target && host.querySelectorAll('.tabulator-cell[tabindex="0"]').length === 1,
        'GB-SCALE-002 GB-SCALE-003 — 100x40 virtualized Arrow navigation retains one active score');
    }
    output.textContent = `PASS: ${checks.length} ${stress ? '100x40' : '35x20'} workload checks\n` + checks.join('\n');
    document.title = `PASS ${checks.length} — workload`;
  } catch (error) {
    output.textContent = `FAIL after ${checks.length}: ${error.message}\n` + checks.join('\n');
    document.title = 'FAIL — workload';
  }
})();
