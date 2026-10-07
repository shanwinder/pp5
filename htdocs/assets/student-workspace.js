(() => {
  'use strict';
  // The server owns student state; this file only manages the local panel and focus.
  let studentTrigger = null;
  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-student-trigger]');
    if (trigger) studentTrigger = trigger;
    if (!event.target.closest('[data-student-close]')) return;
    document.getElementById('student-context')?.replaceChildren();
    if (studentTrigger?.isConnected) studentTrigger.focus();
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || !event.target.closest('#student-context')) return;
    event.preventDefault();
    document.getElementById('student-context')?.replaceChildren();
    if (studentTrigger?.isConnected) studentTrigger.focus();
  });
  document.addEventListener('htmx:beforeSwap', event => {
    if (event.detail.target?.id === 'student-workspace-content'
        && (event.detail.xhr.status === 419 || event.detail.xhr.status === 422)) {
      event.detail.shouldSwap = true;
      event.detail.isError = false;
    }
  });
  document.addEventListener('htmx:afterSwap', event => {
    if (event.detail.target?.id === 'student-context') {
      document.getElementById('student-context-heading')?.focus();
    } else if (event.detail.target?.id === 'student-workspace-content') {
      document.querySelector('#student-workspace-content [data-workflow-focus]')?.focus();
    }
  });
  document.addEventListener('htmx:responseError', event => {
    if (!event.detail.elt?.closest('.pp5-student-workspace')) return;
    const target = document.getElementById('student-context');
    if (!target) return;
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger';
    alert.setAttribute('role', 'alert');
    alert.tabIndex = -1;
    alert.textContent = 'ไม่สามารถโหลดหรือบันทึกข้อมูลได้ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง';
    target.replaceChildren(alert);
    alert.focus();
  });
})();
