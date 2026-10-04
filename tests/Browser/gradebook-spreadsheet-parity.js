// SYNTHETIC contract checks on the isolated fixture. Real pointer, keyboard and clipboard
// workflow checks are reported separately; this suite does not claim application acceptance.
(async () => {
  'use strict';
  const output = document.getElementById('browser-results');
  if (!output) return;
  const checks = [], requests = [];
  const assert = (okay, label) => { if (!okay) throw new Error(label); checks.push(label); };
  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
  const until = async predicate => {
    for (let n = 0; n < 150; n++) { if (predicate()) return; await sleep(20); }
    throw new Error('Timed out waiting for interaction');
  };
  const grid = document.getElementById('gradebook-tabulator');
  const cells = () => [...grid.querySelectorAll('.tabulator-row')];
  const at = (row, field) => cells()[row]?.querySelector(`[tabulator-field="${field}"]`);
  const focused = () => document.activeElement;
  const key = (value, options = {}) => {
    const event = new KeyboardEvent('keydown', { key: value, bubbles: true, cancelable: true, ...options });
    focused().dispatchEvent(event); return event;
  };
  const choose = async target => {
    target.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    target.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    target.click();
    await until(() => focused() === target);
  };
  const drag = async (from, to) => {
    from.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    to.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));
    to.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    await until(() => focused() === to && document.querySelectorAll('.tabulator-range-selected').length > 1);
  };
  const clipboard = (type, value = '') => {
    const data = new DataTransfer();
    if (type === 'paste') data.setData('text/plain', value);
    const event = new ClipboardEvent(type, { bubbles: true, cancelable: true, clipboardData: data });
    focused().dispatchEvent(event);
    return { event, text: data.getData('text/plain') };
  };
  const originalFetch = window.fetch;
  window.fetch = (...args) => { requests.push({ url: String(args[0]), body: args[1]?.body }); return originalFetch(...args); };
  try {
    await until(() => document.querySelector('.pp5-gradebook').hidden && cells().length === 4);
    const script = [...document.scripts].find(item => item.src.includes('/assets/gradebook.js'));
    const css = [...document.querySelectorAll('link[rel="stylesheet"]')].find(item => item.href.includes('/assets/gradebook-grid.css'));
    assert(/\/gradebook\.js\?v=\d+$/.test(script?.src ?? '') && /\/gradebook-grid\.css\?v=\d+$/.test(css?.href ?? ''),
      'GB-RUNTIME-004 — First-party Gradebook assets have deterministic versioned URLs');
    assert(![...document.querySelectorAll('script[src],link[href]')].some(item => /https?:\/\//.test(item.getAttribute('src') ?? item.getAttribute('href') ?? '')),
      'Runtime assets remain local');
    for (const field of ['identity', 'rowType', 'total']) {
      const target = at(0, field);
      target.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
      target.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
      target.click();
    }
    assert(document.querySelectorAll('.tabulator-range-selected').length === 0,
      'GB-SEL-004 — Identity, row type and summary clicks do not select scores');
    await choose(at(0, 'score_10'));
    assert(focused() === at(0, 'score_10') && document.querySelectorAll('.tabulator-cell[tabindex="0"]').length === 1,
      'Click establishes one active score tab stop without test focus repair');
    assert(grid.tabIndex === -1 && grid.querySelector('.tabulator-tableholder')?.tabIndex === -1,
      'Active score is the only grid Tab stop');
    key('ArrowDown'); assert(focused() === at(1, 'score_10'), 'GB-NAV-002 — ArrowDown moves one score row');
    key('ArrowUp'); assert(focused() === at(0, 'score_10'), 'GB-NAV-001 — ArrowUp moves one score row');
    key('ArrowRight'); assert(focused() === at(0, 'score_11'), 'GB-NAV-004 — ArrowRight moves one score component');
    key('ArrowRight'); assert(focused() === at(0, 'score_11'),
      'GB-NAV-006 — Arrow navigation stops before summary columns');
    const singleCopy = clipboard('copy');
    assert(singleCopy.text === '0.00', 'GB-COPY-001 — Single score copy preserves saved zero');
    key('ArrowLeft'); assert(focused() === at(0, 'score_10'), 'GB-NAV-003 — ArrowLeft moves one score component');
    key('Escape');
    assert(focused() === at(0, 'score_10') && document.querySelectorAll('.tabulator-range-selected').length === 0,
      'GB-SEL-006 — Escape removes the range while keeping score focus');
    await choose(at(0, 'score_10'));
    if (document.getElementById('gradebook-csrf')) {
    key('5'); assert(grid.querySelector('input')?.value === '5', 'Printable typing opens replace editor');
    key('Escape'); assert(!grid.querySelector('input') && focused() === at(0, 'score_10'), 'Editor Escape restores cell focus');
    key('Enter'); assert(grid.querySelector('input')?.value === '', 'Enter opens existing value');
    const editor = grid.querySelector('input');
    assert(!key('ArrowLeft').defaultPrevented && !key('ArrowRight').defaultPrevented, 'Editor Left and Right remain caret keys');
    const beforeEditorPaste = requests.length;
    assert(!clipboard('paste', '4').event.defaultPrevented && requests.length === beforeEditorPaste,
      'Editor paste stays native and never starts batch request');
    key('Escape');
    }
    await drag(at(0, 'score_10'), at(1, 'score_11'));
    assert(document.querySelectorAll('.tabulator-range-selected').length === 4 && focused() === at(1, 'score_11'),
      'Forward score-only drag focuses true extent');
    const copied = clipboard('copy');
    assert(copied.text === '\t0.00\n1.50\t6.00', 'Copy emits score-only TSV with blank and zero');
    key('ArrowLeft', { shiftKey: true });
    assert(focused() === at(1, 'score_10') && document.querySelectorAll('.tabulator-range-selected').length === 2,
      'GB-RANGE-103 — Forward Shift+Left shrinks from real extent');
    key('ArrowUp', { shiftKey: true });
    assert(focused() === at(0, 'score_10') && document.querySelectorAll('.tabulator-range-selected').length === 1,
      'GB-RANGE-101 — Shift+Up shrinks the actual extent');
    key('ArrowDown', { shiftKey: true });
    assert(focused() === at(1, 'score_10') && document.querySelectorAll('.tabulator-range-selected').length === 2,
      'GB-RANGE-102 — Shift+Down extends the actual extent');
    await drag(at(1, 'score_11'), at(0, 'score_10'));
    assert(focused() === at(0, 'score_10') && document.querySelectorAll('.tabulator-range-selected').length === 4,
      'Reverse drag retains top-left extent');
    key('ArrowRight', { shiftKey: true });
    assert(focused() === at(0, 'score_11') && document.querySelectorAll('.tabulator-range-selected').length === 2,
      'GB-RANGE-104 GB-RANGE-105 — Reverse Shift+Right moves from A1 toward B1');
    await drag(at(1, 'score_11'), at(0, 'score_10'));
    if (!document.getElementById('gradebook-csrf')) {
      key('ArrowDown'); assert(focused() === at(1, 'score_10'), 'GB-NAV-005 — Read-only ArrowDown works');
      key('ArrowDown'); key('ArrowDown');
      assert(focused() === at(3, 'score_10'), 'Historical score is keyboard navigable');
      assert(!clipboard('copy').text.includes('STUDENT-'), 'GB-COPY-005 — Read-only and historical copy excludes identity');
      const before = requests.length; key('Delete'); clipboard('paste', '5'); key('5');
      assert(requests.length === before && !grid.querySelector('input'), 'Read-only commands make no write and open no editor');
    } else {
      const beforePaste = requests.length;
      clipboard('paste', '5\t0\n\t12.5');
      await until(() => requests.length === beforePaste + 1 && at(1, 'score_11').textContent === '12.50');
      const paste = JSON.parse(requests.at(-1).body.get('batch'));
      assert(JSON.stringify(paste.enrollment_ids) === '[1,2]' && JSON.stringify(paste.component_ids) === '[10,11]',
        'GB-PASTE-003 — Reverse selected-range paste starts at top-left stable IDs');
      assert(JSON.stringify(paste.values) === JSON.stringify([['5','0'],['','12.5']]), 'Paste preserves blank and zero');
      assert(focused() === at(0, 'score_10') && document.querySelectorAll('.tabulator-range-selected').length === 4,
        'Successful paste keeps target range and top-left typing anchor');
      const beforeClear = requests.length; key('Delete');
      await until(() => requests.length === beforeClear + 1 && at(1, 'score_11').textContent === '');
      assert(focused() === at(0, 'score_10'), 'Delete preserves top-left typing anchor');
      key('9'); assert(grid.querySelector('input')?.value === '9' && grid.querySelector('input')?.closest('.tabulator-row') === cells()[0],
        'Typing after Delete starts at top-left without a second click');
      key('Escape');
      await drag(at(0, 'score_10'), at(1, 'score_11'));
      const forwardStart = requests.length;
      clipboard('paste', '4\t0\n\t5');
      await until(() => requests.length === forwardStart + 1 && at(1, 'score_11').textContent === '5.00');
      const forward = JSON.parse(requests.at(-1).body.get('batch'));
      assert(JSON.stringify(forward.enrollment_ids) === '[1,2]' && JSON.stringify(forward.component_ids) === '[10,11]',
        'GB-PASTE-002 — Forward selected-range paste starts at top-left stable IDs');
      await choose(at(2, 'score_11'));
      const beforeSpill = requests.length;
      clipboard('paste', '1\t2\n3\t4');
      assert(requests.length === beforeSpill && at(2, 'score_11').textContent === '',
        'GB-PASTE-008 — Spill rejects the whole paste before a request');
      await choose(at(3, 'score_10'));
      const beforeHistory = requests.length; key('Delete'); clipboard('paste', '5');
      assert(requests.length === beforeHistory, 'Historical write commands reject atomically');
    }
    output.textContent = `PASS: ${checks.length} SYNTHETIC spreadsheet parity checks\n` + checks.join('\n');
    document.title = `PASS ${checks.length} — synthetic spreadsheet parity`;
  } catch (error) {
    output.textContent = `FAIL after ${checks.length}: ${error.message}\n` + checks.join('\n');
    document.title = 'FAIL — synthetic spreadsheet parity';
  } finally { window.fetch = originalFetch; }
})();
