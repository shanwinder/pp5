// Trusted pointer/keyboard regression; run separately in writable and read-only fixtures.
(async () => {
  const output=document.getElementById('browser-results'), host=document.getElementById('gradebook-tabulator');
  const events=[], writes=[], passed=[];
  const originalFetch=window.fetch;
  window.fetch=(...args)=>{writes.push(String(args[0]));return originalFetch(...args);};
  for(const type of ['pointerup','keydown']) document.addEventListener(type,event=>{if(event.isTrusted)events.push({type,key:event.key});},true);
  const wait=async(text,predicate,type=null)=>{const start=events.length;output.textContent=`WAIT: ${text}`;for(let i=0;i<3000;i++){if(predicate()&&(!type||events.slice(start).some(e=>e.type===type)))return;await new Promise(r=>setTimeout(r,20));}throw new Error(text);};
  const assert=(okay,text)=>{if(!okay)throw new Error(text);passed.push(text);};
  try {
    await wait('grid',()=>document.querySelector('.pp5-gradebook').hidden);
    const live=document.getElementById('gradebook-range-status'),cell=host.querySelector('.tabulator-row [tabulator-field="score_10"]');
    assert(live.textContent==='', 'initial state does not announce cancellation');
    await wait('Click first score',()=>document.activeElement===cell&&host.querySelectorAll('.tabulator-range-selected').length===1,'pointerup');
    assert(live.textContent==='เลือก 1 แถว × 1 หัวข้อคะแนน','selected range announces in readable modes');
    await wait('Press Escape',()=>host.querySelectorAll('.tabulator-range-selected').length===0,'keydown');
    assert(live.textContent==='ยกเลิกการเลือกช่วงคะแนน'&&document.activeElement===cell&&!host.querySelector('input')&&writes.length===0,'Escape announces cancellation, preserves same active cell/focus, sends no request');
    let changes=0;const observer=new MutationObserver(records=>{changes+=records.length;});observer.observe(live,{childList:true,characterData:true,subtree:true});
    const start=events.length;
    await wait('Press Escape again with no selected range',()=>events.slice(start).some(e=>e.key==='Escape'),'keydown');
    await new Promise(r=>requestAnimationFrame(r));observer.disconnect();
    assert(changes===0&&document.activeElement===cell&&writes.length===0,'no-range Escape does not emit new announcement or change focus/write');
    assert(host.querySelectorAll('.tabulator-cell[tabindex="0"]').length===1,'one roving score stop');
    const tool=document.getElementById('gradebook-range-tool');
    if(tool) {
      const trigger=tool.querySelector('summary'),fill=document.getElementById('gradebook-fill-value');
      const y=host.getBoundingClientRect().top;
      assert(!tool.open,'tool remains closed after selection/cancellation');
      await wait('Click เครื่องมือช่วงคะแนน',()=>tool.open,'pointerup');
      assert(document.getElementById('gradebook-fill-submit').disabled&&document.getElementById('gradebook-clear-submit').disabled,'no range keeps commands disabled');
      await wait('Press Escape to close tool',()=>!tool.open&&document.activeElement===trigger,'keydown');
      await wait('Press Space to open tool',()=>tool.open,'keydown');
      await wait('Press Tab to Fill input',()=>document.activeElement===fill,'keydown');
      await wait('Press Escape from input',()=>!tool.open&&document.activeElement===trigger,'keydown');
      await wait('Press Enter to open tool',()=>tool.open,'keydown');
      await wait('Press Tab to Fill input',()=>document.activeElement===fill,'keydown');
      await wait('Press Shift+Tab back to trigger',()=>document.activeElement===trigger,'keydown');
      await wait('Press Space to close tool',()=>!tool.open,'keydown');
      assert(Math.abs(host.getBoundingClientRect().top-y)<.5&&host.querySelectorAll('.tabulator-range-selected').length===0&&writes.length===0,'pointer/Space/Enter/Tab/Escape preserve grid position, selection and zero writes without keyboard trap');
    } else {
      assert(!document.getElementById('gradebook-range-actions')&&!host.querySelector('.tabulator-editable'),'read-only has no range tool or writable cells');
    }
    output.textContent=`PASS: ${passed.length} TRUSTED live-feedback checks\n${passed.join('\n')}`;document.title='PASS — trusted live feedback';
  }catch(error){output.textContent=`FAIL: ${error.message}\n${passed.join('\n')}`;document.title='FAIL — trusted live feedback';}
  finally{window.fetch=originalFetch;}
})();
