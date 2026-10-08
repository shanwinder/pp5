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
    assert(!details.open, 'score details collapsed initially');
    details.querySelector('summary').click();
    assert(details.open && details.textContent.includes('งาน') && details.textContent.includes('เต็ม 15.50 คะแนน')
      && details.textContent.includes('สอบ') && details.textContent.includes('เต็ม 20.00 คะแนน'), 'one-page score names and maxima');
    details.querySelector('summary').click();
    assert(!details.open && !host.closest('details'), 'collapse keeps grid mounted and outside disclosure');
    assert(document.querySelectorAll('#gradebook-guidance').length === 1 && host.getAttribute('aria-describedby') === 'gradebook-guidance', 'stable help relationship');
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
