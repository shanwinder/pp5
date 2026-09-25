(() => {
  'use strict';
  // Optional presentation only: unmarked forms and no-JS submissions stay native.
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
      if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
    });
  });

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
