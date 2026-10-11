// Synthetic presentation/adapter checks; trusted input is verified separately.
(async () => {
  const output = document.getElementById('browser-results'), checks = [], requests = [];
  const assert = (okay, label) => { if (!okay) throw new Error(label); checks.push(label); };
  const until = async predicate => {
    for (let i = 0; i < 400; i++) { if (predicate()) return; await new Promise(r => setTimeout(r, 20)); }
    throw new Error('Timed out');
  };
  const host = document.getElementById('gradebook-tabulator'), tool = document.getElementById('gradebook-range-tool');
  const form = document.getElementById('gradebook-range-actions'), fill = document.getElementById('gradebook-fill-value');
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
      assert(row.children.length === 5, 'fallback header/cell count agrees');
    }
    const bootstrap = JSON.parse(document.getElementById('gradebook-grid-data').textContent);
    assert(bootstrap.rows[0].scores[10]===null && at(0,'score_10').textContent==='', 'summary projection does not convert underlying NULL score to zero');
    for (const row of bootstrap.rows) for (const suffix of ['total','max','count','complete'])
      assert(document.getElementById(`row-1-${row.enrollmentId}-${suffix}`).closest('[hidden]'), `authoritative ${suffix} metadata remains non-column`);
    assert(!tool.open && form.getClientRects().length === 0, 'no permanent range bar');
    assert(document.getElementById('gradebook-fill-submit').disabled && document.getElementById('gradebook-clear-submit').disabled, 'no range disables both commands');
    const y = host.getBoundingClientRect().top;
    tool.querySelector('summary').click();
    assert(tool.open && Math.abs(host.getBoundingClientRect().top-y) < .5, 'open panel overlays without moving grid');
    assert(form.getBoundingClientRect().left >= 0 && form.getBoundingClientRect().right <= innerWidth, 'popup contained');
    fill.value='5'; form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
    assert(requests.length===0 && tool.open && document.getElementById('gradebook-range-feedback').textContent.includes('เลือกช่วง'), 'invalid no-range command sends no write and remains correctable');
    fill.focus(); key('Escape');
    assert(!tool.open && document.activeElement===tool.querySelector('summary'), 'Escape closes and restores trigger focus');
    await choose(0,'score_10'); key('5'); key('ArrowRight');
    await until(() => document.activeElement===at(0,'score_11') && window.__gradebookTiming.snapshot().pendingSingles===1);
    const before = at(0,'summaryDisplay').textContent;
    tool.querySelector('summary').click(); fill.focus(); fill.value='5';
    form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
    assert(host.hasAttribute('aria-busy') && requests.filter(r=>r.url.endsWith('/scores/batch')).length===0, 'Fill waits behind pending single');
    assert(at(0,'summaryDisplay').textContent===before, 'no optimistic summary');
    await until(() => !host.hasAttribute('aria-busy') && requests.length===2);
    assert(requests[1].url.endsWith('/scores/batch') && requests[1].at>=requests[0].resolved, 'one Fill batch serialized after single');
    assert(at(0,'summaryDisplay').textContent==='987.65/432.1', 'batch refresh replaces single summary deterministically');
    assert(document.activeElement===at(0,'score_11') && !tool.open, 'Fill preserves typing anchor and closes panel only on success');
    const current = {...window.__gradebookTiming.summaries()[0]};
    await window.__gradebookTiming.applySummary(1,{total:'1.00',max:'2.00',count:'0 / 2',complete:'ยังไม่ครบ'},0);
    assert(JSON.stringify(window.__gradebookTiming.summaries()[0])===JSON.stringify(current) && at(0,'summaryDisplay').textContent==='987.65/432.1', 'fixture-only stale revision cannot overwrite any newer authoritative summary field');
    key('8'); assert(host.querySelector('input')?.value==='8', 'typing immediately after Fill works'); key('Escape');
    tool.querySelector('summary').click(); fill.focus(); fill.value='21';
    form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
    await until(() => document.getElementById('gradebook-batch-status').dataset.batchState==='error');
    assert(tool.open && document.getElementById('gradebook-range-feedback').textContent.includes('คะแนนไม่ถูกต้อง'), 'failed Fill remains open with correction reason');
    assert(at(0,'summaryDisplay').textContent==='987.65/432.1', 'failed Fill keeps confirmed summary');
    fill.value='5'; document.getElementById('gradebook-clear-submit').click();
    await until(() => !host.hasAttribute('aria-busy') && at(0,'score_11').textContent==='');
    assert(requests.length===4 && JSON.parse(requests[3].body.get('batch')).values[0][0]==='', 'Clear sends exactly one atomic blank batch');
    assert(document.activeElement===at(0,'score_11') && host.querySelectorAll('.tabulator-range-selected').length===1, 'Clear keeps selection and score focus');
    tool.querySelector('summary').click(); fill.focus(); fill.value='5';
    form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
    const outside=document.querySelector('.pp5-gradebook-toolbar a'); outside.focus();
    await until(() => !host.hasAttribute('aria-busy') && requests.length===5);
    assert(document.activeElement===outside && tool.open && host.querySelectorAll('.tabulator-range-selected').length===1,
      'late batch response preserves deliberate external focus and usable selection');
    assert(document.documentElement.scrollWidth<=innerWidth+1, 'no document horizontal overflow');
    output.textContent=`PASS: ${checks.length} SYNTHETIC compact checks\n${checks.join('\n')}`; document.title='PASS — compact Gradebook';
  } catch(error) { output.textContent=`FAIL after ${checks.length}: ${error.message}\n${checks.join('\n')}`; document.title='FAIL — compact Gradebook'; }
  finally { window.fetch=originalFetch; }
})();
