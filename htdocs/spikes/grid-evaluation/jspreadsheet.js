(() => {
  'use strict';
  const fixture = GridSpike.setup();
  const started = performance.now();
  const scoreEnd = fixture.components.length;
  const fields = ['student', ...fixture.components.map(c => c.field), 'sum', 'max', 'count', 'complete'];
  const data = fixture.rows.map(row => fields.map(field => row[field]));
  const columns = [
    {type:'text',title:'นักเรียน',width:210,readOnly:true,align:'left'},
    ...fixture.components.map(c => ({type:'text',title:`${c.name} · เต็ม ${c.max}`,width:92,align:'right'})),
    {type:'text',title:'คะแนนที่บันทึกรวม',width:125,readOnly:true},
    {type:'text',title:'คะแนนเต็มรวม',width:115,readOnly:true},
    {type:'text',title:'บันทึกแล้ว / ทั้งหมด',width:125,readOnly:true},
    {type:'text',title:'ความครบถ้วน',width:115,readOnly:true}
  ];
  const grid = jexcel(document.getElementById('grid'), {
    data,columns,tableOverflow:true,tableWidth:'100%',tableHeight:'min(64vh, 560px)',freezeColumns:1,
    parseFormulas:false,autoIncrement:false,autoCasting:false,
    allowInsertRow:false,allowManualInsertRow:false,allowInsertColumn:false,allowManualInsertColumn:false,
    allowDeleteRow:false,allowDeleteColumn:false,allowRenameColumn:false,
    columnSorting:false,columnDrag:false,rowDrag:false,contextMenu:false,
    updateTable:(_instance,cell,x,y) => {
      if (fixture.rows[y]?.historical) { cell.classList.add('readonly'); cell.parentElement.classList.add('historical'); }
      if (x > scoreEnd) cell.classList.add('summary');
    },
    onselection:(_instance,x1,y1,x2,y2) => GridSpike.log('range',`${x1},${y1} → ${x2},${y2}`),
    oneditionstart:(_instance,cell) => GridSpike.log('edit start',cell?.getAttribute('data-x')+','+cell?.getAttribute('data-y')),
    oneditionend:(_instance,cell) => GridSpike.log('edit end',cell?.getAttribute('data-x')+','+cell?.getAttribute('data-y')),
    onchange:(_instance,cell,x,y,value) => GridSpike.log('change',`${x},${y} = ${value}`),
    oncopy:(_instance,copy) => GridSpike.log('copy',JSON.stringify(String(copy).slice(0,80))),
    onbeforepaste:(_instance,raw,x,y) => {
      const matrix = String(raw).replace(/\r\n?/g,'\n').replace(/\n$/,'').split('\n').map(line=>line.split('\t'));
      const invalid = matrix.some((row,dy) => row.some((_value,dx) =>
        Number(x)+dx < 1 || Number(x)+dx > scoreEnd || Number(y)+dy >= fixture.rows.length || fixture.rows[Number(y)+dy].historical));
      GridSpike.log(invalid ? 'paste blocked' : 'paste before',`${matrix.length} × ${matrix[0]?.length || 0} at ${x},${y}`);
      return invalid ? false : raw;
    },
    onpaste:() => GridSpike.log('paste end','native in-memory mutation')
  });
  GridSpike.ready(performance.now()-started,document.querySelectorAll('#grid td[data-x]').length);
  document.getElementById('grid').addEventListener('mousedown',event => {
    const cell = event.target.closest('td[data-x][data-y]');
    if (cell) GridSpike.log(cell.classList.contains('readonly') ? 'read-only selection' : 'pointer',`${cell.dataset.x},${cell.dataset.y}`);
  },true);
  document.getElementById('grid').addEventListener('keydown',event => {
    if (['ArrowUp','ArrowDown','ArrowLeft','ArrowRight','Enter','Tab'].includes(event.key)) GridSpike.log('key',event.key);
  });
  document.getElementById('ime').addEventListener('compositionstart',()=>GridSpike.log('IME','start'));
  document.getElementById('ime').addEventListener('compositionend',()=>GridSpike.log('IME','end'));
  window.spikeTable = grid;
})();
