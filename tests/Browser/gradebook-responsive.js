// Synthetic container-resize regression. Native viewport journeys are separate release evidence.
(async () => {
  const results = [], measurements = [];
  const until = async predicate => {
    for (let i=0;i<500;i++) { if(predicate()) return; await new Promise(r=>setTimeout(r,20)); }
    throw new Error('Timed out');
  };
  for (const frame of document.querySelectorAll('iframe')) {
    let checks=0;
    const assert=(okay,label)=>{if(!okay)throw new Error(label);checks++;};
    try {
      frame.scrollIntoView({block:'start'});
      await until(()=>frame.contentDocument?.querySelector('.pp5-gradebook')?.hidden);
      const d=frame.contentDocument,w=frame.contentWindow,host=d.getElementById('gradebook-tabulator');
      const table=w.Tabulator.findTable(host)[0],holder=host.querySelector('.tabulator-tableholder');
      await until(()=>table.modules.resizeTable.visible);
      const count=Number(frame.title.split(' ')[0]),requests=[];
      const fetch=w.fetch;w.fetch=(...args)=>{requests.push(args);return fetch(...args);};
      const cell=()=>table.getRows()[0].getCell('score_10').getElement();
      const widths=()=>table.getColumns().map(c=>c.getWidth());
      const key=value=>d.activeElement.dispatchEvent(new w.KeyboardEvent('keydown',{key:value,bubbles:true,cancelable:true}));
      const geometry=width=>{
        const cols=table.getColumns(),content=cols.reduce((sum,c)=>sum+c.getWidth(),0);
        const minimum=cols.reduce((sum,c)=>sum+c.getDefinition().minWidth,0);
        assert(w.innerWidth===width,'actual container viewport matches requested width');
        assert(d.documentElement.scrollWidth<=width+1,'no document overflow');
        assert(w.Tabulator.findTable(host).length===1,'one unchanged grid instance');
        assert(cols.filter(c=>c.getField().startsWith('score_')).length===count,'no hidden score columns');
        assert(cols.every(c=>c.getWidth()>=c.getDefinition().minWidth-.5),'practical minimum widths retained');
        if(minimum<=holder.clientWidth){
          assert(Math.abs(content-holder.clientWidth)<=cols.length,'fills container within native integer rounding per column');
          assert(holder.scrollWidth<=holder.clientWidth+2,'no unnecessary internal overflow');
        }else assert(holder.scrollWidth>holder.clientWidth,'minimum content scrolls internally');
        const summary=table.getRows()[0].getCell('summaryDisplay').getElement();
        assert(summary.scrollWidth<=summary.clientWidth+1,'long decimal summary fully fits');
        const identity=table.getRows()[0].getCell('identity').getElement();
        assert(Math.abs(identity.getBoundingClientRect().left-host.getBoundingClientRect().left)<=3,'identity stays frozen');
        if(count){
          const title=host.querySelector('[tabulator-field="score_10"] .tabulator-col-title');
          assert(w.getComputedStyle(title).whiteSpace==='normal' && title.scrollHeight<=title.clientHeight+1,'long Thai heading wraps without truncation');
        }
        measurements.push({count,width,outer:host.clientWidth,content,columns:widths(),holder:[holder.clientWidth,holder.scrollWidth],document:[d.documentElement.clientWidth,d.documentElement.scrollWidth],rowHeight:table.getRows()[0].getElement().getBoundingClientRect().height});
      };
      const resize=async width=>{
        frame.width=String(width);
        await until(()=>w.innerWidth===width);
        await until(()=>{
          const cols=table.getColumns(),min=cols.reduce((sum,c)=>sum+c.getDefinition().minWidth,0);
          return min<=holder.clientWidth
            ? Math.abs(cols.reduce((sum,c)=>sum+c.getWidth(),0)-holder.clientWidth)<=cols.length
            : cols.every(c=>Math.abs(c.getWidth()-c.getDefinition().minWidth)<.5);
        });
        await new Promise(resolve=>w.requestAnimationFrame(resolve));
        geometry(width);
      };
      for(const width of [1440,1280,1024,768,390])await resize(width);
      if(count){
        await resize(1440);cell().click();await until(()=>d.activeElement===cell());
        if(count>1)key('ArrowRight');
        const focus=d.activeElement,field=focus.getAttribute('tabulator-field');
        key('ArrowDown');key('ArrowUp');key('Shift');
        // Extend a real logical rectangular range through the adapter.
        d.activeElement.dispatchEvent(new w.KeyboardEvent('keydown',{key:'ArrowDown',shiftKey:true,bubbles:true,cancelable:true}));
        const extent=d.activeElement,selected=[...host.querySelectorAll('.tabulator-range-selected')];
        for(const width of [1024,768,390,1440,390,1440,390]){
          await resize(width);
          assert(d.activeElement===extent && d.activeElement.getAttribute('tabulator-field')===field,'resize retains score focus identity');
          assert(selected.every(c=>c.classList.contains('tabulator-range-selected')) && host.querySelectorAll('.tabulator-range-selected').length===selected.length,'range remains rectangular and unchanged');
          const box=extent.getBoundingClientRect(),bounds=holder.getBoundingClientRect(),frozen=extent.closest('.tabulator-row').querySelector('.pp5-grid-identity').getBoundingClientRect();
          assert(box.left>=frozen.right-.5 && box.right<=bounds.right+.5,'focused score revealed beside frozen identity');
          assert(requests.length===0,'selection resize sends no mutation');
        }
        await resize(1440);cell().dispatchEvent(new w.MouseEvent('dblclick',{bubbles:true,cancelable:true}));
        const input=cell().querySelector('input');assert(input && d.activeElement===input,'explicit editor opens');
        key('ArrowLeft');const caret=[input.selectionStart,input.selectionEnd],value=input.value;
        for(const width of [1024,768,390,1440,390,1440,390]){
          await resize(width);
          assert(cell().querySelector('input')===input && d.activeElement===input,'same editor and focus survive resize');
          assert(input.value===value && input.selectionStart===caret[0] && input.selectionEnd===caret[1],'value and caret survive resize');
          assert(requests.length===0,'editor resize never saves or cancels');
        }
        key('Escape');assert(!host.querySelector('input') && requests.length===0,'cancel after resize sends no request');
        await resize(1440);cell().click();await until(()=>d.activeElement===cell());
        const fixed=widths(),rowHeight=cell().closest('.tabulator-row').getBoundingClientRect().height;
        key('5');key('ArrowDown');await until(()=>requests.length===1 && !host.querySelector('[data-pp5-saving]'));
        assert(JSON.stringify(widths())===JSON.stringify(fixed),'authoritative single summary reconciliation preserves widths');
        assert(cell().closest('.tabulator-row').getBoundingClientRect().height===rowHeight,'ordinary save preserves row height');
        assert(table.getRows()[0].getData().summaryDisplay==='19.25/35.5','single summary projects server values');
        const tool=d.getElementById('gradebook-range-tool');tool.querySelector('summary').click();
        assert(JSON.stringify(widths())===JSON.stringify(fixed),'compact tool does not resize grid');
        tool.open=false;
      }
      results.push(`${count} components: PASS ${checks} SYNTHETIC checks`);
    }catch(error){results.push(`${frame.title}: FAIL after ${checks}: ${error.message}`);}
  }
  document.getElementById('browser-results').textContent=results.join('\n')+'\n'+JSON.stringify(measurements);
  document.title=results.every(r=>r.includes(': PASS '))?'PASS — responsive Gradebook':'FAIL — responsive Gradebook';
})();
