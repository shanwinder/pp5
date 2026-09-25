(() => {
  'use strict';
  const selector = 'input[data-score-input]';
  const inputFor = event => event.detail?.requestConfig?.elt || event.detail?.elt;
  const isScore = input => input instanceof HTMLInputElement && input.matches(selector);
  const status = (input, state, message) => {
    const cell = input.closest('[data-score-cell]');
    cell.dataset.saveState = state;
    const feedback = cell.querySelector('[role="status"]');
    // Identical keystroke/queue states should not repeat live-region announcements.
    if (feedback.textContent !== message) feedback.textContent = message;
    input.setAttribute('aria-invalid', state === 'error' ? 'true' : 'false');
  };

  document.addEventListener('input', event => {
    if (isScore(event.target)) status(event.target, 'idle', 'ยังไม่บันทึก');
  });

  document.addEventListener('blur', event => {
    const input = event.target;
    if (!isScore(input)) return;
    // Freeze at the save boundary, including time spent in the HTMX queue.
    // Repeated focus/blur must not queue a duplicate of a soon-to-be-replaced cell.
    if (input.readOnly) { event.stopImmediatePropagation(); return; }
    input.readOnly = true;
    status(input, 'saving', 'กำลังบันทึก');
  }, true);

  document.addEventListener('keydown', event => {
    const input = event.target;
    if (!isScore(input) || event.key !== 'Enter' || event.isComposing || event.repeat) return;
    event.preventDefault();
    const column = Array.from(input.closest('table').querySelectorAll(selector))
      .filter(next => next.dataset.componentId === input.dataset.componentId);
    const next = column[column.indexOf(input) + 1];
    // The blur trigger is the only POST boundary; Tab and caret keys stay native.
    if (next) next.focus();
    else input.blur();
  });

  document.addEventListener('htmx:beforeRequest', event => {
    const input = inputFor(event);
    if (!isScore(input)) return;
    // Also cover requests initiated through HTMX's programmatic API.
    input.readOnly = true;
    status(input, 'saving', 'กำลังบันทึก');
  });

  document.addEventListener('htmx:beforeSwap', event => {
    if (!isScore(inputFor(event))) return;
    // A followed login redirect can return HTTP 200; it is never a saved fragment.
    if (event.detail.xhr.status !== 200 || event.detail.xhr.getResponseHeader('X-Gradebook-Saved') !== '1') {
      event.detail.shouldSwap = false;
      event.detail.isError = true;
    }
  });

  document.addEventListener('htmx:afterRequest', event => {
    const input = inputFor(event);
    if (!isScore(input)) return;
    input.readOnly = false;
    if (event.detail.successful) return; // Only the server's fragment says saved.
    let message = 'ผิดพลาด — บันทึกไม่สำเร็จ กรุณาลองใหม่หรือโหลดหน้าใหม่';
    const xhr = event.detail.xhr;
    if (xhr?.status === 419) message = 'ผิดพลาด — เซสชันหมดอายุ กรุณาโหลดหน้าใหม่';
    else if (xhr?.status === 422 || xhr?.status === 409) {
      const error = new DOMParser().parseFromString(xhr.responseText, 'text/html').querySelector('[data-save-state="error"]');
      if (error) message = error.textContent;
    }
    // Keep the user's exact typed value, including an invalid score, for correction.
    status(input, 'error', message);
  });
})();
