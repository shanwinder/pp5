(async () => {
  'use strict';
  await new Promise(resolve => window.addEventListener('load', resolve, {once:true}));
  const results = [];
  const tick = () => new Promise(resolve => setTimeout(resolve, 100));
  for (const frame of document.querySelectorAll('iframe')) {
    const failures = []; let count = 0;
    const check = (ok, label) => { count++; if (!ok) failures.push(label); };
    const d = frame.contentDocument, w = frame.contentWindow;
    check(w.innerWidth === Number(frame.width) && w.innerHeight === Number(frame.height), 'requested viewport dimensions');
    check(d.documentElement.scrollWidth <= w.innerWidth + 1, 'document overflow');
    check(d.querySelectorAll('main').length === 1 && d.querySelectorAll('h1').length === 1, 'one main/H1');
    check(new Set(Array.from(d.querySelectorAll('[id]'), n => n.id)).size === d.querySelectorAll('[id]').length, 'unique IDs');
    const panel = d.querySelector('[data-nav-panel]'), trigger = d.querySelector('[data-nav-toggle]');
    if (panel) {
      if (frame.hasAttribute('sandbox')) {
        check(!panel.hidden && w.getComputedStyle(panel).display !== 'none', 'no-JS navigation visible');
        check(w.getComputedStyle(trigger).display === 'none', 'no-JS trigger hidden');
      } else if (w.matchMedia('(max-width: 63.999rem)').matches) {
        check(panel.hidden && trigger.getAttribute('aria-expanded') === 'false', 'mobile initial state');
        trigger.click(); check(!panel.hidden && trigger.getAttribute('aria-expanded') === 'true', 'mobile open state');
        check(panel.contains(d.activeElement), 'mobile opening focus');
        panel.dispatchEvent(new w.KeyboardEvent('keydown', {key:'Escape',bubbles:true,cancelable:true}));
        check(panel.hidden && d.activeElement === trigger, 'Escape returns focus');
        trigger.click(); panel.querySelector('[data-nav-close]').click();
        check(panel.hidden && d.activeElement === trigger, 'close returns focus');
      } else check(!panel.hidden && w.getComputedStyle(trigger).display === 'none', 'desktop navigation visible');
    }
    const isRoster = frame.title.startsWith('roster ');
    const isSubjects = frame.title.startsWith('subjects ');
    const isScoreSetup = frame.title.startsWith('gradebook setup');
    const broadWorkspace = frame.title.startsWith('workspace admin') || frame.title.startsWith('roster normal') || frame.title.startsWith('subjects normal') || frame.title.startsWith('subjects empty') || frame.title.startsWith('subjects readonly');
    const workspace = d.querySelector('.pp5-workspace-shell');
    if (workspace) {
      const switcher = workspace.querySelector('details'), summary = switcher.querySelector('summary');
      check(summary.textContent.includes('เปลี่ยนห้อง'), 'switcher has meaningful label');
      summary.focus(); check(d.activeElement === summary, 'native switcher focusable');
      summary.click(); check(switcher.open, 'native disclosure opens');
      check(workspace.querySelectorAll('nav[aria-label="งานในห้องเรียน"] a[aria-current="page"]').length === 1, 'one active local section');
      check(workspace.querySelector('[aria-current="page"]').textContent === (isRoster ? 'นักเรียน' : (isSubjects ? 'รายวิชาและครู' : 'ภาพรวม')), 'implemented section active');
      const targets = workspace.querySelectorAll('details a');
      check(targets.length === (broadWorkspace ? 24 : 1), 'only fixture-authorized switch targets');
      const last = targets[targets.length - 1];
      last.focus(); last.scrollIntoView({block:'nearest',inline:'nearest'});
      const bounds = last.getBoundingClientRect(), scroll = last.closest('.pp5-workspace-choices').getBoundingClientRect();
      check(bounds.top >= scroll.top - 1 && bounds.bottom <= scroll.bottom + 1, 'last switch target reachable');
      check(workspace.querySelectorAll('nav a').length === (broadWorkspace ? 4 : (isRoster || frame.title.startsWith('workspace empty') ? 2 : 3)), 'capability-dependent local navigation');
      check(d.querySelector('form[action="/logout"][method="post"] input[name="_token"]'), 'secure logout reachable');
    }
    if (isRoster) {
      const table = d.querySelector('table#classroom-roster');
      check(table && table.querySelectorAll('thead th[scope="col"]').length === 4, 'semantic roster columns');
      check(table.querySelectorAll('tbody th[scope="row"]').length === (frame.title.startsWith('roster empty') ? 0 : 8), 'expected roster rows');
      check(table.querySelectorAll('a[href$="#move-classroom"]').length === (broadWorkspace ? 8 : 0), 'manage links only for manager');
      check(table.querySelectorAll('a[href$="#student-status"]').length === (broadWorkspace ? 8 : 0), 'status links only for manager');
      check(!table.querySelector('script, input'), 'no injected markup or hidden profile fields');
    }
    if (isSubjects) {
      const table = d.querySelector('table#classroom-subjects');
      const empty = frame.title.startsWith('subjects empty');
      check(empty ? !table : !!table, 'subjects empty/table state');
      check(!!d.querySelector('main a[href="/academic/subjects/create"]') === (broadWorkspace && !frame.title.startsWith('subjects readonly')), 'school subject creation follows permission and year');
      if (table) {
        check(table.querySelectorAll('thead th[scope="col"]').length === 6, 'semantic subject columns');
        check(table.querySelectorAll('tr[data-offering-id]').length === (frame.title.startsWith('subjects normal') ? 2 : 1), 'subject term rows');
        check(table.querySelectorAll('form[action^="/academic/teaching-assignments?"]').length === (frame.title.startsWith('subjects normal') ? 2 : 0), 'assignment actions follow authority');
        check(table.textContent.includes('คะแนนเต็มรวม') || table.textContent.includes('ยังไม่ได้ตั้งค่าการเก็บคะแนน'), 'score setup summary visible');
        check(!table.querySelector('script'), 'teacher and subject names escaped');
      }
    }
    if (isScoreSetup) {
      const main = d.querySelector('main');
      check(main.textContent.includes('การเก็บคะแนน') && main.textContent.includes('หัวข้อคะแนน'), 'teacher terminology');
      check(main.textContent.includes('ปีการศึกษา 2569 · ภาคเรียนที่ 1'), 'authoritative context visible');
      check(!!main.querySelector('a[href="/gradebooks"]'), 'legacy return available');
      check(!main.querySelector('input[name="code"],input[name="sort_order"]'), 'internal fields absent from normal form');
      if (frame.title.includes('empty')) check(main.textContent.includes('ยังไม่ได้ตั้งค่าการเก็บคะแนน'), 'empty setup state');
      if (frame.title.includes('inactive')) check(main.textContent.includes('รายการคะแนนที่ปิดใช้งาน / ประวัติ'), 'inactive structure visible');
      if (frame.title.includes('error')) check(!!main.querySelector('[role="alert"]'), 'validation error announced');
      if (frame.title.includes('closed')) check(!main.querySelector('form'), 'closed setup read only');
    }
    for (const region of d.querySelectorAll('.pp5-table-scroll')) {
      check(region.getBoundingClientRect().right <= w.innerWidth + 1, 'table contained');
      check(region.tabIndex === 0 && region.getAttribute('aria-label'), 'table named/reachable');
      region.scrollLeft = region.scrollWidth;
      const action = region.querySelector('td:last-child a,td:last-child button');
      if (action) {
        action.focus(); action.scrollIntoView({block:'center',inline:'nearest',behavior:'instant'});
        const a = action.getBoundingClientRect(), r = region.getBoundingClientRect();
        check(a.left >= r.left - 1 && a.right <= r.right + 1, 'table action reachable');
      }
      region.scrollLeft = 0;
    }
    if (frame.title.startsWith('import preview')) {
      const nameColumn = d.querySelector('thead th:nth-child(3)');
      check(nameColumn.getBoundingClientRect().width >= 12 * parseFloat(w.getComputedStyle(d.documentElement).fontSize), 'Thai name column remains readable');
    }
    // Check actual occlusion, not only the presence of an outline declaration.
    const controls = d.querySelectorAll('main a,main summary,main button,main input:not([type="hidden"]):not(:disabled),main select,main textarea,main .pp5-table-scroll:not([hidden]),main .pp5-gradebook-tabulator');
    for (const control of controls) {
      if (control.disabled) continue;
      control.focus({preventScroll:true});
      control.scrollIntoView({block:'center',inline:'nearest',behavior:'instant'});
      const rect = control.getBoundingClientRect();
      check(d.activeElement === control && w.getComputedStyle(control).outlineStyle !== 'none', 'visible focus '+(control.id || control.tagName));
      if (!control.closest('.pp5-table-scroll') || control.classList.contains('pp5-table-scroll')) {
        check(rect.left >= -1 && rect.right <= w.innerWidth + 1, 'control contained '+control.id);
      }
      if (!control.classList.contains('pp5-table-scroll')) {
        const hit = d.elementFromPoint((rect.left + rect.right) / 2, (rect.top + rect.bottom) / 2);
        check(hit && (hit === control || control.contains(hit)), 'focus covered '+(control.id || control.textContent.trim().slice(0,30))+' top='+Math.round(rect.top)+' hit='+(hit?.className || 'none'));
      }
      if (control.matches('[data-score-input]')) {
        const grid = control.closest('.pp5-gradebook'), identity = control.closest('tr').querySelector('th');
        check(rect.left >= identity.getBoundingClientRect().right + 3 && rect.right <= grid.getBoundingClientRect().right - 3, 'score outline clear of sticky identity');
        check(rect.top >= d.querySelector('thead').getBoundingClientRect().bottom + 3, 'score outline clear of sticky header');
      }
    }
    if (/^gradebook \d/.test(frame.title)) {
      const host = d.getElementById('gradebook-tabulator');
      check(host && !host.hidden && d.querySelector('.pp5-gradebook').hidden, 'Tabulator Gradebook initialized');
      const score = host?.querySelector('.tabulator-row [tabulator-field="score_10"]');
      if (score) {
        score.focus({preventScroll:true}); score.scrollIntoView({block:'center',inline:'nearest',behavior:'instant'});
        const identity = score.closest('.tabulator-row').querySelector('.pp5-grid-identity');
        check(score.getBoundingClientRect().left >= identity.getBoundingClientRect().right - 1, 'score focus clear of frozen identity');
        check(w.getComputedStyle(score).outlineStyle !== 'none', 'Tabulator score focus visible');
      }
    }
    check(d.documentElement.scrollWidth <= w.innerWidth + 1, 'no overflow after focus');
    results.push(`${frame.title}: ${failures.length ? 'FAIL '+[...new Set(failures)].join('; ') : 'PASS '+count+' checks'}`);
    d.activeElement?.blur(); w.scrollTo(0,0);
  }
  const frame = document.querySelector('iframe[title="students 390x844"]');
  const d = frame.contentDocument;
  const trigger = d.querySelector('[data-nav-toggle]'), panel = d.querySelector('[data-nav-panel]');
  trigger.focus(); frame.width = '1440'; await tick();
  const desktop = !panel.hidden && panel.contains(d.activeElement) && trigger.getAttribute('aria-expanded') === 'true';
  frame.width = '390'; await tick();
  const mobile = panel.hidden && d.activeElement === trigger && trigger.getAttribute('aria-expanded') === 'false';
  trigger.click(); const reopened = !panel.hidden; panel.querySelector('[data-nav-close]').click();
  results.push(`resize mobile → desktop → mobile: ${desktop && mobile && reopened ? 'PASS' : 'FAIL'}`);
  document.getElementById('browser-results').textContent = results.join('\n');
  document.title = results.some(r => r.includes(': FAIL')) ? 'FAIL — PP5 cross-screen' : 'PASS — PP5 cross-screen';
})();
