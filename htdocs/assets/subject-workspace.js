(() => {
  'use strict';
  let selectedOffering = document.querySelector('[data-selected-offering]')?.dataset.selectedOffering ?? null;
  const trigger = () => selectedOffering === null ? null
    : document.querySelector(`[data-subject-trigger="${selectedOffering}"]`);
  const close = () => {
    document.getElementById('subject-context')?.replaceChildren();
    trigger()?.focus();
  };
  document.addEventListener('click', event => {
    const chosen = event.target.closest('[data-subject-trigger]');
    if (chosen) selectedOffering = chosen.dataset.subjectTrigger;
    if (event.target.closest('[data-subject-close]')) close();
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || !event.target.closest('#subject-context')) return;
    event.preventDefault();
    close();
  });
  // app.js leaves hx-confirm forms to HTMX. Preserve the native POST confirmation
  // when the HTMX script fails to load but JavaScript itself is still running.
  document.addEventListener('submit', event => {
    const form = event.target.closest('#subject-context form[data-confirm]');
    if (!form || window.htmx) return;
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
  document.addEventListener('htmx:beforeSwap', event => {
    if (event.detail.target?.id !== 'subject-workspace-content') return;
    if ([419, 422].includes(event.detail.xhr.status)) event.detail.shouldSwap = true;
  });
  document.addEventListener('htmx:afterSwap', event => {
    if (event.detail.target?.id === 'subject-context') {
      selectedOffering = document.querySelector('[data-selected-offering]')?.dataset.selectedOffering ?? selectedOffering;
      document.getElementById('subject-context-heading')?.focus();
    } else if (event.detail.target?.id === 'subject-workspace-content') {
      document.querySelector('#subject-context [data-subject-result]')?.focus();
    }
  });
  const failure = event => {
    if (!event.detail.elt?.closest('.pp5-subject-workspace') || [419, 422].includes(event.detail.xhr?.status)) return;
    const target = document.getElementById('subject-context');
    if (!target) return;
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger';
    alert.setAttribute('role', 'alert');
    alert.tabIndex = -1;
    alert.textContent = 'ไม่สามารถโหลดหรือบันทึกข้อมูลได้ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง';
    target.prepend(alert);
    alert.focus();
  };
  document.addEventListener('htmx:responseError', failure);
  document.addEventListener('htmx:sendError', failure);
})();
