(() => {
  'use strict';
  const fixture = GridSpike.setup(), started = performance.now(), host = document.getElementById('grid');
  const fields = fixture.components.map(c => c.field), scoreFields = new Set(fields);
  const rowIndex = new Map(fixture.rows.map((row, index) => [row.enrollmentId, index]));
  let active = null, editing = null, composing = false, pending = null, programmatic = false;
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
      targets.forEach((row,i) => {
        const next = { ...row.getData(), ...rowData[i] };
        const summary = GridSpike.mockSummary(next, fixture.components);
        Object.assign(fixture.rows[rowIndex.get(next.enrollmentId)], rowData[i], summary);
        row.update({ ...rowData[i], ...summary });
      });
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
  function refreshRow(row) {
    const data = row.getData(), summary = GridSpike.mockSummary(data, fixture.components);
    Object.assign(fixture.rows[rowIndex.get(data.enrollmentId)], summary);
    row.update(summary);
  }
  function selectedRectangle() {
    const range = table.getRanges()[0], matrix = range?.getStructuredCells();
    if (!matrix?.length || !matrix[0]?.length) return null;
    const first = matrix[0][0], last = matrix.at(-1).at(-1);
    const rectangle = {
      x1:fields.indexOf(first.getField()) + 1, y1:rowIndex.get(first.getRow().getData().enrollmentId),
      x2:fields.indexOf(last.getField()) + 1, y2:rowIndex.get(last.getRow().getData().enrollmentId)
    };
    // Identity and summary fields are not in `fields`; use the full column order.
    const allFields = ['student', ...fields, 'sum', 'max', 'count', 'complete'];
    rectangle.x1 = allFields.indexOf(first.getField());
    rectangle.x2 = allFields.indexOf(last.getField());
    return { range, matrix, rectangle };
  }
  function clearSelection(key) {
    const selected = selectedRectangle();
    if (!selected) return;
    const plan = GridSpike.planClear(fixture, selected.rectangle);
    const actual = selected.matrix.flat();
    if (plan.ok && (actual.length !== plan.cells.length || actual.some((cell,i) =>
      cell.getField() !== plan.cells[i].field || cell.getRow().getData().enrollmentId !== plan.cells[i].enrollmentId))) {
      plan.ok = false; plan.reason = 'invalid rectangle';
    }
    const size = `${selected.matrix.length} × ${selected.matrix[0].length}`;
    if (!plan.ok) { log('ACTIVE', `clear ${key} · ${size} · blocked ${plan.reason} · 0 changed · selection preserved`); return; }
    const startedClear = performance.now(), affected = new Set();
    let changed = 0;
    programmatic = true;
    try {
      plan.cells.forEach((target,i) => {
        const cell = actual[i], before = cell.getValue();
        if (before !== '' && before !== null && before !== undefined) { cell.setValue(''); changed++; }
        target.row[target.field] = '';
        affected.add(target.y);
      });
    } finally { programmatic = false; }
    const clearMs = performance.now() - startedClear, startedSummary = performance.now();
    if (changed) affected.forEach(y => refreshRow(table.getRows('active')[y]));
    const summaryMs = performance.now() - startedSummary;
    const preserved = table.getRanges()[0] === selected.range;
    active = actual[0]; // A clear leaves the top-left score as the predictable typing anchor.
    active?.getElement()?.focus();
    log('ACTIVE', `clear ${key} · ${size} · ${plan.cells.length} targets · ${changed} changed · ${affected.size} summary rows · selection ${preserved ? 'preserved' : 'changed'} · ${clearMs.toFixed(1)} + ${summaryMs.toFixed(1)} ms`);
  }
  function delayedSummary() {
    const row = active?.getRow(); if (!row) return;
    const before = selectedRectangle(), scroller = host.querySelector('.tabulator-tableholder');
    const scroll = [scroller?.scrollLeft, scroller?.scrollTop], focus = document.activeElement;
    setTimeout(() => {
      refreshRow(row);
      log('ACTIVE', `async summary · range ${before?.range === table.getRanges()[0] ? 'preserved' : 'changed'} · focus ${focus === document.activeElement ? 'preserved' : 'changed'} · scroll ${scroll[0] === scroller?.scrollLeft && scroll[1] === scroller?.scrollTop ? 'preserved' : 'changed'}`);
    }, 180);
  }
  document.getElementById('simulate-summary').addEventListener('click', delayedSummary);
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
  table.on('cellEdited', cell => {
    if (programmatic) return;
    editing = null;
    const data = cell.getRow().getData();
    fixture.rows[rowIndex.get(data.enrollmentId)][cell.getField()] = cell.getValue();
    refreshRow(cell.getRow());
    log('ACTIVE', 'edit commit · ' + cell.getField() + ' = ' + cell.getValue() + ' · summary refreshed');
  });
  table.on('cellEditCancelled', () => { editing = null; log('ACTIVE', 'edit cancel'); });
  table.on('clipboardPasted', () => log('ACTIVE', 'paste event'));
  host.addEventListener('compositionstart', () => { composing = true; log('COMPOSING', 'start'); }, true);
  host.addEventListener('compositionend', () => { composing = false; log(editing ? 'EDITING' : 'ACTIVE', 'end'); }, true);
  host.addEventListener('keydown', e => {
    if (composing || e.isComposing || editing) return;
    if (e.key === 'F8' && e.shiftKey) { e.preventDefault(); e.stopPropagation(); delayedSummary(); return; }
    if ((e.key === 'Delete' || e.key === 'Backspace') && table.getRanges().length) {
      e.preventDefault(); e.stopPropagation(); clearSelection(e.key); return;
    }
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
