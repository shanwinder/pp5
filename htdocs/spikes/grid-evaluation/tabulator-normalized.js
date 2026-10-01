(() => {
  'use strict';
  const fixture = GridSpike.setup(), started = performance.now(), host = document.getElementById('grid');
  const fields = fixture.components.map(c => c.field), scoreFields = new Set(fields);
  let active = null, editing = null, composing = false, pending = null;
  const log = (state, action) => GridSpike.log(state, action);
  const escape = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const eligible = cell => cell && scoreFields.has(cell.getField()) && !cell.getRow().getData().historical;
  const printable = e => e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey && !e.isComposing && !composing;
  const exitFocus = direction => document.querySelector(direction === 'right' ? '#ime' : 'a[href="index.html"]').focus();
  const columns = [{title:'นักเรียน',field:'student',width:210,frozen:true,headerSort:false,editor:false,cssClass:'identity'}];
  function scoreEditor(cell, onRendered, success, cancel) {
    const input = document.createElement('input');
    input.type = 'text'; input.setAttribute('aria-label', 'คะแนน');
    input.value = pending === null ? String(cell.getValue() ?? '') : pending;
    const replace = pending !== null; pending = null;
    input.addEventListener('compositionstart', () => { composing = true; log('COMPOSING', 'start'); });
    input.addEventListener('compositionend', () => { composing = false; log('EDITING', 'composition end'); });
    input.addEventListener('keydown', e => {
      if (composing || e.isComposing) return;
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); editing = null; cancel(); log('ACTIVE', 'edit cancel'); return; }
      let direction = null;
      if (e.key === 'ArrowDown' || (e.key === 'Enter' && !e.shiftKey)) direction = 'down';
      if (e.key === 'ArrowUp' || (e.key === 'Enter' && e.shiftKey)) direction = 'up';
      if (e.key === 'Tab') direction = e.shiftKey ? 'left' : 'right';
      if (!direction) return;
      const target = neighbor(cell, direction);
      if (!target && e.key === 'Tab') {
        e.preventDefault(); e.stopPropagation(); editing = null; success(input.value);
        queueMicrotask(() => exitFocus(direction)); log('ACTIVE', 'Tab boundary · focus exits'); return;
      }
      e.preventDefault(); e.stopPropagation();
      editing = null; success(input.value); log('ACTIVE', 'edit commit · ' + e.key + (e.shiftKey ? ' + Shift' : ''));
      if (target) queueMicrotask(() => select(target));
    });
    input.addEventListener('blur', () => {
      if (editing === cell && !composing) { editing = null; success(input.value); log('ACTIVE', 'edit commit · blur'); }
    });
    onRendered(() => { input.focus(); if (!replace) input.setSelectionRange(input.value.length, input.value.length); });
    return input;
  }
  fixture.components.forEach(c => columns.push({
    title:escape(c.name) + '<br><span class="max">เต็ม ' + c.max + '</span>',field:c.field,width:88,
    hozAlign:'right',editor:scoreEditor,editable:eligible,
    formatter:cell => cell.getValue() === '' ? '' : escape(cell.getValue())
  }));
  [['sum','คะแนนที่บันทึกรวม',118],['max','คะแนนเต็มรวม',105],['count','บันทึกแล้ว / ทั้งหมด',125],['complete','ความครบถ้วน',105]].forEach(([field,title,width]) => {
    columns.push({title,field,width,editor:false,cssClass:'summary',headerSort:false});
  });
  const table = new Tabulator(host, {
    data:fixture.rows,index:'id',columns,height:'min(64vh, 560px)',layout:'fitData',
    rowHeight:30,headerHeight:40,renderVertical:'virtual',columnDefaults:{headerSort:false,resizable:'header'},
    selectableRange:1,selectableRangeColumns:false,selectableRangeRows:false,selectableRangeFill:false,
    selectableRangeClearCells:false,editTriggerEvent:'dblclick',selectableRangeBlurEditOnNavigate:true,
    clipboard:true,clipboardCopyStyled:false,clipboardCopyRowRange:'range',
    clipboardCopyConfig:{rowHeaders:false,columnHeaders:false},clipboardPasteParser:'range',
    clipboardPasteAction: rowData => {
      const start = table.getRanges()[0]?.getStructuredCells()[0]?.[0];
      if (!start || !rowData?.length) return [];
      const rows = table.getRows('active');
      const offset = rows.findIndex(row => row.getData().id === start.getRow().getData().id);
      const targets = rows.slice(offset, offset + rowData.length);
      const forbidden = offset < 0 || targets.length !== rowData.length || targets.some((row,i) =>
        row.getData().historical || Object.keys(rowData[i]).some(field => !scoreFields.has(field)));
      if (forbidden) { log('ACTIVE', 'paste blocked · history/summary/identity'); return []; }
      targets.forEach((row,i) => row.update(rowData[i]));
      log('ACTIVE', 'paste commit · ' + rowData.length + ' rows');
      return targets;
    },
    rowFormatter:row => { if (row.getData().historical) row.getElement().classList.add('historical'); }
  });
  function neighbor(cell, direction) {
    const rows = table.getRows('active');
    const y = rows.findIndex(row => row.getData().id === cell.getRow().getData().id), x = fields.indexOf(cell.getField());
    if (y < 0 || x < 0) return null;
    if (direction === 'left' || direction === 'right') {
      const nx = x + (direction === 'right' ? 1 : -1);
      return nx < 0 || nx >= fields.length ? null : rows[y].getCell(fields[nx]);
    }
    for (let ny = y + (direction === 'down' ? 1 : -1); ny >= 0 && ny < rows.length; ny += direction === 'down' ? 1 : -1) {
      if (!rows[ny].getData().historical) return rows[ny].getCell(fields[x]);
    }
    return null;
  }
  function select(cell) {
    table.getRanges().forEach(range => range.remove());
    table.addRange(cell, cell); active = cell; cell.getElement().focus();
    log('ACTIVE', cell.getField() + ' · ' + cell.getRow().getData().enrollmentId);
  }
  table.on('tableBuilt', () => GridSpike.ready(performance.now()-started,host.querySelectorAll('.tabulator-cell').length));
  table.on('rangeAdded', range => {
    const cells = range.getStructuredCells();
    active = cells.at(-1)?.at(-1) || active;
    log('ACTIVE', 'range start');
  });
  table.on('rangeChanged', range => {
    const cells = range.getStructuredCells();
    active = cells.at(-1)?.at(-1) || active;
    log('ACTIVE', 'range extension · ' + cells.length + ' rows · ' + (active?.getField() || '?'));
  });
  table.on('cellClick', (_event, cell) => {
    active = cell; log('ACTIVE', cell.getField() + ' selected');
    setTimeout(() => { active = cell; if (!editing) cell.getElement().focus(); }, 0);
  });
  table.on('cellEditing', cell => { editing = cell; log('EDITING', cell.getField() + ' · ' + cell.getRow().getData().enrollmentId); });
  table.on('cellEdited', cell => { editing = null; log('ACTIVE', 'edit commit · ' + cell.getField() + ' = ' + cell.getValue()); });
  table.on('cellEditCancelled', () => { editing = null; log('ACTIVE', 'edit cancel'); });
  table.on('clipboardPasted', () => log('ACTIVE', 'paste event'));
  host.addEventListener('compositionstart', () => { composing = true; log('COMPOSING', 'start'); }, true);
  host.addEventListener('compositionend', () => { composing = false; log(editing ? 'EDITING' : 'ACTIVE', 'end'); }, true);
  host.addEventListener('keydown', e => {
    if (composing || e.isComposing || editing) return;
    log('ACTIVE', 'keydown · ' + e.key + ' · ' + (active?.getField() || 'none') + ' · eligible=' + !!eligible(active));
    if (printable(e) && eligible(active)) {
      e.preventDefault(); e.stopPropagation(); pending = e.key; log('EDITING', 'printable starts edit · ' + e.key); active.edit(); return;
    }
    if ((e.key === 'Enter' || e.key === 'F2') && eligible(active)) {
      e.preventDefault(); e.stopPropagation(); pending = null; active.edit(); log('EDITING', e.key + ' opens current value'); return;
    }
    if (e.key === 'Escape') { table.getRanges().forEach(range => range.remove()); active = null; log('ACTIVE', 'range cleared'); return; }
    if (e.key.startsWith('Arrow') && !e.shiftKey && !e.ctrlKey && !e.metaKey && eligible(active)) {
      const target = neighbor(active,e.key.slice(5).toLowerCase());
      e.preventDefault(); e.stopPropagation();
      if (target) select(target);
      log('ACTIVE', e.key); return;
    }
    if (e.key === 'Tab' && eligible(active)) {
      const direction = e.shiftKey ? 'left' : 'right', target = neighbor(active,direction);
      e.preventDefault(); e.stopPropagation();
      if (target) select(target); else exitFocus(direction);
      log('ACTIVE', target ? e.key : 'Tab boundary · focus exits'); return;
    }
    if (e.key.startsWith('Arrow')) log('ACTIVE', (e.shiftKey ? 'range extension · ' : '') + e.key);
  }, true);
  host.addEventListener('copy', e => {
    const range = table.getRanges()[0];
    if (!range || !e.clipboardData) return;
    const tsv = range.getStructuredCells().map(row => row.map(cell => String(cell.getValue() ?? '')).join('\t')).join('\n');
    e.clipboardData.setData('text/plain', tsv); e.preventDefault();
    log('ACTIVE', 'copy · ' + JSON.stringify(tsv.slice(0,80)));
  }, true);
  document.getElementById('ime').addEventListener('compositionstart', () => log('COMPOSING', 'test field start'));
  document.getElementById('ime').addEventListener('compositionend', () => log('ACTIVE', 'test field end'));
  window.spikeTable = table;
})();
