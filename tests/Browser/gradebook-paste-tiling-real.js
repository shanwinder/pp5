// Task 6S TRUSTED operator suite. No dispatched events, test focus or assigned selection.
// /?mode=tiling&tests=tiling-real (isolated response fixture, not authenticated DB proof).
(async () => {
  'use strict';
  const output=document.getElementById('browser-results'),host=document.getElementById('gradebook-tabulator');
  const passed=[],events=[],requests=[];
  const originalFetch=window.fetch;
  window.fetch=async(...args)=>{const item={url:String(args[0]),body:args[1]?.body};requests.push(item);return originalFetch(...args);};
  for(const type of ['pointerdown','pointermove','pointerup','keydown','copy','paste'])document.addEventListener(type,event=>{
    if(event.isTrusted || type==='copy' || type==='paste')events.push({type,event});
  },true);
  const wait=async(instruction,predicate,type=null)=>{
    const start=events.length;output.textContent=`WAIT: ${instruction}\n${passed.join('\n')}`;document.title=`WAIT — ${instruction}`;
    for(let i=0;i<3000;i++){if(predicate()&&(!type||events.slice(start).some(e=>e.type===type)))return;await new Promise(r=>setTimeout(r,20));}
    throw new Error(instruction);
  };
  const assert=(okay,label)=>{if(!okay)throw new Error(label);passed.push(label);};
  const rows=()=>[...host.querySelectorAll('.tabulator-row')];
  const at=(r,c)=>rows()[r]?.querySelector(`[tabulator-field="score_${c}"]`);
  const selected=()=>[...host.querySelectorAll('.tabulator-range-selected')];
  const status=()=>document.getElementById('gradebook-batch-status');
  const batches=()=>requests.filter(r=>r.url.endsWith('/scores/batch'));
  const sent=()=>JSON.parse(batches().at(-1).body.get('batch'));
  const view=()=>{const h=host.querySelector('.tabulator-tableholder');return [scrollX,scrollY,h.scrollLeft,h.scrollTop];};
  const click=async(r,c)=>{
    await wait(`Click row ${r+1}, score ${c}`,()=>document.activeElement===at(r,c)&&selected().length===1,'pointerup');
    assert(document.activeElement===at(r,c),'trusted click selects and focuses score');
  };
  const drag=async(r,c,toR,toC)=>{
    const count=(Math.abs(r-toR)+1)*(Math.abs(c-toC)+1),start=events.length;
    await wait(`REAL drag row ${r+1}/score ${c} to row ${toR+1}/score ${toC}`,()=>selected().length===count&&document.activeElement===at(toR,toC),'pointerup');
    assert(events.slice(start).some(e=>e.type==='pointerdown')&&events.slice(start).some(e=>e.type==='pointermove')&&events.slice(start).some(e=>e.type==='pointerup'),'trusted drag paints entire score-only rectangle');
  };
  const paste=async(instruction,expected)=>{
    const start=batches().length,position=view();
    await wait(instruction,()=>batches().length===start+1&&!host.hasAttribute('aria-busy'),'paste');
    assert(status().dataset.batchState==='saved'&&batches().length===start+1,'exactly one authoritative atomic POST');
    assert(JSON.stringify(sent().values)===JSON.stringify(expected),'expanded matrix is exact');
    assert(selected().length===expected.length*expected[0].length && document.activeElement===at(0,10),'GB-PASTE-016 — target selection and top-left score focus retained');
    assert(view().every((n,i)=>Math.abs(n-position[i])<.6),'no document/holder scroll during save');
  };
  try {
    await wait('Grid ready',()=>document.querySelector('.pp5-gradebook').hidden&&rows().length===5);
    assert(!document.getElementById('gradebook-help')&&!document.getElementById('gradebook-range-actions'),'retired UI absent');
    await click(0,10);await paste('Put 8 on clipboard, then press native Paste',[['8']]);
    await wait('Press native Copy shortcut on saved 8.00',()=>events.some(e=>e.type==='copy'),'copy');
    assert(events.filter(e=>e.type==='copy').at(-1).event.clipboardData.getData('text/plain')==='8.00','scalar native TSV copy');
    await drag(0,10,2,13);await paste('Press native Paste to repeat 8.00 into 3×4',Array.from({length:3},()=>Array(4).fill('8.00')));
    assert(sent().enrollment_ids.join(',')==='101,102,103'&&sent().component_ids.join(',')==='10,11,12,13','GB-PASTE-012 — scalar native repeat uses stable IDs');
    await click(0,10);await paste('Put 8\t9\n7\t10 on clipboard, then press native Paste',[['8','9'],['7','10']]);
    const copyStart=events.length;
    await wait('Press native Copy shortcut on selected 2×2',()=>events.slice(copyStart).some(e=>e.type==='copy'),'copy');
    assert(events.filter(e=>e.type==='copy').at(-1).event.clipboardData.getData('text/plain')==='8.00\t9.00\n7.00\t10.00','exact native 2×2 TSV');
    await drag(0,10,3,14);
    const pattern=[['8.00','9.00','8.00','9.00','8.00'],['7.00','10.00','7.00','10.00','7.00'],['8.00','9.00','8.00','9.00','8.00'],['7.00','10.00','7.00','10.00','7.00']];
    await paste('Press native Paste to tile copied 2×2 into 4×5',pattern);
    assert(sent().enrollment_ids.join(',')==='101,102,103,104'&&sent().component_ids.join(',')==='10,11,12,13,14','GB-PASTE-013 — horizontal partial edge and vertical repetition exact');
    await drag(3,14,0,10);await paste('Press native Paste into reverse 4×5 selection',pattern);
    await wait('Type 8 immediately without click',()=>host.querySelector('input')?.value==='8','keydown');
    assert(document.activeElement===at(0,10).querySelector('input'),'immediate typing opens top-left replacement editor');
    await wait('Press Escape to cancel',()=>!host.querySelector('input')&&document.activeElement===at(0,10),'keydown');
    await drag(3,14,0,10);
    const mixed=[['','0','0.00','','0'],['0.00','','8','0.00',''],['','0','0.00','','0'],['0.00','','8','0.00','']];
    await paste('Put \t0\t0.00\n0.00\t\t8 on clipboard, then press native Paste',mixed);
    assert(at(0,10).textContent===''&&at(0,11).textContent==='0.00'&&at(0,12).textContent==='0.00','GB-PASTE-017 — repeated NULL and real zero distinct');
    await drag(0,10,0,11);const before=requests.length;
    await wait('Put 8\t9\n7\t10 on clipboard, then native Paste into too-small 1×2',()=>status().textContent.includes('เล็กกว่า'),'paste');
    assert(requests.length===before&&selected().length===2&&at(0,10).textContent==='','GB-PASTE-014 — insufficient rectangle rejects with no request, clipping or partial score');
    await click(0,11);
    await wait('Press F2 for explicit edit',()=>host.querySelector('input')?.value==='0.00','keydown');
    const input=host.querySelector('input'),caret=input.selectionStart,start=requests.length;
    await wait('Press Left inside existing input',()=>input.selectionStart===caret-1,'keydown');
    assert(document.activeElement===input&&at(0,11).querySelector('input')===input&&requests.length===start,'same explicit input, caret and zero POST');
    await wait('Press Right inside input',()=>input.selectionStart===caret,'keydown');
    await wait('Put 8 on clipboard, then native Paste inside input',()=>input.value.includes('8'),'paste');
    assert(document.activeElement===input&&requests.length===start,'native editor text paste sends no range batch');
    await wait('Press Escape to cancel editor text',()=>!host.querySelector('input')&&document.activeElement===at(0,11),'keydown');
    assert(at(0,11).textContent==='0.00'&&requests.length===start,'editor cancel restores saved value without mutation');
    output.textContent=`PASS: ${passed.length} TRUSTED Task 6S tiling checks\n${passed.join('\n')}`;document.title='PASS — trusted Task 6S tiling';
  }catch(error){output.textContent=`FAIL after ${passed.length}: ${error.message}\n${passed.join('\n')}`;document.title='FAIL — trusted Task 6S tiling';}
  finally{window.fetch=originalFetch;}
})();
