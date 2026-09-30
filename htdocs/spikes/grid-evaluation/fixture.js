/* Synthetic, deterministic fixture shared verbatim by both engines. No PP5 API calls. */
window.GridSpike = (() => {
  const names = [
    ['ด.ช.กิตติ', 'ใจดี'], ['ด.ญ.มะลิ', 'สุขสันต์'], ['ด.ช.ธนกฤต', 'ศรีสวัสดิ์'],
    ['ด.ญ.ปิยธิดา', 'วัฒนากุล'], ['ด.ช.ภูริพัฒน์', 'แสงทอง'],
    ['ด.ญ.ณัฐธิดา', 'บุญช่วย'], ['ด.ช.ภาคิน', 'จันทร์สว่าง'],
    ['ด.ญ.รัตนาภรณ์', 'พรหมประสิทธิ์'], ['ด.ช.ธีรภัทร', 'นาคทอง'],
    ['ด.ญ.ชญานิศ', 'แก้วมณี']
  ];
  const titles = [
    'ใบงาน 1', 'ใบงาน 2', 'แบบฝึกหัดการบวกและลบ', 'การอ่านออกเสียง', 'การเขียนสะกดคำ',
    'งานกลุ่ม', 'แบบทดสอบย่อย 1', 'ใบงาน 3', 'สมุดงาน', 'การตอบคำถาม',
    'แบบฝึกหัดโจทย์ปัญหา', 'การนำเสนอหน้าชั้นเรียน', 'กลางภาค', 'ใบงาน 4',
    'แบบทดสอบย่อย 2', 'การอ่านจับใจความ', 'กิจกรรมปฏิบัติ', 'โครงงาน', 'ปลายภาค', 'ความรับผิดชอบ'
  ];
  const maxes = [5, 10, 10, 5, 5, 10, 10, 5, 5, 5, 10, 10, 20, 5, 10, 10, 10, 15, 20, 5];
  const summaryTitles = ['คะแนนที่บันทึกรวม', 'คะแนนเต็มรวม', 'บันทึกแล้ว / ทั้งหมด', 'ความครบถ้วน'];
  function make(mode = 'normal') {
    const stress = mode === 'stress', count = stress ? 100 : 35, width = stress ? 40 : 20;
    const components = Array.from({ length: width }, (_, i) => ({
      id: 8001 + i, field: `s${i}`, name: titles[i % 20] + (i >= 20 ? ` ${Math.floor(i / 20) + 1}` : ''),
      max: maxes[i % 20]
    }));
    const rows = Array.from({ length: count }, (_, i) => {
      const historical = i >= (stress ? 95 : 30);
      const pair = names[i % names.length];
      const row = {
        id: 9001 + i, enrollmentId: 9001 + i,
        student: `${historical ? 'ประวัติ · ' : ''}ST${String(i + 1).padStart(3, '0')}  ${pair[0]} ${pair[1]}${i >= 10 ? ` ${Math.floor(i / 10) + 1}` : ''}`,
        historical
      };
      let total = 0, entered = 0;
      components.forEach((component, j) => {
        let value = '';
        if (i % 11 < 2 || (i >= 4 && (i * 3 + j * 5) % 13 < 3) || ((i === 2 || i === 3) && j === width - 1)) value = '';
        else if ((i + j) % 17 === 0) value = '0.00';
        else {
          const score = Math.min(component.max, ((i * 7 + j * 3) % (component.max * 2 + 1)) / 2);
          value = Number.isInteger(score) ? String(score) : score.toFixed(1);
        }
        row[component.field] = value;
        if (value !== '') { entered++; total += Number(value); }
      });
      row.sum = total.toFixed(2);
      row.max = components.reduce((sum, c) => sum + c.max, 0).toFixed(2);
      row.count = `${entered} / ${width}`;
      row.complete = entered === width ? 'ครบ' : 'ยังไม่ครบ';
      return row;
    });
    return { mode, rows, components, summaryTitles, currentCount: count - 5, historicalCount: 5 };
  }
  const checklist = [
    'หน้าตาเหมือน spreadsheet', 'หัวตารางอ่านง่าย', 'ชื่อนักเรียนค้างด้านซ้าย',
    'เลื่อนแนวนอนลื่น', 'Active cell ชัด', 'เลือกช่วงง่าย', 'พิมพ์คะแนนเร็ว',
    'Keyboard เป็นธรรมชาติ', 'Copy/Paste เป็นธรรมชาติ', 'Historical/read-only ชัด',
    'เหมาะกับหน้าจอเล็ก'
  ];
  function setup() {
    const params = new URLSearchParams(location.search);
    const mode = params.get('mode') === 'stress' ? 'stress' : 'normal';
    const fixture = make(mode);
    const list = document.getElementById('checklist');
    checklist.forEach(label => {
      const item = document.createElement('label');
      const input = document.createElement('input'); input.type = 'checkbox';
      item.append(input, document.createTextNode(` ${label}`)); list.append(item);
    });
    document.getElementById('dimensions').textContent = `${fixture.rows.length} แถว · ${fixture.components.length} ช่องคะแนน · 4 สรุป · ประวัติ ${fixture.historicalCount} แถว`;
    document.getElementById('mode').value = mode;
    document.getElementById('mode').addEventListener('change', event => {
      location.search = `?mode=${encodeURIComponent(event.target.value)}`;
    });
    return fixture;
  }
  function log(type, detail = '') {
    const output = document.getElementById('diagnostics');
    const line = `${new Date().toLocaleTimeString('th-TH')} ${type}${detail ? ` · ${detail}` : ''}`;
    output.textContent = `${line}\n${output.textContent}`.slice(0, 3500);
  }
  function ready(ms, cells) {
    document.getElementById('performance').textContent = `เริ่มต้นประมาณ ${ms.toFixed(1)} ms · DOM ${cells} cells`;
    log('ready', `${ms.toFixed(1)} ms`);
  }
  return { make, setup, log, ready };
})();
