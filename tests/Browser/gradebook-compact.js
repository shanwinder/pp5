// Synthetic presentation/adapter checks; trusted input is verified separately.
(async () => {
  const output = document.getElementById('browser-results'), checks = [], requests = [];
  const assert = (okay, label) => { if (!okay) throw new Error(label); checks.push(label); };
  const until = async predicate => {
    for (let i = 0; i < 400; i++) { if (predicate()) return; await new Promise(r => setTimeout(r, 20)); }
    throw new Error('Timed out');
  };
  const host = document.getElementById('gradebook-tabulator');
  const paste = value => { const data = new DataTransfer(); data.setData('text/plain', value);
    document.activeElement.dispatchEvent(new ClipboardEvent('paste', {clipboardData:data,bubbles:true,cancelable:true})); };
  const rows = () => [...host.querySelectorAll('.tabulator-row')];
  const at = (row, field) => rows()[row].querySelector(`[tabulator-field="${field}"]`);
  const key = value => document.activeElement.dispatchEvent(new KeyboardEvent('keydown', {key:value,bubbles:true,cancelable:true}));
  const choose = async (row, field) => { at(row,field).click(); await until(() => document.activeElement === at(row,field)); };
  const originalFetch = window.fetch;
  window.fetch = async (...args) => {
    const item = {url:String(args[0]), body:args[1]?.body, at:performance.now()}; requests.push(item);
    const response = await originalFetch(...args);
    if (item.url.endsWith('/score')) await new Promise(r => setTimeout(r, 500));
    item.resolved = performance.now(); return response;
  };
  try {
    await until(() => document.querySelector('.pp5-gradebook').hidden);
    const expected = ['42/50','42.5/50','25.75/35.5','0/50','0/0','12345678901234567890.25/12345678901234567890.5'];
    assert(host.querySelectorAll('.tabulator-col.pp5-grid-summary').length === 1, 'one physical summary column');
    assert(host.querySelector('.tabulator-col.pp5-grid-summary').textContent === 'คะแนนที่บันทึก', 'summary heading');
    assert(!host.querySelector('[tabulator-field="total"],[tabulator-field="max"],[tabulator-field="count"],[tabulator-field="complete"]'), 'old columns absent physically');
    assert(!document.getElementById('gradebook-components') && !document.querySelector('.pp5-gradebook-heading'), 'overview/score heading strips absent');
    expected.forEach((value,i) => {
      assert(at(i,'summaryDisplay').textContent === value, `exact JS summary ${value}`);
      assert(document.getElementById(`row-1-${i+1}-display`).textContent === value, `fallback parity ${value}`);
    });
    for (const row of document.querySelectorAll('.pp5-gradebook tbody tr')) {
      assert(row.querySelectorAll('td.pp5-gradebook-summary').length === 1, 'one fallback summary cell per row');
      assert(row.children.length === 6, 'fallback header/cell count agrees');
    }
    const bootstrap = JSON.parse(document.getElementById('gradebook-grid-data').textContent);
    assert(bootstrap.rows[0].scores[10]===null && at(0,'score_10').textContent==='', 'summary projection does not convert underlying NULL score to zero');
    for (const row of bootstrap.rows) for (const suffix of ['total','max','count','complete'])
      assert(document.getElementById(`row-1-${row.enrollmentId}-${suffix}`).closest('[hidden]'), `authoritative ${suffix} metadata remains non-column`);
    for (const id of ['gradebook-help','gradebook-range-tool','gradebook-range-actions','gradebook-fill-value','gradebook-fill-submit','gradebook-clear-submit'])
      assert(!document.getElementById(id), `retired UI ${id} absent from DOM and tab order`);
    assert(!document.getElementById('gradebook-guidance').textContent.includes('เครื่องมือช่วงคะแนน'), 'accessible guidance never advertises removed tools');
    await choose(0,'score_10'); key('5'); key('ArrowRight');
    await until(() => document.activeElement===at(0,'score_11') && window.__gradebookTiming.snapshot().pendingSingles===1);
    const before = at(0,'summaryDisplay').textContent;
    paste('5');
    assert(host.hasAttribute('aria-busy') && requests.filter(r=>r.url.endsWith('/scores/batch')).length===0, 'Paste waits behind pending single');
    assert(at(0,'summaryDisplay').textContent===before, 'no optimistic summary');
    await until(() => !host.hasAttribute('aria-busy') && requests.length===2);
    assert(requests[1].url.endsWith('/scores/batch') && requests[1].at>=requests[0].resolved, 'one Paste batch serialized after single');
    assert(at(0,'summaryDisplay').textContent==='987.65/432.1', 'batch refresh replaces single summary deterministically');
    assert(document.activeElement===at(0,'score_11'), 'Paste preserves typing anchor and selected rectangle');
    const current = {...window.__gradebookTiming.summaries()[0]};
    await window.__gradebookTiming.applySummary(1,{total:'1.00',max:'2.00',count:'0 / 2',complete:'ยังไม่ครบ'},0);
    assert(JSON.stringify(window.__gradebookTiming.summaries()[0])===JSON.stringify(current) && at(0,'summaryDisplay').textContent==='987.65/432.1', 'fixture-only stale revision cannot overwrite any newer authoritative summary field');
    key('8'); assert(host.querySelector('input')?.value==='8', 'typing immediately after Paste works'); key('Escape');
    paste('21');
    await until(() => document.getElementById('gradebook-batch-status').dataset.batchState==='error');
    assert(document.getElementById('gradebook-batch-status').textContent.includes('คะแนนไม่ถูกต้อง'), 'failed Paste exposes correction reason in live feedback');
    assert(at(0,'summaryDisplay').textContent==='987.65/432.1', 'failed Paste keeps confirmed summary');
    key('Delete');
    await until(() => !host.hasAttribute('aria-busy') && at(0,'score_11').textContent==='');
    assert(requests.length===4 && JSON.parse(requests[3].body.get('batch')).values[0][0]==='', 'Clear sends exactly one atomic blank batch');
    assert(document.activeElement===at(0,'score_11') && host.querySelectorAll('.tabulator-range-selected').length===1, 'Clear keeps selection and score focus');
    paste('5');
    const outside=document.querySelector('.pp5-gradebook-toolbar a'); outside.focus();
    await until(() => !host.hasAttribute('aria-busy') && requests.length===5);
    assert(document.activeElement===outside && host.querySelectorAll('.tabulator-range-selected').length===1,
      'late batch response preserves deliberate external focus and usable selection');
    assert(document.documentElement.scrollWidth<=innerWidth+1, 'no document horizontal overflow');
    output.textContent=`PASS: ${checks.length} SYNTHETIC compact checks\n${checks.join('\n')}`; document.title='PASS — compact Gradebook';
  } catch(error) { output.textContent=`FAIL after ${checks.length}: ${error.message}\n${checks.join('\n')}`; document.title='FAIL — compact Gradebook'; }
  finally { window.fetch=originalFetch; }
})();
