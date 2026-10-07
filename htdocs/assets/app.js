(() => {
  'use strict';
  // Optional presentation only: unmarked forms and no-JS submissions stay native.
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
      if (form.hasAttribute('hx-confirm')) return;
      if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
    });
  });

  // Keep one selected student in view. Native details still works without JavaScript.
  const studentDetails = document.querySelectorAll('[data-student-detail]');
  studentDetails.forEach(detail => {
    detail.addEventListener('toggle', () => {
      if (!detail.open) return;
      studentDetails.forEach(other => { if (other !== detail) other.open = false; });
    });
    detail.addEventListener('keydown', event => {
      if (event.key !== 'Escape' || !detail.open) return;
      event.preventDefault();
      detail.open = false;
      detail.querySelector('summary').focus();
    });
  });

  // A single help bubble escapes horizontally scrolling tables. Native title
  // and the labelled student panel remain useful without this enhancement.
  const helpTargets = document.querySelectorAll('[data-tooltip]');
  if (helpTargets.length) {
    const help = document.createElement('div');
    help.className = 'pp5-tooltip';
    help.id = 'pp5-action-tooltip';
    help.setAttribute('role', 'tooltip');
    help.hidden = true;
    document.body.append(help);
    const show = target => {
      help.textContent = target.getAttribute('data-tooltip') || '';
      help.hidden = false;
      target.setAttribute('aria-describedby', help.id);
      const rect = target.getBoundingClientRect();
      const { width, height } = help.getBoundingClientRect();
      help.style.left = `${Math.max(8, Math.min(rect.left + rect.width / 2 - width / 2, window.innerWidth - width - 8))}px`;
      help.style.top = `${rect.bottom + height + 8 > window.innerHeight ? Math.max(8, rect.top - height - 8) : rect.bottom + 8}px`;
    };
    const hide = target => {
      help.hidden = true;
      target.removeAttribute('aria-describedby');
    };
    helpTargets.forEach(target => {
      target.setAttribute('data-tooltip-ready', '');
      target.addEventListener('mouseenter', () => show(target));
      target.addEventListener('mouseleave', () => hide(target));
      target.addEventListener('focus', () => show(target));
      target.addEventListener('blur', () => hide(target));
      target.addEventListener('keydown', event => { if (event.key === 'Escape') hide(target); });
    });
  }

  // In-page disclosure, not a modal: native Tab remains available throughout.
  // Match the shell breakpoint in app.css. No enhancement means visible navigation.
  const narrow = window.matchMedia('(max-width: 63.999rem)');
  document.querySelectorAll('[data-nav-toggle]').forEach(trigger => {
    const panel = document.getElementById(trigger.getAttribute('aria-controls'));
    if (!panel?.hasAttribute('data-nav-panel') || trigger.hasAttribute('data-nav-ready')) return;
    const firstLink = () => panel.querySelector('nav a[href]') || panel;
    const setOpen = open => {
      panel.hidden = narrow.matches && !open;
      trigger.setAttribute('aria-expanded', String(!panel.hidden));
    };
    const close = () => { setOpen(false); trigger.focus(); };
    const onEscape = event => {
      if (event.key !== 'Escape' || !narrow.matches || panel.hidden) return;
      event.preventDefault();
      close();
    };
    trigger.setAttribute('data-nav-ready', '');
    panel.setAttribute('data-nav-ready', '');
    setOpen(!narrow.matches);

    trigger.addEventListener('click', () => {
      if (!narrow.matches) return;
      if (panel.hidden) { setOpen(true); firstLink().focus(); }
      else close();
    });
    panel.querySelectorAll('[data-nav-close]').forEach(button => button.addEventListener('click', close));
    panel.addEventListener('keydown', onEscape);
    trigger.addEventListener('keydown', onEscape);
    panel.addEventListener('click', event => {
      const link = event.target.closest('a[href]');
      if (!link || !narrow.matches || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      const href = link.getAttribute('href');
      const target = href.startsWith('#') ? document.getElementById(href.slice(1)) : null;
      // A same-page destination receives focus; normal links retain native navigation.
      if (target && !panel.contains(target)) {
        setOpen(false);
        if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
        target.focus();
      } else if (!href.startsWith('#')) close();
    });
    narrow.addEventListener('change', () => {
      const wasInPanel = panel.contains(document.activeElement);
      const wasTrigger = document.activeElement === trigger;
      setOpen(!narrow.matches);
      if (narrow.matches && wasInPanel) trigger.focus();
      else if (!narrow.matches && wasTrigger) firstLink().focus();
    });
  });
})();
