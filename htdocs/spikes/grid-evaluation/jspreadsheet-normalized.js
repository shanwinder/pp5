(() => {
  'use strict';
  const fixture = GridSpike.setup(), started = performance.now(), host = document.getElementById('grid');
  const scoreEnd = fixture.components.length;
  const fields = ['student', ...fixture.components.map(c => c.field), 'sum', 'max', 'count', 'complete'];
  const data = fixture.rows.map(row => fields.map(field => row[field]));
  const columns = [
    {type:'text',title:'นักเรียน',width:210,readOnly:true,align:'left'},
    ...fixture.components.map(c => ({type:'text',title:c.name + ' · เต็ม ' + c.max,width:92,align:'right'})),
    {type:'text',title:'คะแนนที่บันทึกรวม',width:125,readOnly:true},
    {type:'text',title:'คะแนนเต็มรวม',width:115,readOnly:true},
    {type:'text',title:'บันทึกแล้ว / ทั้งหมด',width:125,readOnly:true},
    {type:'text',title:'ความครบถ้วน',width:115,readOnly:true}
  ];
  let active = null, editing = null, composing = false;
  const log = (state, action) => GridSpike.log(state, action);
  const exitFocus = direction => document.querySelector(direction === 'right' ? '#ime' : 'a[href="index.html"]').focus();
  const eligible = (x,y) => x >= 1 && x <= scoreEnd && y >= 0 && y < fixture.currentCount;
  function neighbor(x,y,direction) {
    const nx = x + (direction === 'left' ? -1 : direction === 'right' ? 1 : 0);
    const ny = y + (direction === 'up' ? -1 : direction === 'down' ? 1 : 0);
    return eligible(nx,ny) ? [nx,ny] : null;
  }
  function select(coords) {
    if (!coords) return;
    grid.updateSelectionFromCoords(coords[0],coords[1],coords[0],coords[1]);
    active = coords;
    log('ACTIVE', coords.join(','));
  }
  const grid = jexcel(host, {
    data,columns,tableOverflow:true,tableWidth:'100%',tableHeight:'min(64vh, 560px)',freezeColumns:1,
    parseFormulas:false,autoIncrement:false,autoCasting:false,
    allowInsertRow:false,allowManualInsertRow:false,allowInsertColumn:false,allowManualInsertColumn:false,
    allowDeleteRow:false,allowDeleteColumn:false,allowRenameColumn:false,
    columnSorting:false,columnDrag:false,rowDrag:false,contextMenu:false,
    updateTable:(_instance,cell,x,y) => {
      if (fixture.rows[y]?.historical) { cell.classList.add('readonly'); cell.parentElement.classList.add('historical'); }
      if (x > scoreEnd) cell.classList.add('summary');
    },
    onselection:(_instance,x1,y1,x2,y2) => {
      active = [Number(x2),Number(y2)];
      log('ACTIVE', 'range ' + x1 + ',' + y1 + ' → ' + x2 + ',' + y2);
    },
    oneditionstart:(_instance,cell,x,y) => {
      if (!cell?.classList.contains('readonly')) { editing = cell; log('EDITING', x + ',' + y); }
    },
    oneditionend:(_instance,cell,x,y,_value,save) => {
      editing = null; log('ACTIVE', (save ? 'edit commit · ' : 'edit cancel · ') + x + ',' + y);
    },
    onchange:(_instance,_cell,x,y,value) => log('ACTIVE', 'change · ' + x + ',' + y + ' = ' + value),
    oncopy:(_instance,copy) => log('ACTIVE', 'copy · ' + JSON.stringify(String(copy).slice(0,80))),
    onbeforepaste:(_instance,raw,x,y) => {
      const matrix = String(raw).replace(/\r\n?/g,'\n').replace(/\n$/,'').split('\n').map(line=>line.split('\t'));
      const invalid = matrix.some((row,dy) => row.some((_value,dx) => !eligible(Number(x)+dx,Number(y)+dy)));
      log('ACTIVE', (invalid ? 'paste blocked · ' : 'paste before · ') + matrix.length + ' × ' + (matrix[0]?.length || 0));
      return invalid ? false : raw;
    },
    onpaste:() => log('ACTIVE', 'paste commit · in memory')
  });
  GridSpike.ready(performance.now()-started,host.querySelectorAll('td[data-x]').length);
  host.addEventListener('compositionstart', () => { composing = true; log('COMPOSING', 'start'); }, true);
  host.addEventListener('compositionend', () => { composing = false; log(editing ? 'EDITING' : 'ACTIVE', 'composition end'); }, true);
  host.addEventListener('keydown', e => {
    if (composing || e.isComposing) {
      // Keep CE's document-level Enter/arrow handler away from an active IME, without cancelling browser composition.
      if (editing && ['Enter','ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key)) e.stopPropagation();
      return;
    }
    if (editing) {
      if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') return; // Native caret and Shift selection.
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); grid.closeEditor(editing,false); return; }
      let direction = null;
      if (e.key === 'ArrowDown' || (e.key === 'Enter' && !e.shiftKey)) direction = 'down';
      if (e.key === 'ArrowUp' || (e.key === 'Enter' && e.shiftKey)) direction = 'up';
      if (e.key === 'Tab') direction = e.shiftKey ? 'left' : 'right';
      if (!direction) return;
      const x = Number(editing.dataset.x), y = Number(editing.dataset.y);
      const target = neighbor(x,y,direction);
      if (!target && e.key === 'Tab') {
        e.preventDefault(); e.stopPropagation(); grid.closeEditor(editing,true);
        exitFocus(direction); log('ACTIVE', 'Tab boundary · focus exits'); return;
      }
      e.preventDefault(); e.stopPropagation();
      grid.closeEditor(editing,true);
      log('ACTIVE', 'edit commit · ' + e.key + (e.shiftKey ? ' + Shift' : ''));
      if (target) select(target);
      return;
    }
    if (!active) return;
    if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
      if (eligible(...active)) log('EDITING', 'native printable starts edit · ' + e.key);
      return; // CE's documented native type-to-edit.
    }
    if ((e.key === 'Enter' || e.key === 'F2') && eligible(...active)) {
      e.preventDefault(); e.stopPropagation();
      const cell = host.querySelector('td[data-x="' + active[0] + '"][data-y="' + active[1] + '"]');
      if (cell) grid.openEditor(cell,false);
      log('EDITING', e.key + ' opens current value'); return;
    }
    if (e.key === 'Escape') { grid.resetSelection(); active = null; log('ACTIVE', 'range cleared'); return; }
    if (e.key.startsWith('Arrow') && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
      const direction = e.key.slice(5).toLowerCase();
      const target = neighbor(...active,direction);
      if (target) { e.preventDefault(); e.stopPropagation(); select(target); }
      log('ACTIVE', e.key); return;
    }
    if (e.key.startsWith('Arrow') && e.shiftKey) log('ACTIVE', 'range extension · ' + e.key);
    if (e.key === 'Tab') {
      const target = neighbor(...active,e.shiftKey ? 'left' : 'right');
      e.preventDefault(); e.stopPropagation();
      if (target) select(target);
      else { exitFocus(e.shiftKey ? 'left' : 'right'); log('ACTIVE', 'Tab boundary · focus exits'); }
    }
  }, true);
  document.getElementById('ime').addEventListener('compositionstart', () => log('COMPOSING', 'test field start'));
  document.getElementById('ime').addEventListener('compositionend', () => log('ACTIVE', 'test field end'));
  window.spikeTable = grid;
})();
