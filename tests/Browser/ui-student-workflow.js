(() => {
  'use strict';
  if (window === window.top && location.pathname === '/') {
    const results = new Map();
    window.addEventListener('message', event => {
      if (event.origin !== location.origin || event.data?.type !== 'pp5-student-check') return;
      const frame = Array.from(document.querySelectorAll('iframe')).find(item => item.contentWindow === event.source);
      if (!frame) return;
      results.set(frame.title, event.data.result);
      document.getElementById('browser-results').textContent = Array.from(results, ([key, value]) => `${key}: ${value}`).join('\n');
      if (results.size === document.querySelectorAll('iframe').length) document.title = Array.from(results.values()).every(value => value.startsWith('PASS')) ? 'PASS — PP5 student workflows' : 'FAIL — PP5 student workflows';
    });
    return;
  }
  const checks = [];
  const assert = (ok, label) => { if (!ok) throw new Error(label); checks.push(label); };
  try {
    assert(document.querySelectorAll('html,body,main,h1').length === 4, 'one document/main/H1');
    assert(document.querySelectorAll('script:not([src]),[onerror]').length === 0, 'hostile data remains text');
    assert(document.documentElement.scrollWidth <= innerWidth + 1, 'no whole-page horizontal overflow');
    assert(document.querySelectorAll('link[href^="http"],script[src^="http"]').length === 0, 'local assets only');
    assert(getComputedStyle(document.body).fontFamily.includes('system-ui'), 'CSS loaded');
    assert(!/[0-9]{13}/.test(document.querySelector('main').textContent), 'no raw national ID');
    const idField = document.querySelector('input[name="national_id"]');
    assert(!idField || idField.value === '', 'fixture identity field empty');
    for (const control of document.querySelectorAll('main input:not([type="hidden"]),main select')) {
      assert(control.id && control.labels.length === 1 && control.labels[0].htmlFor === control.id, 'explicit label');
      assert(document.querySelectorAll(`[id="${control.id}"]`).length === 1, 'unique field ID');
      assert(control.getBoundingClientRect().right <= innerWidth + 1, 'field stays contained');
      control.focus();
      assert(document.activeElement === control && getComputedStyle(control).outlineStyle !== 'none', 'field focus visible');
    }
    for (const region of document.querySelectorAll('.pp5-table-scroll')) {
      assert(region.tabIndex === 0 && region.getAttribute('aria-label'), 'named keyboard scroll region');
      assert(region.getBoundingClientRect().right <= innerWidth + 1, 'table scroll region contained');
      region.focus(); assert(document.activeElement === region, 'table keyboard reachable');
      region.scrollLeft = region.scrollWidth;
    }
    for (const action of document.querySelectorAll('main a,main button')) {
      action.focus(); assert(document.activeElement === action, 'action reachable');
    }
    const tab = new KeyboardEvent('keydown', {key:'Tab',bubbles:true,cancelable:true});
    document.querySelector('main').dispatchEvent(tab); assert(!tab.defaultPrevented, 'native Tab retained');
    const page = new URLSearchParams(location.search).get('page');
    const variant = new URLSearchParams(location.search).get('variant');
    if (page.endsWith('/index') && !page.includes('student-import')) {
      assert(document.querySelector('input[name="q"]').value === 'TEST', 'search preserved');
    }
    if (page === 'academic/enrollments/edit') {
      assert(Array.from(document.querySelectorAll('main h2')).map(item => item.textContent).join('|') === 'สถานะการลงทะเบียน|การจัดห้องเรียน|ประวัติการจัดห้องเรียน', 'separate status, placement and history');
      assert(document.querySelectorAll('main form').length === (variant === 'normal' ? 2 : 0), 'read-only states have no mutation controls');
    }
    if (page.includes('student-import')) {
      assert(document.querySelectorAll('ol[aria-label="ขั้นตอนนำเข้า"] li').length === 3, 'three import steps');
      if (page.endsWith('preview')) {
        assert(Boolean(document.querySelector('form[action$="/apply"]')) === (variant === 'normal'), 'apply availability preserved');
        assert(Boolean(document.querySelector('form[action$="/cancel"]')) === ['normal','noop','error'].includes(variant), 'cancel availability preserved');
      } else assert(document.querySelector('input[type="file"]').accept === '.csv', 'CSV-only picker');
    }
    // Exercise opt-in with native submit events but block test navigation after observing app.js.
    const originalConfirm = window.confirm;
    let confirmations = 0;
    window.confirm = () => { confirmations++; return false; };
    for (const form of document.querySelectorAll('main form')) {
      let rejected;
      form.addEventListener('submit', event => { rejected = event.defaultPrevented; event.preventDefault(); }, {once:true});
      const before = confirmations;
      form.dispatchEvent(new Event('submit', {bubbles:true,cancelable:true}));
      assert(rejected === form.hasAttribute('data-confirm'), 'only explicit high-impact forms cancel on rejection');
      assert(confirmations - before === (form.hasAttribute('data-confirm') ? 1 : 0), 'ordinary forms never prompt');
    }
    window.confirm = originalConfirm;
    window.parent.postMessage({type:'pp5-student-check',result:`PASS ${checks.length} checks`},location.origin);
  } catch (error) { window.parent.postMessage({type:'pp5-student-check',result:`FAIL after ${checks.length}: ${error.message}`},location.origin); }
})();
