// Synthetic DOM checks supplement the separately operated trusted browser journey.
// Run /?mode=editable&tests=workspace and /?mode=readonly&tests=workspace.
(async () => {
  const output = document.getElementById('browser-results');
  const checks = [];
  const assert = (okay, label) => { if (!okay) throw new Error(label); checks.push(label); };
  try {
    for (let i = 0; i < 250 && !document.querySelector('.pp5-gradebook')?.hidden; i++)
      await new Promise(resolve => setTimeout(resolve, 20));
    const data = JSON.parse(document.getElementById('gradebook-grid-data').textContent);
    const host = document.getElementById('gradebook-tabulator');
    const details = document.getElementById('gradebook-components');
    assert(document.querySelectorAll('h1').length === 1, 'one contextual page heading');
    assert(document.querySelector('h1').textContent.includes('วิชาทดสอบ'), 'subject in heading');
    assert(document.getElementById('gradebook-context-title').parentElement.textContent.includes('ภาคเรียน 1 · ปีการศึกษา 2569'), 'term and year in context');
    assert(document.getElementById('gradebook-mode').textContent === (data.canScore ? 'แก้ไขคะแนนได้' : 'อ่านอย่างเดียว'), 'authoritative mode');
    assert(!details && !document.querySelector('.pp5-gradebook-heading'), 'overview and heading strips absent');
    for (const item of data.components) {
      const header = host.querySelector(`.tabulator-col[tabulator-field="score_${item.id}"]`);
      assert(header.textContent.includes(item.name) && header.textContent.includes(`เต็ม ${item.max}`), 'component name and authoritative maximum in score header');
    }
    assert(!host.closest('details'), 'grid outside compact disclosures');
    assert(document.querySelectorAll('#gradebook-guidance').length === 1 && host.getAttribute('aria-describedby') === 'gradebook-guidance', 'stable help relationship');
    const guidance = document.getElementById('gradebook-guidance');
    assert(!document.getElementById('gradebook-help') && !document.getElementById('gradebook-range-tool'), 'help and Fill/Clear UI entry points are retired');
    assert(!guidance.closest('details') && !guidance.hidden && !guidance.hasAttribute('aria-hidden')
      && guidance.getBoundingClientRect().height <= 1 && guidance.textContent.includes('0.00 คือศูนย์ที่บันทึกแล้ว')
      && guidance.textContent.includes('Escape นอกช่องแก้ไข'), 'complete visually hidden description remains accessible');
    assert(!guidance.textContent.includes('เครื่องมือช่วงคะแนน') && !document.getElementById('gradebook-range-actions'), 'no instructions or form for inaccessible tools');
    assert(document.getElementById('gradebook-range-status').getAttribute('aria-live') === 'polite', 'live range feedback retained');
    if (data.canScore) {
      const feedback = document.getElementById('gradebook-batch-status');
      assert(!feedback.closest('details') && feedback.getAttribute('aria-live') === 'polite'
        && feedback.getAttribute('aria-atomic') === 'true' && feedback.getBoundingClientRect().height >= 21,
        'visible reserved live save feedback remains outside help');
    }
    assert(![...document.querySelectorAll('link')].some(link => link.href.includes('bootstrap')), 'single Tabler foundation');
    assert(document.documentElement.scrollWidth <= innerWidth + 1, 'contained document width');
    assert(data.canScore || ![...document.querySelectorAll('main a')].some(link => link.textContent.includes('จัดการรายการคะแนน')), 'unauthorized setup hidden');
    output.textContent = `PASS: ${checks.length} SYNTHETIC contextual workspace checks\n${checks.join('\n')}`;
    document.title = 'PASS — Gradebook workspace';
  } catch (error) {
    output.textContent = `FAIL: ${error.message}\n${checks.join('\n')}`;
    document.title = 'FAIL — Gradebook workspace';
  }
})();
