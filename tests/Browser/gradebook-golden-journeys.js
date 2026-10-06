// Golden Gradebook journeys require browser-generated input. This file never
// dispatches MouseEvent, KeyboardEvent or ClipboardEvent. Run one journey at a
// time on /?mode=golden&tests=golden&journey=A (through M), following the live
// instruction. J/K lock immediate single direct-entry → drag → clear; L/M
// repeat after rapid horizontal/vertical entry while saves remain pending.
// A PASS requires trusted events plus the resulting DOM and wire
// assertions. The isolated fixture is separate from authenticated MAMP proof.
(async () => {
  'use strict';
  const output = document.getElementById('browser-results');
  const host = document.getElementById('gradebook-tabulator');
  if (!output || !host) return;
  const journey = new URLSearchParams(location.search).get('journey')?.toUpperCase();
  const valid = 'ABCDEFGHIJKLM';
  const singleDelayMs = Math.min(2000, Math.max(0, Number(new URLSearchParams(location.search).get('singleDelay')) || 0));
  const events = [], requests = [], passed = [];
  const rows = () => [...host.querySelectorAll('.tabulator-row')];
  const cell = (row, component) => rows()[row]?.querySelector(`[tabulator-field="score_${component}"]`);
  const selected = () => [...host.querySelectorAll('.tabulator-cell.tabulator-range-selected')];
  const focus = () => document.activeElement;
  const status = () => document.getElementById('gradebook-batch-status')?.textContent ?? '';
  const holder = () => host.querySelector('.tabulator-tableholder');
  const snapshot = () => [scrollX, scrollY, holder()?.scrollLeft, holder()?.scrollTop];
  const sameViewport = (before, after) => before.every((n, i) => Math.abs(n - after[i]) < 0.6);
  const has = (type, since) => events.slice(since).some(item => item.type === type);
  const assert = (okay, ids, label) => {
    if (!okay) throw new Error(`${ids.join(', ')} — ${label}`);
    passed.push(`${ids.join(', ')} — ${label}`);
  };
  const wait = async (instruction, predicate, eventType = null, timeoutMs = 300000) => {
    const since = events.length;
    output.textContent = `Journey ${journey}: ${instruction}\n\nPassed:\n${passed.join('\n')}`;
    document.title = `WAIT ${journey} — ${instruction}`;
    const deadline = performance.now() + timeoutMs;
    while (performance.now() < deadline) {
      if (predicate() && (!eventType || has(eventType, since))) return;
      await new Promise(resolve => setTimeout(resolve, 20));
    }
    throw new Error(`Timed out: ${instruction}`);
  };
  const batch = () => requests.filter(item => item.url.endsWith('/scores/batch'));
  const matrix = request => JSON.parse(request.body.get('batch'));
  const click = async (row, component) => {
    const since = events.length;
    await wait(`Click row ${row + 1}, score ${component}`, () => focus() === cell(row, component), 'pointerup');
    assert(has('pointerdown', since) && has('pointerup', since),
      ['GB-SEL-001'], 'browser pointer press and release reached the grid');
    assert(selected().length === 1 && selected()[0] === cell(row, component),
      ['GB-SEL-001','GB-SEL-002','GB-SEL-005'], 'one score is selected and focused');
  };
  const type = async value => {
    await wait(`Type ${value} into the focused score`, () => host.querySelector('.tabulator-cell input')?.value === value, 'keydown');
    assert(Boolean(host.querySelector('.tabulator-cell input')),
      ['GB-DIRECT-001'], 'printable input opens replacement editor');
  };
  const move = async (key, row, component, ids) => {
    await wait(`Press ${key}`, () => focus() === cell(row, component) && !host.querySelector('.tabulator-cell input'), 'keydown');
    assert(focus() === cell(row, component), ids, `${key} commits and focuses the target score`);
  };
  const drag = async (fromRow, fromColumn, toRow, toColumn, ids) => {
    const expected = (Math.abs(toRow - fromRow) + 1) * (Math.abs(toColumn - fromColumn) + 1);
    const since = events.length;
    await wait(`REAL pointer drag row ${fromRow + 1}/score ${fromColumn} to row ${toRow + 1}/score ${toColumn}`,
      () => selected().length === expected && focus() === cell(toRow, toColumn), 'pointerup');
    const expectedCells = [];
    for (let row = Math.min(fromRow, toRow); row <= Math.max(fromRow, toRow); row++)
      for (let component = Math.min(fromColumn, toColumn); component <= Math.max(fromColumn, toColumn); component++)
        expectedCells.push(cell(row, component));
    assert(has('pointerdown', since) && has('pointermove', since) && has('pointerup', since)
      && selected().length === expected && selected().every(element => expectedCells.includes(element)),
      ids, `exact ${expected}-cell score rectangle is painted`);
    assert(focus() === cell(toRow, toColumn),
      ['GB-RANGE-003','GB-RANGE-004','GB-RANGE-005','GB-RANGE-006','GB-RANGE-007'],
      'anchor, extent, active and visual range agree after release');
  };
  const clear = async (key, size, ids, timeoutMs = 300000, eventSince = null, batchStart = null) => {
    const start = batchStart ?? batch().length;
    await wait(`Press ${key} without clicking again`, () => batch().length === start + 1
      && status().includes('ช่องแล้ว')
      && (eventSince === null || events.slice(eventSince).some(item => item.type === 'host-keydown' && item.key === key)),
    eventSince === null ? 'keydown' : null, timeoutMs);
    const sent = matrix(batch().at(-1));
    assert(JSON.stringify(sent.enrollment_ids) === JSON.stringify([1,2,3].slice(0,size))
      && JSON.stringify(sent.component_ids) === JSON.stringify([10,11,12].slice(0,size))
      && sent.values.length === size && sent.values.every(line => line.length === size && line.every(value => value === '')),
      ids, `one atomic ${size}×${size} blank batch`);
    assert([0,1,2].slice(0,size).every(row => [10,11,12].slice(0,size).every(component => cell(row,component)?.textContent === '')),
      ['GB-CLEAR-007'], 'every selected score reflects the authoritative blank result');
    assert(selected().length === size * size && focus() === cell(0, 10),
      ['GB-CLEAR-008','GB-CLEAR-009'], 'clear preserves rectangle and focuses top-left');
  };
  document.addEventListener('pointerdown', event => { if (event.isTrusted) events.push({ type: 'pointerdown' }); }, true);
  document.addEventListener('pointermove', event => { if (event.isTrusted) events.push({ type: 'pointermove' }); }, true);
  document.addEventListener('pointerup', event => { if (event.isTrusted) events.push({ type: 'pointerup' }); }, true);
  document.addEventListener('keydown', event => { if (event.isTrusted) events.push({ type: 'keydown', key: event.key,
    shortcut: event.metaKey || event.ctrlKey }); }, true);
  host.addEventListener('keydown', event => { if (event.isTrusted) events.push({ type: 'host-keydown', key: event.key,
    pending: window.__gradebookTiming?.snapshot()?.pendingSingles ?? null,
    editing: window.__gradebookTiming?.snapshot()?.editing ?? null,
    selected: selected().length,
    focusField: document.activeElement?.getAttribute('tabulator-field') ?? null }); }, true);
  // Browser automation may route native clipboard shortcuts directly to a
  // clipboard event without a trusted keydown. The operator must use the browser
  // shortcut and independently read the browser clipboard for real evidence.
  document.addEventListener('copy', event => { events.push({ type: 'copy', event }); }, true);
  document.addEventListener('paste', event => { events.push({ type: 'paste', event }); }, true);
  const originalFetch = window.fetch;
  window.fetch = async (...args) => {
    const url = String(args[0]);
    const item = { url, body: args[1]?.body, at: performance.now() };
    requests.push(item);
    const response = await originalFetch(...args);
    if (singleDelayMs && url.endsWith('/score')) await new Promise(resolve => setTimeout(resolve, singleDelayMs));
    item.resolvedAt = performance.now();
    return response;
  };
  try {
    if (!valid.includes(journey) || journey?.length !== 1) throw new Error('Use journey=A through journey=M');
    for (let n = 0; n < 250; n++) {
      if (rows().length === 4 && document.querySelector('.pp5-gradebook')?.hidden) break;
      await new Promise(resolve => setTimeout(resolve, 20));
    }
    assert(rows().length === 4 && document.querySelector('.pp5-gradebook')?.hidden,
      ['GB-RUNTIME-001','GB-RUNTIME-002','GB-RUNTIME-003'], 'one live Tabulator engine');
    if (journey === 'A') {
      await click(0, 10);
      await type('5'); await move('Right', 0, 11, ['GB-DIRECT-002']);
      await type('6'); await move('Right', 0, 12, ['GB-DIRECT-002']);
      await type('7'); await move('Right', 0, 13, ['GB-DIRECT-009']);
      await wait('Wait for 5.00, 6.00, 7.00 server results', () => [10,11,12].map(id => cell(0,id)?.textContent).join(',') === '5.00,6.00,7.00');
      assert(requests.filter(item => item.url.includes('/score')).length === 3,
        ['GB-SERVER-003','GB-SERVER-005'], 'rapid horizontal entry uses three authoritative single writes');
    } else if (journey === 'B') {
      await click(0, 10);
      await type('5'); await move('Down', 1, 10, ['GB-DIRECT-004']);
      await type('6'); await move('Down', 2, 10, ['GB-DIRECT-004']);
      await type('7'); await move('Down', 2, 10, ['GB-DIRECT-010']);
      await wait('Wait for 5.00, 6.00, 7.00 server results', () => [0,1,2].map(row => cell(row,10)?.textContent).join(',') === '5.00,6.00,7.00');
      assert(requests.filter(item => item.url.includes('/score')).length === 3,
        ['GB-SERVER-005','GB-SERVER-006'], 'rapid vertical responses preserve the newest focus');
    } else if (journey === 'C') {
      await wait('Double-click row 2, score 10 (existing 12.50)', () => host.querySelector('.tabulator-cell input')?.value === '12.50', 'pointerup');
      assert(focus() === host.querySelector('.tabulator-cell input'), ['GB-EDIT-001'], 'existing-value editor owns focus');
      for (const key of ['Left','Right','BackSpace']) {
        const count = events.length;
        await wait(`Press ${key} inside editor`, () => events.slice(count).some(item => item.type === 'keydown' && item.key === ({Left:'ArrowLeft',Right:'ArrowRight',BackSpace:'Backspace'}[key])), 'keydown');
      }
      assert(Boolean(host.querySelector('.tabulator-cell input')),
        ['GB-EDIT-004','GB-EDIT-006'], 'caret and Backspace remain native editor operations');
      await move('Enter', 2, 10, ['GB-EDIT-005']);
      assert(cell(1,10)?.textContent !== '', ['GB-EDIT-009'], 'explicit edit commits without clearing the score');
    } else if (journey === 'D') {
      await drag(0, 10, 2, 12, ['GB-RANGE-001']);
      await clear('Delete', 3, ['GB-CLEAR-003','GB-CLEAR-007']);
      await type('8');
      assert(focus()?.closest('.tabulator-row') === rows()[0], ['GB-CLEAR-010'], 'typing immediately edits top-left without click');
      await wait('Press Escape to cancel the uncommitted 8', () => !host.querySelector('.tabulator-cell input')
        && focus() === cell(0,10), 'keydown');
      await drag(0, 10, 2, 12, ['GB-RANGE-001']);
      await clear('Backspace', 3, ['GB-CLEAR-004']);
    } else if (journey === 'E') {
      await drag(2, 12, 0, 10, ['GB-RANGE-002']);
      await clear('Backspace', 3, ['GB-CLEAR-005','GB-CLEAR-007']);
      await drag(2, 12, 0, 10, ['GB-RANGE-002']);
      await clear('Delete', 3, ['GB-CLEAR-005']);
    } else if (journey === 'F') {
      await drag(0, 10, 1, 11, ['GB-RANGE-001']);
      await wait('Press native Copy shortcut', () => events.some(item => item.type === 'copy'), 'copy');
      const copied = events.filter(item => item.type === 'copy').at(-1)?.event?.clipboardData?.getData('text/plain');
      assert(copied === '\t0.00\n12.50\t6.00',
        ['GB-COPY-002','GB-COPY-003','GB-COPY-004','GB-COPY-006'], 'native copy yields exact score-only TSV');
      await click(1, 12);
      const start = batch().length;
      await wait('Put 5\\t0\\n\\t12.5 on clipboard, then press native Paste shortcut',
        () => batch().length === start + 1 && status().includes('ช่องแล้ว'), 'paste');
      const sent = matrix(batch().at(-1));
      assert(JSON.stringify(sent.enrollment_ids) === '[2,3]' && JSON.stringify(sent.component_ids) === '[12,13]'
        && JSON.stringify(sent.values) === JSON.stringify([['5','0'],['','12.5']]),
        ['GB-PASTE-004','GB-PASTE-006','GB-PASTE-007','GB-PASTE-010'], 'one 2×2 batch uses stable IDs, blank and zero');
    } else if (journey === 'G') {
      await drag(0, 10, 2, 12, ['GB-RANGE-001']);
      const start = batch().length;
      await wait('Type 5 in the Fill control and click Fill',
        () => batch().length === start + 1 && status().includes('ช่องแล้ว'), 'pointerup');
      const sent = matrix(batch().at(-1));
      assert(sent.values.length === 3 && sent.values.every(line => line.length === 3 && line.every(value => value === '5')),
        ['GB-FILL-001','GB-FILL-002'], 'one 3×3 atomic Fill batch');
      assert(focus() === cell(0,10), ['GB-FILL-004','GB-FILL-005'], 'Fill returns focus to top-left');
      await wait('Press Right then type 8 immediately', () => host.querySelector('.tabulator-cell input')?.value === '8', 'keydown');
      assert(focus()?.closest('.tabulator-row') === rows()[0], ['GB-FILL-004'], 'post-Fill typing works without click');
    } else if (journey === 'H') {
      await wait('Focus the Fill input and press Tab into the Gradebook',
        () => focus() === cell(0,10), 'keydown');
      assert(selected().length === 1 && cell(0,10)?.tabIndex === 0,
        ['GB-SEL-003','GB-SEL-005'], 'Tab entry selects the first score with one roving stop');
      await click(3, 10);
      assert(cell(3,10)?.classList.contains('tabulator-range-selected'), ['GB-HIST-001','GB-RO-001'], 'historical score selectable');
      await move('Right', 3, 11, ['GB-HIST-002','GB-RO-002']);
      await wait('Press native Copy shortcut on historical score', () => events.some(item => item.type === 'copy'), 'copy');
      assert(Boolean(events.filter(item => item.type === 'copy').at(-1)), ['GB-HIST-003','GB-RO-003'], 'historical copy receives native shortcut');
      const before = requests.length;
      await wait('Press Delete on historical score', () => events.some(item => item.type === 'keydown' && item.key === 'Delete'), 'keydown');
      await wait('Put 5 on clipboard and press native Paste shortcut', () => events.some(item => item.type === 'paste'), 'paste');
      assert(requests.length === before && !host.querySelector('.tabulator-cell input'),
        ['GB-HIST-004','GB-RO-004','GB-RO-005','GB-RO-006'], 'historical write attempts send no request');
      await drag(2, 10, 3, 11, ['GB-RANGE-001']);
      const mixedStart = events.length, mixedRequests = requests.length;
      await wait('Press Delete on the mixed current/historical rectangle',
        () => events.slice(mixedStart).some(item => item.type === 'keydown' && item.key === 'Delete'), 'keydown');
      assert(requests.length === mixedRequests && selected().length === 4,
        ['GB-CLEAR-006','GB-CLEAR-007'], 'mixed range rejects the entire clear and remains selected');
    } else if (journey === 'I') {
      await click(0, 10);
      const before = snapshot();
      window.__goldenViewBefore = before;
      await type('5'); await move('Right', 0, 11, ['GB-DIRECT-002']);
      await type('6'); await move('Right', 0, 12, ['GB-DIRECT-009']);
      await type('7'); await move('Down', 1, 12, ['GB-DIRECT-004']);
      await wait('Wait for all direct-entry responses', () => requests.filter(item => item.url.includes('/score')).length === 3
        && !host.querySelector('[data-pp5-saving]'));
      assert(sameViewport(before, snapshot()),
        ['GB-VIS-001','GB-VIS-002','GB-VIS-003','GB-VIS-005','GB-VIS-010'], 'visible-neighbor entry keeps document and holder stable');
      assert(rows().every(row => Math.abs(row.getBoundingClientRect().height - rows()[0].getBoundingClientRect().height) < 0.6)
        && host.querySelectorAll('.tabulator-cell input').length === 0,
        ['GB-VIS-006','GB-VIS-007','GB-VIS-008','GB-VIS-009'], 'row/column geometry and frozen grid remain stable');
    } else if ('JKLM'.includes(journey)) {
      if (!window.__gradebookTiming) throw new Error('Use timing=1 for private-state fixture diagnostics');
      const key = 'JL'.includes(journey) ? 'Delete' : 'Backspace';
      const id = key === 'Delete' ? 'GB-CLEAR-011' : 'GB-CLEAR-012';
      const rapid = 'LM'.includes(journey);
      const direction = journey === 'L' ? 'horizontal' : 'vertical';
      await click(0, 10);
      await type('5');
      const eventStart = events.length, batchStart = batch().length;
      const sequence = rapid ? `${direction} 5→6→7, then drag` : 'Right, then immediately drag';
      await wait(`Complete ${sequence} row 1/score 10 to row 3/score 12, then press ${key}`,
        () => events.slice(eventStart).some(item => item.type === 'host-keydown' && item.key === key), null, 10000);
      const actual = selected(), expected = [0,1,2].flatMap(row => [10,11,12].map(component => cell(row, component)));
      assert(has('pointerdown', eventStart) && has('pointermove', eventStart) && has('pointerup', eventStart)
        && actual.length === 9 && actual.every(element => expected.includes(element)),
      ['GB-RANGE-001',id], 'trusted pointer drag paints exactly nine score cells during the continuous sequence');
      const arrow = journey === 'M' ? 'ArrowDown' : 'ArrowRight';
      const keyAtCommand = events.slice(eventStart).find(item => item.type === 'host-keydown' && item.key === key);
      const expectedSingles = rapid ? 3 : 1;
      assert(events.slice(eventStart).filter(item => item.type === 'keydown' && item.key === arrow).length >= (rapid ? 3 : 1)
        && keyAtCommand?.pending === expectedSingles,
      [rapid ? (journey === 'L' ? 'GB-DIRECT-009' : 'GB-DIRECT-010') : 'GB-DIRECT-002',id],
      'direct-entry Arrows queued the expected single writes before range clear');
      await clear(key, 3, [id], singleDelayMs * expectedSingles + 5000, eventStart, batchStart);
      const singles = requests.filter(item => item.url.endsWith('/score'));
      assert(singles.length === expectedSingles, [id], 'all queued single saves completed before the batch');
      const hostKey = events.slice(eventStart).find(item => item.type === 'host-keydown' && item.key === key);
      assert(Boolean(hostKey) && hostKey.selected === 9 && hostKey.editing === false,
        [id], `${key} reaches the host with a nine-cell selection and no editor`);
      if (singleDelayMs) assert(hostKey.pending > 0,
        [id], `${key} reaches the host while at least one single save is pending`);
      assert(window.__gradebookTiming.readyRejections.length === 0,
        [id], 'valid clear command was queued rather than rejected during a pending single');
      assert(batch().at(-1).at >= Math.max(...singles.map(item => item.resolvedAt)),
        ['GB-SERVER-007'], 'batch follows the earlier single save');
    }
    const timing = 'JKLM'.includes(journey) ? (() => {
      const key = 'JL'.includes(journey) ? 'Delete' : 'Backspace';
      const hostKey = events.filter(item => item.type === 'host-keydown' && item.key === key).at(-1);
      const singles = requests.filter(item => item.url.endsWith('/score'));
      return `\nTiming: ${JSON.stringify({ delay: singleDelayMs, selectedAtKey: hostKey?.selected,
        focusAtKey: hostKey?.focusField, pendingSinglesAtKey: hostKey?.pending, editingAtKey: hostKey?.editing,
        hostKeyReached: Boolean(hostKey), readyRejections: window.__gradebookTiming?.readyRejections.length ?? null,
        singles: singles.length, batches: batch().length,
        batchAfterSingle: Boolean(singles.length && batch()[0]?.at >= Math.max(...singles.map(item => item.resolvedAt))),
        finalFocus: focus()?.getAttribute('tabulator-field'), finalSelected: selected().length })}`;
    })() : '';
    output.textContent = `PASS: Golden Journey ${journey} — ${passed.length} real-browser checks${timing}\n` + passed.join('\n');
    document.title = `PASS Golden Journey ${journey}`;
  } catch (error) {
    const debug = window.__gradebookTiming;
    const lastKey = events.filter(item => item.type === 'host-keydown').at(-1);
    output.textContent = `FAIL: Golden Journey ${journey} — ${error.message}\nDiagnostics: ${JSON.stringify({
      delay: singleDelayMs, snapshot: debug?.snapshot(), viewportBefore: window.__goldenViewBefore,
      viewportAfter: journey === 'I' ? snapshot() : null, readyRejections: debug?.readyRejections, lastKey,
      selected: selected().length, focusField: focus()?.getAttribute('tabulator-field'),
      singles: requests.filter(item => item.url.endsWith('/score')).length, batches: batch().length,
    })}\n` + passed.join('\n');
    document.title = `FAIL Golden Journey ${journey}`;
  } finally {
    window.fetch = originalFetch;
  }
})();
