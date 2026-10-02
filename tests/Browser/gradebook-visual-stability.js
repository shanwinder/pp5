// Chromium fixture diagnostics and regression checks. Synthetic events supplement
// separate real pointer/keyboard checks against the fixture and MAMP page.
(async () => {
  'use strict';
  const output = document.getElementById('browser-results');
  const host = document.getElementById('gradebook-tabulator');
  if (!output || !host) return;
  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
  const frame = () => new Promise(resolve => requestAnimationFrame(resolve));
  const until = async condition => {
    for (let n = 0; n < 200; n++) { if (condition()) return; await sleep(20); }
    throw new Error('Timed out waiting for visual test state');
  };
  const cell = (row, component) => [...host.querySelectorAll('.tabulator-row')][row]
    ?.querySelector(`[tabulator-field="score_${component}"]`);
  const choose = async (row, component) => {
    const target = cell(row, component);
    target.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    target.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    target.click();
    await until(() => document.activeElement === target);
  };
  const key = value => document.activeElement.dispatchEvent(new KeyboardEvent('keydown',
    { key: value, bubbles: true, cancelable: true }));
  const rect = element => {
    const r = element?.getBoundingClientRect();
    return r && { left: r.left, top: r.top, width: r.width, height: r.height, right: r.right, bottom: r.bottom };
  };
  const snapshot = (from, target) => {
    const holder = host.querySelector('.tabulator-tableholder');
    return { window: [scrollX, scrollY], holder: [holder.scrollLeft, holder.scrollTop],
      host: rect(host), current: rect(from), target: rect(target),
      currentRow: rect(from?.closest('.tabulator-row')), targetRow: rect(target?.closest('.tabulator-row')),
      focus: document.activeElement?.getAttribute('tabulator-field') || document.activeElement?.tagName,
      editors: host.querySelectorAll('.tabulator-editing input').length,
      statusHeight: rect(document.getElementById('gradebook-batch-status'))?.height,
      currentText: from?.textContent };
  };
  const diagnostics = [];
  const checks = [];
  const assert = (condition, label) => { if (!condition) throw new Error(label); checks.push(label); };
  const near = (a, b) => Math.abs(a - b) < 0.6;
  const focusCalls = [];
  const originalFocus = HTMLElement.prototype.focus;
  HTMLElement.prototype.focus = function (...args) {
    if (host.contains(this)) focusCalls.push(this.getAttribute('tabulator-field') || this.tagName);
    return originalFocus.apply(this, args);
  };
  const observerStats = { childList: 0, classes: 0, selectedClasses: 0 };
  const observer = new MutationObserver(records => {
    for (const record of records) {
      if (record.type === 'childList') observerStats.childList++;
      if (record.attributeName === 'class') {
        observerStats.classes++;
        if (record.target.classList.contains('tabulator-range-selected')) observerStats.selectedClasses++;
      }
    }
  });
  let renders = 0;
  const countRender = () => { renders++; };
  let table;
  let shifts = 0, gradebookShifts = 0;
  let layoutObserver;
  try {
    await until(() => !host.classList.contains('pp5-grid-initializing') && cell(3, 13));
    table = Tabulator.findTable(host)[0];
    table.on('renderComplete', countRender);
    observer.observe(host, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
    if (typeof PerformanceObserver === 'function' && PerformanceObserver.supportedEntryTypes?.includes('layout-shift')) {
      layoutObserver = new PerformanceObserver(list => {
        for (const entry of list.getEntries()) {
          shifts += entry.value;
          if (entry.sources?.some(source => source.node && host.contains(source.node))) gradebookShifts += entry.value;
        }
      });
      layoutObserver.observe({ type: 'layout-shift', buffered: true });
    }
    document.documentElement.style.scrollBehavior = 'auto';
    window.scrollTo({ top: Math.max(0, host.getBoundingClientRect().top + scrollY - 350), behavior: 'instant' });
    await frame(); await frame();
    const run = async (row, component, arrow, targetRow, targetComponent, value) => {
      await choose(row, component);
      window.scrollTo({ top: Math.max(0, host.getBoundingClientRect().top + scrollY - 350), behavior: 'instant' });
      await frame();
      key(value);
      await until(() => host.querySelector('.tabulator-editing input')?.value === value);
      const from = cell(row, component), target = cell(targetRow, targetComponent);
      const before = snapshot(from, target), beforeRenders = renders;
      const beforeFocus = focusCalls.length;
      const beforeMutations = { ...observerStats };
      key(arrow);
      const keydown = snapshot(from, target);
      await frame(); const frame1 = snapshot(from, target);
      await frame(); const frame2 = snapshot(from, target);
      await until(() => !host.querySelector('[data-pp5-saving]') && from.textContent === `${value}.00`);
      await frame(); const response = snapshot(from, target);
      diagnostics.push({ arrow, before, keydown, frame1, frame2, response,
        sameSourceNode: from === cell(row, component), sameTargetNode: target === cell(targetRow, targetComponent),
        renders: renders - beforeRenders, focusCalls: focusCalls.slice(beforeFocus),
        mutations: { childList: observerStats.childList - beforeMutations.childList,
          classes: observerStats.classes - beforeMutations.classes,
          selectedClasses: observerStats.selectedClasses - beforeMutations.selectedClasses } });
      for (const [phase, sample] of Object.entries({ keydown, frame1, frame2, response })) {
        assert(sample.window.every((value, index) => near(value, before.window[index])), `${arrow} ${phase}: document stays still`);
        assert(sample.holder.every((value, index) => near(value, before.holder[index])), `${arrow} ${phase}: visible holder stays still`);
        assert(near(sample.host.top, before.host.top) && near(sample.host.height, before.host.height),
          `${arrow} ${phase}: host geometry stays still`);
        assert(near(sample.currentRow.top, before.currentRow.top) && near(sample.currentRow.height, before.currentRow.height)
          && near(sample.target.width, before.target.width), `${arrow} ${phase}: row and column geometry stay still`);
      }
      assert(response.focus === `score_${targetComponent}` && response.editors === 0,
        `${arrow}: target retains focus without an editor after response`);
      assert(from === cell(row, component) && target === cell(targetRow, targetComponent),
        `${arrow}: reconciliation retains score cell DOM nodes`);
      assert(focusCalls.slice(beforeFocus).filter(value => value === `score_${targetComponent}`).length === 1,
        `${arrow}: target receives one focus call`);
    };
    await run(0, 10, 'ArrowRight', 0, 11, '5');
    await run(0, 10, 'ArrowDown', 1, 10, '6');
    const rapid = async (vertical, label) => {
      await choose(0, 10);
      window.scrollTo({ top: Math.max(0, host.getBoundingClientRect().top + scrollY - 350), behavior: 'instant' });
      await frame();
      const start = snapshot(cell(0, 10), cell(0, 10));
      for (let n = 0; n < 4; n++) {
        key(String(n + 5));
        await until(() => host.querySelector('.tabulator-editing input')?.value === String(n + 5));
        key(vertical ? 'ArrowDown' : 'ArrowRight');
        await frame();
        const row = vertical ? Math.min(n + 1, 3) : 0;
        const component = vertical ? 10 : Math.min(n + 1, 3) + 10;
        const sample = snapshot(cell(row, component), cell(row, component));
        assert(sample.focus === `score_${component}` && sample.editors === 0, `${label} ${n + 1}: immediate target focus`);
        assert(sample.window.every((value, index) => near(value, start.window[index]))
          && sample.holder.every((value, index) => near(value, start.holder[index])),
        `${label} ${n + 1}: no page or holder scroll`);
      }
      await until(() => !host.querySelector('[data-pp5-saving]'));
      await frame();
      const final = snapshot(cell(vertical ? 3 : 0, vertical ? 10 : 13), cell(vertical ? 3 : 0, vertical ? 10 : 13));
      assert(final.focus === (vertical ? 'score_10' : 'score_13')
        && final.window.every((value, index) => near(value, start.window[index]))
        && final.holder.every((value, index) => near(value, start.holder[index])),
      `${label}: delayed responses leave the latest cell and viewport in place`);
      diagnostics.push({ label, start, final });
    };
    await rapid(false, '5 → 6 → 7 → 8');
    await rapid(true, '5 ↓ 6 ↓ 7 ↓ 8');
    await choose(0, 10);
    window.scrollTo({ top: Math.max(0, host.getBoundingClientRect().top + scrollY - 350), behavior: 'instant' });
    await frame();
    key('5');
    const sameInput = host.querySelector('.tabulator-editing input');
    sameInput.value = '5.00';
    const sameCell = cell(0, 10), sameBefore = snapshot(sameCell, cell(0, 11));
    key('ArrowRight');
    const sameMutations = [];
    const sameObserver = new MutationObserver(records => sameMutations.push(...records));
    sameObserver.observe(sameCell, { childList: true, subtree: true });
    await until(() => !host.querySelector('[data-pp5-saving]'));
    await frame(); sameObserver.disconnect();
    const sameAfter = snapshot(sameCell, cell(0, 11));
    assert(sameCell.textContent === '5.00' && sameMutations.length === 0,
      'Identical authoritative literal does not redraw its score cell');
    assert(sameAfter.window.every((value, index) => near(value, sameBefore.window[index]))
      && sameAfter.holder.every((value, index) => near(value, sameBefore.holder[index]))
      && near(sameAfter.currentRow.height, sameBefore.currentRow.height),
    'Summary-only reconciliation preserves viewport and row height');
    await choose(0, 10);
    const selectionMutations = observerStats.classes;
    key('ArrowRight'); await frame();
    assert(observerStats.classes - selectionMutations <= 3,
      'Single-cell Arrow selection touches only the old and new cell classes');
    assert(host.querySelectorAll('.tabulator-range-selected').length === 1
      && cell(0, 11).classList.contains('tabulator-range-selected'),
    'Single-cell Arrow selection paints only the new score');
    const holder = host.querySelector('.tabulator-tableholder');
    host.style.width = '450px';
    await frame();
    await choose(0, 10);
    window.scrollTo({ top: Math.max(0, host.getBoundingClientRect().top + scrollY - 350), behavior: 'instant' });
    await frame();
    const horizontalWindow = [scrollX, scrollY];
    for (let n = 0; n < 3; n++) { key('ArrowRight'); await frame(); }
    assert(holder.scrollLeft > 0 && [scrollX, scrollY].every((value, index) => near(value, horizontalWindow[index])),
      'Offscreen horizontal move scrolls only the holder');
    for (let n = 0; n < 3; n++) { key('ArrowLeft'); await frame(); }
    const first = cell(0, 10).getBoundingClientRect();
    const frozen = cell(0, 10).closest('.tabulator-row').querySelector('.tabulator-frozen-left').getBoundingClientRect();
    assert(first.left >= frozen.right - 0.6 && [scrollX, scrollY].every((value, index) => near(value, horizontalWindow[index])),
      'First score stays clear of frozen identity without document scroll');
    holder.style.height = '100px';
    await frame();
    await choose(0, 10);
    window.scrollTo({ top: Math.max(0, host.getBoundingClientRect().top + scrollY - 350), behavior: 'instant' });
    await frame();
    const verticalWindow = [scrollX, scrollY], verticalLeft = holder.scrollLeft;
    for (let n = 0; n < 3; n++) { key('ArrowDown'); await frame(); }
    assert(holder.scrollTop > 0 && near(holder.scrollLeft, verticalLeft)
      && [scrollX, scrollY].every((value, index) => near(value, verticalWindow[index])),
    'Offscreen vertical move scrolls only the holder');
    diagnostics.push({ offscreen: { horizontalWindow, verticalWindow,
      holder: [holder.scrollLeft, holder.scrollTop], frozenRight: frozen.right, firstScoreLeft: first.left } });
    await choose(0, 10);
    key('5'); key('ArrowRight'); await frame();
    holder.scrollLeft = 245; // Simulate a user moving the viewport during the delayed save.
    const manuallyMoved = holder.scrollLeft;
    await until(() => !host.querySelector('[data-pp5-saving]'));
    await frame();
    assert(near(holder.scrollLeft, manuallyMoved) && document.activeElement === cell(0, 11),
      'Delayed response does not reclaim a viewport moved by the user');
    output.textContent = `PASS: ${checks.length} SYNTHETIC visual checks (layout shifts: ${shifts}; Gradebook sources: ${gradebookShifts})\n${checks.join('\n')}\nTRACE ${JSON.stringify(diagnostics)}`;
    document.title = `PASS ${checks.length} — synthetic visual stability`;
  } catch (error) {
    output.textContent = `FAIL after ${checks.length} visual checks: ${error.message}\nTRACE ${JSON.stringify(diagnostics)}`;
    document.title = 'FAIL — visual stability';
  } finally {
    observer.disconnect(); layoutObserver?.disconnect();
    if (table) table.off('renderComplete', countRender);
    HTMLElement.prototype.focus = originalFocus;
  }
})();
