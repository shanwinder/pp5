(() => {
  'use strict';
  if (window === window.top) {
    const results = new Map();
    window.addEventListener('message', event => {
      if (event.origin !== location.origin || event.data?.type !== 'pp5-navigation-check') return;
      const frame = Array.from(document.querySelectorAll('iframe')).find(item => item.contentWindow === event.source);
      if (!frame) return;
      results.set(frame.title, event.data.result);
      document.getElementById('browser-results').textContent = Array.from(results, ([key, value]) => `${key}: ${value}`).join('\n');
      if (results.size === 6) document.title = Array.from(results.values()).every(value => value.startsWith('PASS')) ? 'PASS — PP5 navigation' : 'FAIL — PP5 navigation';
    });
    return;
  }
  const checks = [];
  const assert = (ok, label) => { if (!ok) throw new Error(label); checks.push(label); };
  try {
    const panel = document.querySelector('[data-nav-panel]');
    const toggle = document.querySelector('[data-nav-toggle]');
    const main = document.getElementById('main-content');
    const mobile = matchMedia('(max-width: 63.999rem)').matches;
    assert(document.querySelectorAll('main, .pp5-shell, nav[aria-label="เมนูหลัก"]').length === 3, 'single shell/main/nav');
    assert(document.querySelectorAll('script:not([src])').length === 0, 'hostile names do not become scripts');
    assert(document.querySelector('.pp5-school-name').textContent.includes('<script>'), 'hostile school name is plain text');
    assert(document.querySelector('.pp5-user-name').textContent.includes('<script>'), 'hostile display name is plain text');
    assert(document.documentElement.scrollWidth <= innerWidth + 1, 'long Thai names do not overflow page');
    for (const element of document.querySelectorAll('.pp5-school-name, .pp5-user-name')) {
      assert(element.scrollWidth <= element.clientWidth + 1, 'identity wraps within its container');
    }
    assert(document.querySelectorAll('nav a[aria-current="page"]').length === 1, 'current page marked');
    assert(document.querySelector('form[action="/logout"]').method === 'post', 'logout remains POST');
    assert(document.querySelector('.pp5-skip-link').hash === '#main-content', 'skip link targets main');
    if (location.pathname === '/no-js') {
      assert(!panel.hidden && getComputedStyle(panel).display !== 'none', 'navigation visible without app.js');
      assert(getComputedStyle(toggle).display === 'none', 'no unusable toggle without app.js');
    } else if (mobile) {
      assert(panel.hidden && toggle.getAttribute('aria-expanded') === 'false', 'mobile starts collapsed');
      toggle.click();
      assert(!panel.hidden && toggle.getAttribute('aria-expanded') === 'true', 'toggle opens navigation');
      assert(panel.contains(document.activeElement), 'focus enters navigation');
      assert(document.activeElement.getBoundingClientRect().top >= document.querySelector('.pp5-topbar').getBoundingClientRect().bottom, 'focused menu link is not obscured by long identity header');
      assert(document.documentElement.scrollWidth <= innerWidth + 1, 'long navigation text wraps');
      panel.dispatchEvent(new KeyboardEvent('keydown', {key:'Escape', bubbles:true}));
      assert(panel.hidden && document.activeElement === toggle, 'Escape restores toggle focus');
      toggle.click(); panel.querySelector('[data-nav-close]').click();
      assert(panel.hidden && document.activeElement === toggle, 'close restores toggle focus');
      toggle.click(); panel.querySelector('a').click();
      assert(panel.hidden && document.activeElement === main, 'same-page destination focuses main');
    } else {
      assert(!panel.hidden && getComputedStyle(toggle).display === 'none', 'desktop navigation available');
      assert(panel.scrollWidth <= panel.clientWidth + 1, 'long desktop navigation wraps');
    }
    const tab = new KeyboardEvent('keydown', {key:'Tab', bubbles:true, cancelable:true});
    main.dispatchEvent(tab);
    assert(!tab.defaultPrevented, 'native Tab retained');
    window.parent.postMessage({type:'pp5-navigation-check', result:`PASS ${checks.length} checks`}, location.origin);
  } catch (error) {
    window.parent.postMessage({type:'pp5-navigation-check', result:`FAIL after ${checks.length}: ${error.message}`}, location.origin);
  }
})();
