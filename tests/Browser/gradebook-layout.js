(async () => {
  'use strict';
  const results = [];
  for (const frame of document.querySelectorAll('iframe')) {
    let count = 0;
    const assert = (condition, label) => { if (!condition) throw new Error(label); count++; };
    try {
      for (let attempt = 0; attempt < 250 && !frame.contentDocument?.querySelector('main h1'); attempt++)
        await new Promise(resolve => setTimeout(resolve, 20));
      const d = frame.contentDocument, w = frame.contentWindow;
      const mode = frame.title.split(' ')[0], width = w.innerWidth;
      const host = d.getElementById('gradebook-tabulator');
      const fallback = d.querySelector('.pp5-gradebook');
      if (host && mode !== 'nojs') {
        for (let attempt = 0; attempt < 100 && !fallback.hidden; attempt++) await new Promise(resolve => setTimeout(resolve, 20));
      }
      assert(d.querySelectorAll('html,body,main,h1').length === 4, 'single shell');
      assert(d.documentElement.scrollWidth <= width + 1, 'no document overflow');
      assert(d.querySelectorAll('script:not([src]):not([type="application/json"]),[onerror]').length === 0, 'no executable inline script');
      assert(!/[0-9]{13}/.test(d.body.textContent), 'no raw national ID');
      if (host) {
        assert(host.getAttribute('aria-label') && fallback, 'named grid and semantic fallback exist');
        const data = JSON.parse(d.getElementById('gradebook-grid-data').textContent);
        assert(data.components.length === (mode === 'no-components' ? 0 : 2)
          && data.rows.length === (mode === 'empty' ? 0 : 4), 'bootstrap uses expected read model');
        assert(!d.getElementById('gradebook-grid-data').innerHTML.includes('<script>'), 'bootstrap escapes tags');
        if (mode === 'nojs') {
          assert(host.hidden && !fallback.hidden, 'no-JS semantic fallback remains readable');
          assert(d.querySelector('noscript').textContent.includes('JavaScript'), 'no-JS save limitation visible');
          assert(d.querySelector('table caption') && fallback.scrollWidth > fallback.clientWidth, 'fallback table scrolls internally');
        } else {
          assert(fallback.hidden && !host.hidden, 'Tabulator replaces fallback only after build');
          assert([...fallback.querySelectorAll('[data-score-input]')].every(input => input.disabled), 'fallback inputs cannot double-write');
          const holder = host.querySelector('.tabulator-tableholder');
          assert(holder && holder.scrollWidth >= holder.clientWidth, 'grid has internal horizontal scroll');
          assert(host.getBoundingClientRect().left >= -1 && host.getBoundingClientRect().right <= width + 1, 'grid contained in viewport');
          assert(d.querySelectorAll('#gradebook-tabulator .tabulator-row').length === (mode === 'empty' ? 0 : 4), 'roster rendered in grid');
          if (mode !== 'empty') {
            const identity = host.querySelector('.tabulator-row .pp5-grid-identity');
            assert(identity && identity.textContent.includes('hostile'), 'long name remains present as text');
            holder.scrollLeft = 300;
            assert(Math.abs(identity.getBoundingClientRect().left - host.getBoundingClientRect().left) < 3, 'student identity stays frozen');
            const header = host.querySelector('.tabulator-header');
            assert(header && header.getBoundingClientRect().top >= host.getBoundingClientRect().top - 1, 'header remains aligned');
            holder.scrollLeft = 0;
            if (mode === 'no-components') {
              assert(!host.querySelector('[tabulator-field^="score_"]'), 'no score columns when components are absent');
              assert(d.body.textContent.includes('ยังไม่มีองค์ประกอบคะแนนที่เปิดใช้งาน'), 'empty component guidance remains visible');
            } else {
              const first = host.querySelector('.tabulator-row [tabulator-field="score_10"]');
              const second = host.querySelector('.tabulator-row [tabulator-field="score_11"]');
              assert(first.textContent === '' && second.textContent === '0.00', 'blank and zero remain distinct');
              first.click(); first.focus();
              assert(d.activeElement === first && w.getComputedStyle(first).outlineStyle !== 'none', 'score focus has visible outline');
              assert(first.getBoundingClientRect().left >= identity.getBoundingClientRect().right - 1, 'frozen identity does not cover score');
            }
          }
          if (mode === 'readonly') {
            assert(!d.querySelector('main input,[hx-post],script[src*="htmx"]'), 'read-only page has no mutation inputs');
            assert(!host.querySelector('.tabulator-editable'), 'read-only grid has no editable cell');
          } else if (mode !== 'empty') {
            const panel = d.getElementById('gradebook-range-actions');
            assert(panel && !panel.hidden && panel.getBoundingClientRect().right <= width + 1, 'range controls fit viewport');
            assert(d.getElementById('gradebook-fill-value').labels.length === 1, 'fill control has native label');
          }
        }
      } else {
        for (const input of d.querySelectorAll('main input:not([type="hidden"])')) {
          assert(input.labels.length === 1 && input.labels[0].htmlFor === input.id, 'setup field labelled');
          assert(input.getBoundingClientRect().right <= width + 1, 'setup field contained');
        }
        if (mode === 'closed-setup') assert(!d.querySelector('main form'), 'closed setup read-only');
      }
      for (const link of d.querySelectorAll('main a')) assert(link.getBoundingClientRect().right <= width + 1, 'action contained');
      results.push(`${frame.title}: PASS ${count} checks`);
    } catch (error) { results.push(`${frame.title}: FAIL after ${count}: ${error.message}`); }
  }
  document.getElementById('browser-results').textContent = results.join('\n');
  document.title = results.every(result => result.includes(': PASS ')) ? 'PASS — Gradebook layout' : 'FAIL — Gradebook layout';
})();
