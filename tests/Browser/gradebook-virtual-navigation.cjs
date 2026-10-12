'use strict';
// Trusted input regression using the existing no-DB fixture and local renderer.
// Exported for an isolated Playwright invocation; never uses a personal profile.
module.exports = async ({browser, base, result, check, asset}) => {
  result.virtualNavigation = [];
  for (const [height, width, viewport, readOnly] of [[52,5,1440,false],[52,5,390,false],[52,40,1440,false],[100,40,1440,false],[100,40,390,false],[52,5,1440,true]]) {
    const context = await browser.newContext({viewport:{width:viewport,height:900},permissions:['clipboard-read','clipboard-write']});
    const page = await context.newPage();
    const entry = {height,width,viewport,readOnly,errors:[],posts:[],steps:[],checks:0};
    result.virtualNavigation.push(entry);
    const assert = (yes,label) => {entry.checks++; check(yes,`${height}x${width}/${viewport}/${readOnly?'reader':'writer'}: ${label}`);};
    page.on('pageerror',error=>entry.errors.push(error.message));
    page.on('request',request=>{if(request.method()==='POST'&&new URL(request.url()).pathname.includes('/score'))entry.posts.push(new URL(request.url()).pathname);});
    await page.route('**/assets/gradebook.js*',route=>route.fulfill({contentType:'text/javascript',body:asset.replace('  let table;', `  window.__navigationRead = () => ({ active: state.active && {id:state.active.getRow().getData().enrollmentId,field:state.active.getField()}, live:state.active===liveCell(state.active), pending:state.pendingSingles.size, uncertain:state.uncertain });\n  let table;`)}));
    await page.route('**/frame?mode=stress',async route=>{
      const response=await route.fetch();let html=await response.text();
      html=html.replace(/(<script[^>]*id="gradebook-grid-data"[^>]*>)([\s\S]*?)(<\/script>)/,(all,start,json,end)=>{
        const config=JSON.parse(json);config.components=config.components.slice(0,width);config.rows=config.rows.slice(0,height);config.canScore=!readOnly;
        config.rows.forEach((row,index)=>{row.scores=Object.fromEntries(config.components.map((c,x)=>[c.id,x===1?'0.00':'1.50']));if(index===height-1)row.rowType='HISTORICAL';});
        return start+JSON.stringify(config)+end;
      });
      await route.fulfill({response,body:html});
    });
    const cell=n=>page.locator('.tabulator-row').filter({has:page.locator('[tabulator-field=rowNumber]').filter({hasText:new RegExp('^'+n+'$')})}).locator('[tabulator-field=score_1000]');
    const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
    async function snapshot(label,ordinal,selection=1){
      const data=await page.evaluate(()=>{const h=document.querySelector('.tabulator-tableholder'),a=document.activeElement;
        const id=e=>({id:Number(e?.closest('.tabulator-row')?.querySelector('[tabulator-field=rowNumber]')?.textContent),field:e?.getAttribute('tabulator-field')});
        return {logical:window.__navigationRead(),focus:id(a),tag:a.tagName,active:[...document.querySelectorAll('[data-pp5-active]')].map(id),stops:[...document.querySelectorAll('.tabulator-cell[tabindex="0"]')].map(id),selected:document.querySelectorAll('.tabulator-range-selected').length,editors:document.querySelectorAll('#gradebook-tabulator input').length,scroll:[scrollX,scrollY,h.scrollLeft,h.scrollTop]};});
      entry.steps.push({label,...data});
      assert(!entry.errors.length,`${label}: no exception`);
      assert(data.logical.active?.id===ordinal&&data.logical.active.field==='score_1000'&&data.logical.live,`${label}: logical identity resolves current cell`);
      assert(data.focus.id===ordinal&&data.focus.field==='score_1000'&&data.tag==='DIV',`${label}: DOM focus agrees`);
      assert(data.active.length===1&&data.active[0].id===ordinal&&data.stops.length===1&&data.stops[0].id===ordinal,`${label}: active highlight and unique score Tab stop`);
      assert(data.editors===0&&data.selected===selection,`${label}: correct selection, no editor`);
      return data;
    }
    try {
      await page.goto(base+'/frame?mode=stress');await page.waitForFunction(()=>document.querySelector('.pp5-gradebook')?.hidden);
      await cell(1).click();await pause(80);const initial=await snapshot('pointer start',1);
      // No settling or DOM observation between burst keys: reproduces Task 6T.
      for(let n=1;n<height;n++)await page.keyboard.press('ArrowDown');
      const down=await snapshot('burst down',height);await pause(100);await snapshot('after scroll callbacks',height);
      assert(initial.scroll[0]===down.scroll[0]&&initial.scroll[1]===down.scroll[1],'Down burst scrolls holder only');
      await cell(height).click();await pause(80);
      const reverseViewport=await page.evaluate(()=>[scrollX,scrollY]);
      for(let n=height;n>1;n--)await page.keyboard.press('Shift+ArrowUp');
      const reverse=await page.evaluate(()=>({logical:window.__navigationRead(),range:document.getElementById('gradebook-range-status').textContent,focusedRow:Number(document.activeElement.closest('.tabulator-row')?.querySelector('[tabulator-field=rowNumber]')?.textContent)}));
      entry.steps.push({label:'reverse virtual range',...reverse});
      assert(reverse.logical.active.id===1&&reverse.focusedRow===1&&reverse.range===`เลือก ${height} แถว × 1 หัวข้อคะแนน`,'reverse Shift range crosses virtual boundaries without dropped keys');
      assert(await page.evaluate(before=>before[0]===scrollX&&before[1]===scrollY,reverseViewport),'reverse Shift navigation scrolls holder only');
      await page.keyboard.press('Meta+C');assert(await page.evaluate(()=>navigator.clipboard.readText())===Array(height).fill('1.50').join('\n'),'native reverse virtual range includes historical scores');
      await page.keyboard.press('Escape');await snapshot('reverse range Escape',1,0);
      await cell(1).click();await pause(80);
      // Re-run the plain Up burst independently of the reverse range.
      for(let n=1;n<height;n++)await page.keyboard.press('ArrowDown');
      const upViewport=await page.evaluate(()=>[scrollX,scrollY]);
      for(let n=height;n>1;n--)await page.keyboard.press('ArrowUp');
      const returned=await snapshot('burst up',1);
      assert(upViewport[0]===returned.scroll[0]&&upViewport[1]===returned.scroll[1],'Up burst scrolls holder only');
      if(width===40){
        for(let n=1;n<width;n++)await page.keyboard.press('Shift+ArrowRight');
        for(let n=1;n<51;n++)await page.keyboard.press('Shift+ArrowDown');
        const range=await page.evaluate(()=>({logical:window.__navigationRead(),focus:document.activeElement.getAttribute('tabulator-field'),row:Number(document.activeElement.closest('.tabulator-row')?.querySelector('[tabulator-field=rowNumber]')?.textContent)}));
        entry.steps.push({label:'2040-cell range',...range});
        assert(range.logical.active.id===51&&range.logical.active.field==='score_1039'&&range.row===51&&range.focus==='score_1039','rapid 2040-cell range keeps extent focus');
        await page.evaluate(()=>navigator.clipboard.writeText('5'));await page.keyboard.press('Meta+V');await pause(200);
        assert(entry.posts.length===0&&await page.locator('#gradebook-batch-status').innerText().then(text=>text.includes('2000')),'expanded limit rejects before POST');
        await cell(51).click();await pause(80);for(let n=51;n>1;n--)await page.keyboard.press('ArrowUp');await snapshot('return after large range',1);
      }
      for(let n=2;n<=height;n++){await page.keyboard.press('ArrowDown');await snapshot('continuous down '+n,n);}
      for(let n=height-1;n>=1;n--){await page.keyboard.press('ArrowUp');await snapshot('continuous up '+n,n);}
      // Force the lifecycle that used to leave false in cached CellComponents.
      await page.evaluate(()=>{const table=Tabulator.findTable('#gradebook-tabulator')[0],row=table.getRow(1);window.__oldNavigationCell=row.getCell('score_1000');row.reformat();});
      assert(await page.evaluate(()=>window.__oldNavigationCell.getElement()===false),'old component was actually deleted');
      await page.keyboard.press('ArrowDown');await snapshot('after row recreation',2);
      await page.keyboard.press('Shift+ArrowDown');await snapshot('forward range',3,2);
      await page.keyboard.press('Shift+ArrowUp');await snapshot('shrink range',2);
      await page.keyboard.press('Shift+ArrowUp');await snapshot('reverse range',1,2);
      await page.keyboard.press('Meta+C');assert(await page.evaluate(()=>navigator.clipboard.readText())==='1.50\n1.50','native rectangular score TSV');
      await page.keyboard.press('Escape');await snapshot('range Escape',1,0);
      await cell(1).click();await pause(80);await page.keyboard.press('Meta+C');assert(await page.evaluate(()=>navigator.clipboard.readText())==='1.50','native scalar score TSV');
      if(!readOnly){
        await page.keyboard.press('F2');const input=await page.locator('#gradebook-tabulator input').elementHandle();
        const caret=await input.evaluate(el=>el.selectionStart);await page.keyboard.press('ArrowLeft');
        assert(await input.evaluate((el,c)=>document.activeElement===el&&el.selectionStart===c-1,caret),'explicit Left stays in same input');
        await page.setViewportSize({width:viewport===390?1440:390,height:900});await pause(200);
        assert(await input.evaluate((el,c)=>el.isConnected&&document.activeElement===el&&el.value==='1.50'&&el.selectionStart===c-1,caret),'resize retains input/value/caret/focus');
        await page.keyboard.press('Escape');await snapshot('editor Escape',1);
      }
      assert(entry.posts.length===0,'navigation/copy/editor cancel sends no score POST');
      if(!readOnly&&width===5&&viewport===1440){
        // Existing no-DB response fixture: intentional writes are isolated from
        // the nonmutation window above and retain real keyboard/paste input.
        await page.route('**/hx/gradebook/**/score',async route=>{
          // The existing single-response fixture accepts components 10–13 and
          // rows 1–4. Reuse its unchanged protocol with synthetic ID translation.
          const path=new URL(route.request().url()).pathname;
          const ids=path.match(/components\/(\d+)\/enrollments\/(\d+)/);
          const response=await route.fetch({url:base+'/hx/gradebook/1/components/10/enrollments/1/score'});
          const body=(await response.text()).replaceAll('score-1-10-1',`score-1-${ids[1]}-${ids[2]}`).replaceAll('row-1-1-',`row-1-${ids[2]}-`);
          await pause(220);await route.fulfill({response,body});
        });
        for(let n=1;n<17;n++)await page.keyboard.press('ArrowDown');
        const directViewport=await page.evaluate(()=>[scrollX,scrollY]);
        for(const value of ['5','6','7']){await page.keyboard.type(value);await page.keyboard.press('ArrowDown');}
        await snapshot('rapid direct vertical commits',20);
        assert(await page.evaluate(before=>before[0]===scrollX&&before[1]===scrollY,directViewport),'direct-entry navigation keeps document scroll fixed');
        await page.waitForFunction(()=>window.__navigationRead().pending===0);
        assert(entry.posts.length===3&&[17,18,19].every((n,i)=>entry.posts[i].endsWith(`/components/1000/enrollments/${n}/score`)),'three direct writes retain enrollment order');
        await page.keyboard.type('8');await page.keyboard.press('ArrowDown');
        await page.evaluate(()=>Tabulator.findTable('#gradebook-tabulator')[0].getRow(20).reformat());
        await snapshot('row recreation while single pending',21);
        await page.waitForFunction(()=>window.__navigationRead().pending===0);
        entry.recreatedResponse=await cell(20).evaluate(el=>({text:el.textContent,status:document.getElementById('gradebook-batch-status').textContent,coordination:window.__navigationRead()}));
        assert(await cell(20).innerText()==='8.00','recreated source reconciles authoritative single value');
        assert(await page.locator('#gradebook-batch-status').getAttribute('data-batch-state')!=='error','recreated source causes no false uncertain-write lock');
        await snapshot('delayed single preserves target',21);
        await page.keyboard.press('Shift+ArrowRight');await page.keyboard.press('Shift+ArrowDown');
        await page.evaluate(()=>navigator.clipboard.writeText('8'));await page.keyboard.press('Meta+V');
        await page.waitForFunction(()=>document.getElementById('gradebook-batch-status').dataset.batchState==='saved'&&!document.querySelector('#gradebook-tabulator[aria-busy]'));
        assert(await page.locator('.tabulator-range-selected').count()===4&&await cell(21).evaluate(el=>el===document.activeElement),'native repeating paste retains rectangle/top-left after virtualization');
        await page.keyboard.type('9');assert(await page.locator('#gradebook-tabulator input').inputValue()==='9','immediate post-paste replacement typing');
        await page.keyboard.press('Escape');assert(entry.posts.length===5,'cancel after paste adds no score POST');
        // A genuine external pointer target must retain delayed-response focus.
        await page.keyboard.type('5');await page.keyboard.press('ArrowDown');
        await page.evaluate(()=>{const button=document.createElement('button');button.id='navigation-external';button.textContent='Fixture external control';button.style.cssText='position:fixed;top:100px;right:0;z-index:2147483647';document.body.append(button);});
        await page.locator('#navigation-external').click();await pause(600);
        assert(await page.locator('#navigation-external').evaluate(el=>el===document.activeElement),'late single preserves deliberate external focus');
        assert(entry.posts.length===6,'intentional writes have no duplicate requests');
      }
      assert(entry.errors.length===0,'zero exceptions throughout');entry.result='PASS';
    } catch(error){entry.result='FAIL';entry.failure=error.message;throw error;} finally {await context.close();}
  }
};
