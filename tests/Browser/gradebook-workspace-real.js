// Trusted input journey on the isolated fixture, separate from authenticated MAMP.
// /?mode=editable&tests=workspace-real or /?mode=readonly&tests=workspace-real
(async () => {
  const output = document.getElementById('browser-results');
  const host = document.getElementById('gradebook-tabulator');
  const details = document.getElementById('gradebook-components');
  const events = [], passed = [];
  const assert = (okay, label) => { if (!okay) throw new Error(label); passed.push(label); };
  for (const type of ['pointerup', 'keydown', 'copy']) {
    document.addEventListener(type, event => {
      if (event.isTrusted || type === 'copy') events.push({type, key: event.key, event});
    }, true);
  }
  const wait = async (instruction, predicate, type = null) => {
    const since = events.length;
    output.textContent = `WAIT: ${instruction}\n${passed.join('\n')}`;
    const deadline = performance.now() + 60000;
    while (performance.now() < deadline) {
      if (predicate() && (!type || events.slice(since).some(e => e.type === type))) return;
      await new Promise(resolve => setTimeout(resolve, 20));
    }
    throw new Error(`Timed out: ${instruction}`);
  };
  const first = () => host.querySelector('.tabulator-row [tabulator-field="score_10"]');
  const second = () => host.querySelector('.tabulator-row [tabulator-field="score_11"]');
  const viewport = () => {
    const holder = host.querySelector('.tabulator-tableholder');
    return [scrollX, scrollY, holder.scrollLeft, holder.scrollTop, first().getBoundingClientRect().height, first().getBoundingClientRect().width];
  };
  try {
    await wait('Wait for grid', () => document.querySelector('.pp5-gradebook')?.hidden);
    const data = JSON.parse(document.getElementById('gradebook-grid-data').textContent);
    assert(document.querySelectorAll('h1').length === 1 && document.querySelector('h1').textContent.includes('วิชาทดสอบ'), 'subject heading');
    assert(document.getElementById('gradebook-mode').textContent === (data.canScore ? 'แก้ไขคะแนนได้' : 'อ่านอย่างเดียว'), 'authoritative score mode');
    await wait('Click score-component summary', () => details.open, 'pointerup');
    for (const item of data.components) {
      assert(details.textContent.includes(item.name) && details.textContent.includes(`เต็ม ${item.max} คะแนน`), `component ${item.id} name and maximum`);
    }
    await wait('Press Enter to collapse score details', () => !details.open, 'keydown');
    assert(document.activeElement === details.querySelector('summary'), 'disclosure retains keyboard focus');
    await wait('Click first row score 10', () => document.activeElement === first(), 'pointerup');
    const before = viewport();
    if (data.canScore) {
      await wait('Type 5', () => host.querySelector('input')?.value === '5', 'keydown');
      await wait('Press Right to save and navigate', () => document.activeElement === second() && !host.querySelector('input'), 'keydown');
      await wait('Wait for saved 5.00', () => first().textContent === '5.00' && !host.querySelector('[data-pp5-saving]'));
      assert(document.getElementById('gradebook-batch-status').dataset.batchState === 'saved', 'save status remains present');
      assert(document.activeElement === second(), 'save response preserves target focus');
    } else {
      await wait('Press Right', () => document.activeElement === second(), 'keydown');
      await wait('Press native Copy shortcut', () => events.some(e => e.type === 'copy'), 'copy');
      assert(events.find(e => e.type === 'copy').event.clipboardData.getData('text/plain') === '0.00', 'read-only zero is copyable');
      assert(![...document.querySelectorAll('main a')].some(a => a.textContent.includes('จัดการรายการคะแนน')), 'unauthorized setup absent');
      assert(!host.querySelector('input'), 'read-only navigation opens no editor');
    }
    assert(viewport().every((value, i) => Math.abs(value - before[i]) < .6), 'page, holder, row height and column width remain stable');
    assert(!details.open, 'score interaction never expands contextual details');
    output.textContent = `PASS: ${passed.length} TRUSTED contextual workspace checks\n${passed.join('\n')}`;
    document.title = `PASS — trusted Gradebook workspace ${data.canScore ? 'writable' : 'read-only'}`;
  } catch (error) {
    output.textContent = `FAIL: ${error.message}\n${passed.join('\n')}`;
    document.title = 'FAIL — trusted Gradebook workspace';
  }
})();
