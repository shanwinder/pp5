(() => {
  'use strict';
  const fixture = GridSpike.setup();
  const started = performance.now();
  const scoreFields = new Set(fixture.components.map(c => c.field));
  const escape = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const columns = [{title:'นักเรียน',field:'student',width:210,frozen:true,headerSort:false,editor:false,cssClass:'identity'}];
  fixture.components.forEach(c => columns.push({
    title:`${escape(c.name)}<br><span class="max">เต็ม ${c.max}</span>`,field:c.field,width:88,
    hozAlign:'right',editor:'input',editable:cell => !cell.getRow().getData().historical,
    formatter:cell => cell.getValue() === '' ? '' : escape(cell.getValue())
  }));
  [['sum','คะแนนที่บันทึกรวม',118],['max','คะแนนเต็มรวม',105],['count','บันทึกแล้ว / ทั้งหมด',125],['complete','ความครบถ้วน',105]].forEach(([field,title,width]) => {
    columns.push({title,field,width,editor:false,cssClass:'summary',headerSort:false});
  });
  const table = new Tabulator('#grid', {
    data:fixture.rows,index:'id',columns,height:'min(64vh, 560px)',layout:'fitData',
    rowHeight:30,headerHeight:40,renderVertical:'virtual',
    columnDefaults:{headerSort:false,resizable:'header'},
    selectableRange:1,selectableRangeColumns:false,selectableRangeRows:false,selectableRangeFill:false,
    selectableRangeClearCells:false,editTriggerEvent:'dblclick',
    clipboard:true,clipboardCopyStyled:false,clipboardCopyRowRange:'range',
    clipboardCopyConfig:{rowHeaders:false,columnHeaders:false},
    clipboardPasteParser:'range',
    clipboardPasteAction:function(rowData) {
      const range = this.table.modules.selectRange.activeRange;
      if (!range || !rowData?.length) return [];
      const start = range.getBounds().start;
      const rows = this.table.rowManager.activeRows;
      const offset = rows.indexOf(start.row);
      const targets = rows.slice(offset, offset + rowData.length);
      const forbidden = targets.length !== rowData.length || targets.some((row,i) =>
        row.getData().historical || Object.keys(rowData[i]).some(field => !scoreFields.has(field)));
      if (forbidden) { GridSpike.log('paste blocked','history/summary/identity'); return []; }
      GridSpike.log('paste',`${rowData.length} rows · native parsed matrix; in-memory only`);
      targets.forEach((row,i) => row.updateData(rowData[i]));
      return targets;
    },
    rowFormatter:row => { if (row.getData().historical) row.getElement().classList.add('historical'); }
  });
  table.on('tableBuilt', () => GridSpike.ready(performance.now()-started,document.querySelectorAll('#grid .tabulator-cell').length));
  table.on('rangeChanged', range => {
    const bounds = range.getBounds();
    GridSpike.log('range',`${bounds.start?.column?.field || '?'}…${bounds.end?.column?.field || '?'}`);
  });
  table.on('cellEditing', cell => GridSpike.log('edit start',`${cell.getField()} · ${cell.getRow().getData().enrollmentId}`));
  table.on('cellEdited', cell => GridSpike.log('edit end',`${cell.getField()} = ${cell.getValue()}`));
  table.on('clipboardCopied', tsv => GridSpike.log('copy',JSON.stringify(tsv.slice(0,80))));
  table.on('clipboardPasted', () => GridSpike.log('paste event'));
  document.getElementById('grid').addEventListener('keydown',event => {
    if (['ArrowUp','ArrowDown','ArrowLeft','ArrowRight','Enter','Tab'].includes(event.key)) GridSpike.log('key',event.key);
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'c') GridSpike.log('copy key',event.metaKey ? 'Meta' : 'Ctrl');
  });
  document.getElementById('grid').addEventListener('copy',()=>GridSpike.log('browser copy event'));
  document.getElementById('ime').addEventListener('compositionstart',()=>GridSpike.log('IME','start'));
  document.getElementById('ime').addEventListener('compositionend',()=>GridSpike.log('IME','end'));
  document.getElementById('copy-api').addEventListener('click',()=>table.copyToClipboard('range'));
  window.spikeTable = table;
})();
