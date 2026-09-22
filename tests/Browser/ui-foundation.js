(() => {
  'use strict';
  if (window === window.top) {
    const results = new Map();
    window.addEventListener('message', event => {
      if (event.origin !== location.origin || event.data?.type !== 'pp5-ui-check') return;
      const frame = Array.from(document.querySelectorAll('iframe')).find(item => item.contentWindow === event.source);
      if (!frame) return;
      results.set(frame.title, event.data.result);
      const passed = results.size === 5 && Array.from(results.values()).every(result => result.startsWith('PASS'));
      document.getElementById('browser-results').textContent = Array.from(results, ([key, value]) => `${key}: ${value}`).join('\n');
      document.title = passed ? 'PASS — PP5 UI foundation' : results.size === 5 ? 'FAIL — PP5 UI foundation' : 'Running — PP5 UI foundation';
    });
    return;
  }

  const checks = [];
  const assert = (ok, message) => { if (!ok) throw new Error(message); checks.push(message); };
  try {
    const trigger = document.querySelector('[data-nav-toggle]');
    const panel = document.querySelector('[data-nav-panel]');
    const close = panel.querySelector('[data-nav-close]');
    const field = document.getElementById('fixture-name');
    const mobile = matchMedia('(max-width: 63.999rem)').matches;
    assert(getComputedStyle(document.body).backgroundColor === 'rgb(244, 246, 248)', 'base CSS loaded');
    assert(document.styleSheets.length === 2, 'two local stylesheets');
    for (const sheet of document.styleSheets) assert(sheet.cssRules.length > 0, 'stylesheet parsed');
    assert(document.documentElement.scrollWidth <= innerWidth + 1, 'no page overflow');
    const table = document.querySelector('.pp5-table-scroll');
    assert(getComputedStyle(table).overflowX === 'auto', 'table scrolls deliberately');
    assert(getComputedStyle(field).fontSize === '16px', 'readable form control size');

    if (location.pathname === '/no-js') {
      assert(!panel.hidden && getComputedStyle(panel).display !== 'none', 'navigation available without enhancement');
      assert(getComputedStyle(trigger).display === 'none', 'no dead menu trigger without enhancement');
    } else if (mobile) {
      assert(panel.hidden && trigger.getAttribute('aria-expanded') === 'false', 'mobile starts collapsed');
      assert(getComputedStyle(trigger).display !== 'none', 'mobile trigger visible');
      trigger.click();
      assert(!panel.hidden && trigger.getAttribute('aria-expanded') === 'true', 'menu opens');
      assert(panel.contains(document.activeElement), 'opening moves focus into menu');
      panel.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
      assert(panel.hidden && document.activeElement === trigger, 'Escape closes and restores focus');
      trigger.click(); close.click();
      assert(panel.hidden && document.activeElement === trigger, 'close button restores focus');
      trigger.click(); trigger.click();
      assert(panel.hidden && trigger.getAttribute('aria-expanded') === 'false', 'toggle closes');
      trigger.click(); panel.querySelector('a').click();
      assert(panel.hidden, 'destination closes menu');
      assert(document.activeElement === document.getElementById('fixture-content'), 'destination keeps focus on content');
      field.focus();
      field.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
      assert(document.activeElement === field, 'unrelated Escape leaves form alone');
    } else {
      assert(!panel.hidden, 'desktop navigation visible');
      assert(getComputedStyle(trigger).display === 'none', 'desktop trigger hidden');
    }
    const unrelated = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
    field.dispatchEvent(unrelated);
    assert(!unrelated.defaultPrevented, 'native Tab retained');
    window.parent.postMessage({ type: 'pp5-ui-check', result: `PASS ${checks.length} checks` }, location.origin);
  } catch (error) {
    window.parent.postMessage({ type: 'pp5-ui-check', result: `FAIL after ${checks.length}: ${error.message}` }, location.origin);
  }
})();
