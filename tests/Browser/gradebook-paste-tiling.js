// Task 6S SYNTHETIC integration checks. Native input has its own trusted suite.
// Run /?mode=tiling&tests=tiling; expanded-limit variant: mode=stress&tests=tiling.
(async () => {
  'use strict';
  const output = document.getElementById('browser-results'), host = document.getElementById('gradebook-tabulator');
  const checks = [], requests = [];
  const assert = (okay, label) => { if (!okay) throw new Error(label); checks.push(label); };
  const until = async (predicate, label="interaction") => {
    for (let i=0;i<500;i++) { if(predicate()) return; await new Promise(r=>setTimeout(r,20)); }
    throw new Error(`Timed out: ${label}; active=${document.activeElement?.getAttribute('tabulator-field')}; rows=${host.querySelectorAll('.tabulator-row').length}; scroll=${host.querySelector('.tabulator-tableholder')?.scrollTop}`);
  };
  const originalFetch = window.fetch;
  let delaySingles = false;
  window.fetch = async (...args) => {
    const item = {url:String(args[0]),body:args[1]?.body,at:performance.now()}; requests.push(item);
    const response = await originalFetch(...args);
    if (delaySingles && item.url.endsWith('/score')) await new Promise(r=>setTimeout(r,500));
    item.resolved = performance.now(); return response;
  };
  const status = () => document.getElementById('gradebook-batch-status');
  const selected = () => [...host.querySelectorAll('.tabulator-range-selected')];
  const key = (key, extra={}) => document.activeElement.dispatchEvent(new KeyboardEvent('keydown',{key,bubbles:true,cancelable:true,...extra}));
  const clipboard = (value, target=document.activeElement) => {
    const data = new DataTransfer(); data.setData('text/plain',value);
    const event = new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true}); target.dispatchEvent(event); return event;
  };
  const viewport = () => { const holder=host.querySelector('.tabulator-tableholder');return [scrollX,scrollY,holder.scrollLeft,holder.scrollTop]; };
  let table;
  const at = (r,c) => table.getRows('active')[r].getCell(`score_${c}`).getElement();
  const choose = async (r,c) => { const cell=at(r,c); cell.dispatchEvent(new MouseEvent('mousedown',{bubbles:true})); cell.dispatchEvent(new MouseEvent('mouseup',{bubbles:true})); cell.click(); await until(()=>document.activeElement===at(r,c), 'choose '+r+':'+c); };
  const drag = async (r,c,endR,endC) => {
    at(r,c).dispatchEvent(new MouseEvent('mousedown',{bubbles:true}));
    at(endR,endC).dispatchEvent(new MouseEvent('mousemove',{bubbles:true}));
    at(endR,endC).dispatchEvent(new MouseEvent('mouseup',{bubbles:true}));
    await until(()=>document.activeElement===at(endR,endC));
  };
  const batch = () => requests.filter(r=>r.url.endsWith('/scores/batch'));
  const sent = () => JSON.parse(batch().at(-1).body.get('batch'));
  const success = async (source, expected, r=0,c=10, preserve=true) => {
    const start = batch().length, before=at(r,c).textContent, box=viewport();
    assert(clipboard(source).defaultPrevented, 'paste uses native text/plain path');
    assert(at(r,c).textContent===before, 'GB-PASTE-009 — no optimistic committed score');
    await until(()=>batch().length===start+1&&!host.hasAttribute('aria-busy'));
    assert(status().dataset.batchState==='saved' && batch().length===start+1, 'exactly one successful atomic batch');
    assert(JSON.stringify(sent().values)===JSON.stringify(expected), 'GB-PASTE-012 — complete expanded matrix matches independent modulo pattern');
    assert(sent().enrollment_ids[0]===101+r && sent().component_ids[0]===c, 'stable enrollment/component IDs, not row numbers');
    assert(selected().length===expected.length*expected[0].length && document.activeElement===at(r,c), 'GB-PASTE-016 — full target retained with top-left focus');
    if(preserve)assert(viewport().every((n,i)=>Math.abs(n-box[i])<.6), 'no unexpected document or holder scroll');
    assert(table.getRows('active')[r].getData().summaryDisplay==='987.65/432.1', 'authoritative total/max, never client arithmetic');
    assert(table.getRows('active')[r].getData().count==='2 / 2' && table.getRows('active')[r].getData().complete==='ครบ', 'all server summary fields retained');
  };
  const reject = async (source, reason) => {
    const start=requests.length, values=table.getRows('active').map(r=>r.getData()), before=JSON.stringify(values), selection=selected();
    clipboard(source);
    await new Promise(r=>setTimeout(r,20));
    assert(requests.length===start && JSON.stringify(values)===before, 'GB-PASTE-014 — rejection sends no POST and changes no score or summary');
    assert(status().dataset.batchState==='error' && status().textContent.includes(reason), 'rejection explains the full-operation error');
    assert(selected().length===selection.length && selection.every(c=>c.classList.contains('tabulator-range-selected')), 'rejection preserves selection');
  };
  try {
    await until(()=>document.querySelector('.pp5-gradebook').hidden);
    table=Tabulator.findTable(host)[0];
    if(new URLSearchParams(location.search).get('mode')==='stress') {
      await choose(0,1000);
      at(0,1000).dispatchEvent(new MouseEvent('mousedown',{bubbles:true}));
      // Virtual endpoint becomes rendered while the fixed start remains logical.
      await table.scrollToRow(51, 'center', true);
      await until(()=>at(50,1039).isConnected, 'virtual endpoint connected');
      const last=at(50,1039);
      last.dispatchEvent(new MouseEvent('mousemove',{bubbles:true}));last.dispatchEvent(new MouseEvent('mouseup',{bubbles:true}));
      await until(()=>document.activeElement===last, 'virtual endpoint focus');
      await reject('8','2000');
      assert(batch().length===0, 'GB-PASTE-015 — 1×1 source expanded to 51×40=2040 is rejected before transport');
      const ordinal=last.closest('.tabulator-row').querySelector('[tabulator-field="rowNumber"]');
      assert(ordinal.textContent==='51', 'virtual row ordinal follows displayed position, not renderer index');
    } else {
      const fields=[...host.querySelectorAll('.tabulator-col')].map(c=>c.getAttribute('tabulator-field'));
      assert(JSON.stringify(fields)===JSON.stringify(['rowNumber','studentCode','name','score_10','score_11','score_12','score_13','score_14','summaryDisplay']), 'new column order with no row-type column');
      assert(table.getRows().every((row,i)=>row.getCell('rowNumber').getValue()===i+1), 'sequential ordinals include historical rows');
      assert(table.getRows()[0].getCell('studentCode').getValue()==='000001', 'student code retains leading zeros as string');
      assert(table.getRows()[0].getCell('name').getValue().startsWith('เด็กหญิงชื่อภาษาไทยยาว'), 'full long Thai name and prefix retained');
      const historical=table.getRows()[4];
      assert(historical.getData().rowType==='HISTORICAL' && historical.getElement().classList.contains('pp5-grid-historical') && historical.getElement().getAttribute('aria-label').includes('แถวประวัติ อ่านอย่างเดียว'), 'historical metadata, shade and accessible row identity');
      assert(historical.getCell('score_10').getElement().getAttribute('aria-readonly')==='true', 'historical score announces read-only');
      assert(table.getColumns().filter(c=>c.getDefinition().frozen).map(c=>c.getField()).join(',')==='rowNumber', 'single contiguous 56px frozen column');
      await choose(0,10); await success('8',[['8']]); // A
      await choose(0,10); await success('8\t9\n7\t10',[['8','9'],['7','10']]); // B
      await drag(0,10,2,13); await success('8',Array.from({length:3},()=>Array(4).fill('8'))); // C
      await drag(0,10,3,14);
      await success('8\t9\n7\t10',[['8','9','8','9','8'],['7','10','7','10','7'],['8','9','8','9','8'],['7','10','7','10','7']]); // D
      assert(sent().enrollment_ids.join(',')==='101,102,103,104' && sent().component_ids.join(',')==='10,11,12,13,14', 'GB-PASTE-013 — 4×5 partial-edge target has exact stable IDs');
      await drag(1,11,0,10); await success('8\t9\n7\t10',[['8','9'],['7','10']]); // E reverse
      await drag(0,10,0,11); await reject('8\t9\n7\t10','เล็กกว่า'); // F height
      await drag(0,10,1,10); await reject('8\t9\n7\t10','เล็กกว่า'); // F width
      await drag(0,10,2,12); await reject('8\t9\n7','จำนวนช่องเท่ากัน');
      await drag(3,14,0,10);
      const nullZero=[['','0','0.00','','0'],['0.00','','0','0.00',''],['','0','0.00','','0'],['0.00','','0','0.00','']];
      await success('\t0\t0.00\n0.00\t\t0',nullZero);
      assert(at(0,10).textContent==='' && at(0,11).textContent==='0.00' && at(0,12).textContent==='0.00', 'GB-PASTE-017 — blanks repeat as NULL and both zero variants remain real zero');
      const before=table.getRows().map(r=>JSON.stringify(r.getData()));const count=batch().length;
      clipboard('8\t21\n7\t10');await until(()=>batch().length===count+1&&!host.hasAttribute('aria-busy'));
      assert(status().dataset.batchState==='error' && before.every((row,i)=>row===JSON.stringify(table.getRows()[i].getData())), 'server invalid maximum rejects whole expanded matrix without partial display');
      await drag(3,10,4,11); await reject('8','แถวประวัติ');
      await choose(4,10); await reject('8','ไม่สามารถวาง');
      await choose(3,14); await reject('8\t9','เกินช่อง');
      await choose(3,10); await reject('8\n9\n10','เกินช่อง');
      await choose(0,10);key('F2');const input=host.querySelector('input'),writes=requests.length;
      assert(input===document.activeElement,'explicit input owns focus');
      assert(!clipboard('8\t9\n7\t10',input).defaultPrevented && requests.length===writes, 'explicit editor paste stays native and never tiles');
      const caret=[input.selectionStart,input.selectionEnd];key('ArrowLeft');key('ArrowRight');
      assert(host.querySelector('input')===input && requests.length===writes && input.selectionStart===caret[0] && input.selectionEnd===caret[1], 'explicit caret events retain same input and no request');key('Escape');
      await choose(0,10);key('8');assert(host.querySelector('input')?.value==='8','immediate typing works');key('Escape');
    }
    output.textContent=`PASS: ${checks.length} SYNTHETIC Task 6S tiling checks\n${checks.join('\n')}`;document.title='PASS — Task 6S tiling';
  } catch(error) { output.textContent=`FAIL after ${checks.length}: ${error.message}\n${checks.join('\n')}`;document.title='FAIL — Task 6S tiling'; }
  finally { window.fetch=originalFetch; }
})();
