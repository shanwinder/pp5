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
    const controls = d.querySelectorAll('main a,main button,main input:not([type="hidden"]),main select,main textarea,main .pp5-table-scroll');
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
