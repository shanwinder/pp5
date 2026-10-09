(() => {
  'use strict';
  if (window === window.top && location.pathname === '/') {
    const results = new Map();
    window.addEventListener('message', event => {
      if (event.origin !== location.origin || event.data?.type !== 'pp5-admin-check') return;
      const frame = Array.from(document.querySelectorAll('iframe')).find(item => item.contentWindow === event.source);
      if (!frame) return;
      results.set(frame.title, event.data.result);
      document.getElementById('browser-results').textContent = Array.from(results, ([key, value]) => `${key}: ${value}`).join('\n');
      if (results.size === document.querySelectorAll('iframe').length) document.title = Array.from(results.values()).every(value => value.startsWith('PASS')) ? 'PASS — PP5 administration' : 'FAIL — PP5 administration';
    });
    return;
  }
  const checks = [];
  const assert = (ok, label) => { if (!ok) throw new Error(label); checks.push(label); };
  try {
    assert(document.querySelectorAll('html, body, main, h1').length === 4, 'one document/main/H1');
    assert(document.querySelectorAll('script:not([src])').length === 0, 'hostile values stay text');
    assert(document.documentElement.scrollWidth <= innerWidth + 1, 'no page overflow');
    assert(document.querySelectorAll('link[href^="http"],script[src^="http"]').length === 0, 'local assets');
    assert(getComputedStyle(document.body).fontFamily.includes('system-ui'), 'CSS loaded');
    for (const control of document.querySelectorAll('main input:not([type="hidden"]),main select')) {
      assert(control.labels.length === 1, 'explicit label');
      if (!control.closest('.pp5-table-scroll')) assert(control.getBoundingClientRect().right <= innerWidth, 'field contained');
      if (control.type === 'password') assert(control.value === '', 'password empty');
    }
    for (const region of document.querySelectorAll('.pp5-table-scroll')) {
      assert(region.tabIndex === 0 && region.getAttribute('aria-label'), 'named keyboard scroll region');
      assert(region.getBoundingClientRect().right <= innerWidth + 1, 'table contained');
      region.scrollLeft = region.scrollWidth;
      const action = region.querySelector('a,summary,button');
      if (action) { action.focus(); assert(document.activeElement === action, 'action reachable'); }
    }
    for (const action of document.querySelectorAll('main tbody a,main tbody summary')) {
      assert(action.getAttribute('aria-label') && action.getAttribute('title') && action.getAttribute('data-tooltip'), 'named row icon with help');
    }
    if (new URLSearchParams(location.search).get('state') === 'readonly') assert(!document.querySelector('main form[method="post"],main a[href*="/create"],main a[href*="/setup"]'), 'read-only has no mutation controls');
    assert(!document.querySelector('link[href*="app-compat"],link[href*="bootstrap"]'), 'Tabler-only migrated body');
    for (const detail of document.querySelectorAll('[data-admin-detail]')) {
      const summary = detail.querySelector('summary');
      summary.click();
      assert(detail.open && detail.querySelector('form').offsetHeight > 0, 'native disclosure exposes POST');
      const submit = detail.querySelector('button[type="submit"]');
      submit.focus(); assert(document.activeElement === submit, 'opened action keyboard reachable');
      if (document.querySelector('[data-tooltip-ready]')) {
        submit.dispatchEvent(new KeyboardEvent('keydown', {key:'Escape', bubbles:true}));
        assert(!detail.open && document.activeElement === summary, 'Escape restores summary focus');
      } else { summary.click(); assert(!detail.open, 'native collapse without enhancement'); }
    }
    const first = document.querySelector('main input:not([type="hidden"]),main select,main a');
    if (first) { first.focus(); assert(document.activeElement === first && getComputedStyle(first).outlineStyle !== 'none', 'visible focus'); }
    for (const form of document.querySelectorAll('form')) form.addEventListener('submit', event => event.preventDefault());
    window.parent.postMessage({type:'pp5-admin-check',result:`PASS ${checks.length} checks`}, location.origin);
  } catch (error) { window.parent.postMessage({type:'pp5-admin-check',result:`FAIL after ${checks.length}: ${error.message}`},location.origin); }
})();
