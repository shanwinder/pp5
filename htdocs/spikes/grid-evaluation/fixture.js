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
  // Spike-only presentation values. Production totals always come from PP5.
  function mockSummary(row, components) {
    let total = 0, entered = 0;
    for (const component of components) {
      const value = row[component.field];
      if (value !== '' && value !== null && value !== undefined) {
        entered++;
        const numeric = Number(value);
        if (Number.isFinite(numeric)) total += numeric;
      }
    }
    return {
      sum: total.toFixed(2),
      max: components.reduce((sum, component) => sum + component.max, 0).toFixed(2),
      count: `${entered} / ${components.length}`,
      complete: components.length > 0 && entered === components.length ? 'ครบ' : 'ยังไม่ครบ'
    };
  }
  function planClear(fixture, rectangle) {
    const { x1, y1, x2, y2 } = rectangle || {};
    if (![x1,y1,x2,y2].every(Number.isInteger)) return { ok:false, reason:'invalid rectangle' };
    const cells = [], left = Math.min(x1,x2), right = Math.max(x1,x2);
    const top = Math.min(y1,y2), bottom = Math.max(y1,y2);
    if (left < 0 || right > fixture.components.length + 4 || top < 0 || bottom >= fixture.rows.length) {
      return { ok:false, reason:'invalid rectangle' };
    }
    for (let y = top; y <= bottom; y++) for (let x = left; x <= right; x++) {
      const row = fixture.rows[y], component = fixture.components[x-1];
      if (x === 0) return { ok:false, reason:'identity' };
      if (!component) return { ok:false, reason:'summary' };
      if (row.historical) return { ok:false, reason:'historical' };
      cells.push({ x, y, row, field:component.field, enrollmentId:row.enrollmentId, componentId:component.id });
    }
    return { ok:true, cells, width:right-left+1, height:bottom-top+1 };
  }
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
      components.forEach((component, j) => {
        let value = '';
        if (i % 11 < 2 || (i >= 4 && (i * 3 + j * 5) % 13 < 3) || ((i === 2 || i === 3) && j === width - 1)) value = '';
        else if ((i + j) % 17 === 0) value = '0.00';
        else {
          const score = Math.min(component.max, ((i * 7 + j * 3) % (component.max * 2 + 1)) / 2);
          value = Number.isInteger(score) ? String(score) : score.toFixed(1);
        }
        row[component.field] = value;
      });
      Object.assign(row, mockSummary(row, components));
      return row;
    });
    return { mode, rows, components, summaryTitles, currentCount: count - 5, historicalCount: 5 };
  }
  const checklist = [
    'คลิกหนึ่งครั้งแล้วพิมพ์ 5', 'พิมพ์ 5 ↓ 6 ↓ 7 ↓ ต่อเนื่อง', 'ดับเบิลคลิกแก้ค่าเดิม',
    '← → เลื่อน caret', 'Enter/Shift+Enter ลง/ขึ้น', 'Tab/Shift+Tab ขวา/ซ้าย',
    'ลากช่วง 3×3', 'Shift+Arrow ขยายช่วง', 'Cmd/Ctrl+C ได้ TSV',
    'Delete/Backspace ลบช่องเดียวและ 3×3', 'ลบช่วงผสมประวัติ/สรุป/ชื่อไม่ได้เลย',
    'สรุปเปลี่ยนหลังแก้ ลบ และวาง', 'พิมพ์ต่อทันทีหลังลบ',
    'Paste 2×2 ช่องว่าง ≠ 0', 'ประวัติแก้ไม่ได้แต่ copy ได้',
    'ชื่อนักเรียนค้างซ้าย', 'Focus ชัดเมื่อเลื่อน', 'พิมพ์ไทย/IME ไม่ย้ายก่อนจบ'
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
  return { make, setup, log, ready, mockSummary, planClear };
})();
