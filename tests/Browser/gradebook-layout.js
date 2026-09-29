(() => {
  'use strict';
  window.addEventListener('load', () => {
    const results = [];
    for (const frame of document.querySelectorAll('iframe')) {
      let count = 0;
      const assert = (ok, label) => { if (!ok) throw new Error(label); count++; };
      try {
        const d = frame.contentDocument, w = frame.contentWindow;
        const mode = frame.title.split(' ')[0], width = w.innerWidth;
        assert(d.querySelectorAll('html,body,main,h1').length === 4, 'single shell');
        assert(d.documentElement.scrollWidth <= width + 1, 'no document overflow');
        assert(d.querySelectorAll('script:not([src]),[style],[onerror]').length === 0, 'escaped hostile text and no inline styles');
        assert(!/[0-9]{13}/.test(d.body.textContent), 'no raw national ID');
        const grid = d.querySelector('.pp5-gradebook');
        if (grid) {
          assert(grid.tabIndex === 0 && grid.getAttribute('aria-label'), 'named keyboard scrolling region');
          assert(grid.scrollWidth > grid.clientWidth, 'deliberately wide grid');
          assert(d.querySelectorAll('.pp5-historical input,.pp5-historical [hx-post]').length === 0, 'history read-only');
          const identity = d.querySelector('tbody .pp5-gradebook-identity');
          if (identity) {
            grid.scrollLeft = 300;
            assert(Math.abs(identity.getBoundingClientRect().left-grid.getBoundingClientRect().left) < 2, 'identity stays sticky');
            grid.scrollTop = 200;
            const head = d.querySelector('thead th');
            assert(Math.abs(head.getBoundingClientRect().top-grid.getBoundingClientRect().top) < 2, 'header stays sticky');
            grid.scrollLeft = 0; grid.scrollTop = 0;
            assert(identity.textContent.includes('hostile'), 'full long name remains available as text');
          }
          const input = d.querySelector('[data-score-input]');
          if (input) {
            if (mode !== 'nojs') {
              const panel = d.getElementById('gradebook-range-actions');
              assert(panel && !panel.hidden && panel.getBoundingClientRect().right <= width + 1,
                'range actions remain inside document width');
              assert(d.getElementById('gradebook-fill-value').labels.length === 1,
                'range scalar has native label');
              for (const control of panel.querySelectorAll('input,button')) {
                const bounds = control.getBoundingClientRect();
                assert(bounds.left >= -1 && bounds.right <= width + 1 && bounds.width > 0,
                  'range action reachable at viewport width');
              }
            }
            input.focus();
            const rect=input.getBoundingClientRect(), bounds=grid.getBoundingClientRect();
            assert(d.activeElement === input && w.getComputedStyle(input).outlineStyle !== 'none', 'score focus visible');
            assert(rect.width >= 80 && rect.height >= 44, 'practical score input dimensions');
            assert(rect.left >= identity.getBoundingClientRect().right && rect.right <= bounds.right+1, 'sticky identity does not cover focused score');
            assert(rect.top >= d.querySelector('thead').getBoundingClientRect().bottom, 'sticky header does not cover focused score');
            assert(d.getElementById('score-1-10-1-input').value === '' && d.getElementById('score-1-11-1-input').value === '0.00', 'blank and real zero distinct');
            assert(d.querySelector('[role="status"][aria-live="polite"]'), 'cell live feedback');
            if (mode === 'active' || mode === 'error') {
              const cell = input.closest('[data-score-cell]');
              assert(d.querySelectorAll('[data-active-cell="true"]').length === 1 && cell.dataset.activeCell === 'true', 'one logical active cell');
              const style = w.getComputedStyle(cell);
              assert(style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0, 'active outline is visible without color');
              if (mode === 'error') {
                cell.dataset.saveState = 'error';
                cell.querySelector('[role="status"]').textContent = 'ผิดพลาด — คะแนนไม่ถูกต้องหรือเกินคะแนนเต็ม';
                input.setAttribute('aria-invalid', 'true');
                assert(d.documentElement.scrollWidth <= width + 1 && input.getBoundingClientRect().right <= bounds.right + 1, 'error feedback remains inside internal grid scroll');
              }
            }
          }
          if (mode === 'readonly') {
            assert(!d.querySelector('main input,[hx-post],script[src*="htmx"]') && d.querySelector('script[src*="gradebook.js"]'), 'read-only has selection asset but no scoring controls');
            assert(d.querySelector('main').textContent.includes('อ่านอย่างเดียว'), 'read-only mode explicit');
            assert(d.querySelector('td[data-component-id="10"]').textContent.trim()==='' && d.querySelector('td[data-component-id="11"]').textContent.trim()==='0.00','read-only blank and zero distinct');
          }
          if (mode === 'nojs') {
            assert(!d.querySelector('[data-nav-ready]'), 'application JS did not run');
            assert(d.querySelector('noscript').textContent.includes('JavaScript'), 'no-JS autosave limitation visible');
            assert(!d.querySelector('main form:not([hidden])'), 'no visible no-JS score submission');
          }
        } else {
          for (const input of d.querySelectorAll('main input:not([type="hidden"])')) {
            assert(input.labels.length === 1 && input.labels[0].htmlFor === input.id, 'setup field explicitly labelled');
            assert(input.getBoundingClientRect().right <= width+1, 'setup field contained');
          }
          if (mode === 'closed-setup') assert(!d.querySelector('main form'), 'closed setup read-only');
        }
        for (const link of d.querySelectorAll('main a')) assert(link.getBoundingClientRect().right <= width+1, 'action contained and reachable');
        results.push(`${frame.title}: PASS ${count} checks`);
      } catch (error) { results.push(`${frame.title}: FAIL after ${count}: ${error.message}`); }
    }
    document.getElementById('browser-results').textContent=results.join('\n');
    document.title=results.every(result=>result.includes(': PASS ')) ? 'PASS — Gradebook layout' : 'FAIL — Gradebook layout';
  });
})();
