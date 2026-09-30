(() => {
  'use strict';
  if (window === window.top) {
    const results = new Map();
    window.addEventListener('message', event => {
      if (event.origin !== location.origin || event.data?.type !== 'pp5-entry-check') return;
      const frame = Array.from(document.querySelectorAll('iframe')).find(item => item.contentWindow === event.source);
      if (!frame) return;
      results.set(frame.title, event.data.result);
      document.getElementById('browser-results').textContent = Array.from(results, ([key, value]) => `${key}: ${value}`).join('\n');
      if (results.size === 36) document.title = Array.from(results.values()).every(value => value.startsWith('PASS')) ? 'PASS — PP5 entry surfaces' : 'FAIL — PP5 entry surfaces';
    });
    return;
  }
  const checks = [];
  const assert = (ok, label) => { if (!ok) throw new Error(label); checks.push(label); };
  try {
    assert(document.querySelectorAll('html, body, main, h1').length === 4, 'one document/main/H1');
    assert(document.querySelectorAll('script:not([src])').length === 0, 'hostile data stays text');
    assert(document.documentElement.scrollWidth <= innerWidth + 1, 'no page overflow');
    assert(document.querySelectorAll('link[href^="http"], script[src^="http"]').length === 0, 'local assets');
    assert(getComputedStyle(document.body).fontFamily.includes('system-ui'), 'local CSS loaded');
    const page = new URLSearchParams(location.search).get('page');
    if (page === 'login') {
      for (const name of ['username', 'password']) {
        const input = document.getElementById(name);
        assert(input.labels.length === 1, `${name} label`);
        input.focus();
        assert(document.activeElement === input && getComputedStyle(input).outlineStyle !== 'none', `${name} focus visible`);
        assert(input.getBoundingClientRect().right <= innerWidth, `${name} fits`);
      }
      assert(document.querySelector('button[type="submit"]').textContent === 'เข้าสู่ระบบ', 'clear submit');
      document.querySelector('form').addEventListener('submit', event => event.preventDefault());
    }
    for (const region of document.querySelectorAll('.pp5-table-scroll')) {
      assert(region.tabIndex === 0 && region.getAttribute('role') === 'region', 'table keyboard scroll region');
      assert(region.clientWidth <= innerWidth, 'table contained');
      region.scrollLeft = region.scrollWidth;
      const action = region.querySelector('a');
      action.focus();
      assert(document.activeElement === action, 'open action reachable');
    }
    if (page === 'empty') assert(document.querySelector('main').textContent.includes('ยังไม่มีรายวิชาที่เข้าถึงได้ในขณะนี้'), 'empty state');
    if (['gradebooks','multiple','readonly','historical'].includes(page)) {
      const main = document.querySelector('main');
      assert(main.querySelector('h1').textContent === 'งานสอนของฉัน', 'task heading');
      assert(main.querySelectorAll('.pp5-teaching-item').length === (page === 'multiple' ? 5 : page === 'historical' ? 1 : 3), 'offering count');
      assert([...main.querySelectorAll('.pp5-teaching-actions a')].every(a => a.getAttribute('href') && a.textContent.includes('ภาคเรียน')), 'contextual native links');
      if (page === 'readonly' || page === 'historical') assert(!main.textContent.includes('กรอกคะแนน'), 'read-only action');
      for (const action of main.querySelectorAll('.pp5-teaching-actions a')) {
        action.focus(); assert(document.activeElement === action && getComputedStyle(action).outlineStyle !== 'none', 'visible action focus');
        assert(action.getBoundingClientRect().right <= innerWidth + 1, 'action contained');
      }
    }
    if (page === '403' || page === '404') assert(document.querySelector('a').getAttribute('href') === '/login', 'safe error destination');
    window.parent.postMessage({type:'pp5-entry-check', result:`PASS ${checks.length} checks`}, location.origin);
  } catch (error) {
    window.parent.postMessage({type:'pp5-entry-check', result:`FAIL after ${checks.length}: ${error.message}`}, location.origin);
  }
})();
