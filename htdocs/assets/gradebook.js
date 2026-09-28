(() => {
  'use strict';
  const grid = document.querySelector('.pp5-gradebook');
  if (!grid) return;

  const selector = 'input[data-score-input]';
  const isScore = input => input instanceof HTMLInputElement && input.matches(selector);
  const keyOf = input => `${input.dataset.offeringId}-${input.dataset.componentId}-${input.dataset.enrollmentId}`;
  const inputFor = event => event.detail?.requestConfig?.elt || event.detail?.elt;
  const inputForKey = key => document.getElementById(`score-${key}-input`);
  const cellForKey = key => document.getElementById(`score-${key}`);
  const columns = new Map();
  const positions = new Map();
  for (const input of grid.querySelectorAll(`tbody tr.pp5-current ${selector}`)) {
    const key = keyOf(input);
    const column = input.dataset.componentId;
    if (!columns.has(column)) columns.set(column, []);
    const items = columns.get(column);
    positions.set(key, { column, index: items.length });
    items.push(key);
  }
  if (positions.size === 0) return; // Read-only pages never intercept score keys.

  const phases = new Map();
  let activeKey = null;
  let focusRevision = 0;
  let composing = false;
  let pendingRestore = null;

  const setActive = key => {
    if (activeKey !== null && activeKey !== key) {
      const previous = cellForKey(activeKey);
      if (previous) delete previous.dataset.activeCell;
    }
    activeKey = key;
    if (key !== null) {
      const cell = cellForKey(key);
      if (cell) cell.dataset.activeCell = 'true';
      const phase = phases.get(key);
      if (phase !== 'EDITING' && phase !== 'SAVING' && phase !== 'ERROR') phases.set(key, 'ACTIVE');
    }
  };

  const status = (input, phase, state, message) => {
    const key = keyOf(input);
    phases.set(key, phase);
    const cell = input.closest('[data-score-cell]');
    if (!cell) return;
    cell.dataset.saveState = state;
    const feedback = cell.querySelector('[role="status"]');
    // Identical keystroke/queue states should not repeat live-region announcements.
    if (feedback && feedback.textContent !== message) feedback.textContent = message;
    input.setAttribute('aria-invalid', state === 'error' ? 'true' : 'false');
  };

  const editable = input => isScore(input) && grid.contains(input) && !input.readOnly && !input.disabled
    && input.closest('tr')?.classList.contains('pp5-current');

  const neighbor = (input, direction) => {
    const position = positions.get(keyOf(input));
    if (!position) return null;
    const items = columns.get(position.column);
    for (let index = position.index + direction; index >= 0 && index < items.length; index += direction) {
      const candidate = inputForKey(items[index]);
      if (editable(candidate)) return candidate;
    }
    return null;
  };

  const move = (input, direction, blurAtBoundary) => {
    const target = neighbor(input, direction);
    if (target) {
      target.focus({ preventScroll: true });
      target.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'instant' });
    } else if (blurAtBoundary) input.blur();
  };

  document.addEventListener('focusin', event => {
    ++focusRevision;
    if (editable(event.target)) setActive(keyOf(event.target));
    else if (isScore(event.target)) setActive(keyOf(event.target)); // A frozen in-flight cell can still receive focus.
    else setActive(null);
  });

  document.addEventListener('compositionstart', event => { if (isScore(event.target)) composing = true; });
  document.addEventListener('compositionend', event => { if (isScore(event.target)) composing = false; });

  document.addEventListener('input', event => {
    if (editable(event.target)) status(event.target, 'EDITING', 'idle', 'ยังไม่บันทึก');
  });

  document.addEventListener('blur', event => {
    const input = event.target;
    if (!isScore(input)) return;
    composing = false;
    // Freeze at the blur boundary, including time in HTMX's queue. Repeated blur cannot queue a duplicate.
    if (input.readOnly) { event.stopImmediatePropagation(); return; }
    input.readOnly = true;
    status(input, 'SAVING', 'saving', 'กำลังบันทึก');
  }, true);

  document.addEventListener('keydown', event => {
    const input = event.target;
    if (!editable(input) || composing || event.isComposing || event.repeat
      || event.altKey || event.ctrlKey || event.metaKey) return;
    if (event.key === 'Enter') {
      event.preventDefault();
      move(input, event.shiftKey ? -1 : 1, true);
    } else if (!event.shiftKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
      event.preventDefault();
      move(input, event.key === 'ArrowUp' ? -1 : 1, false);
    }
    // Tab/Shift+Tab and Left/Right remain native; text caret and page exit are predictable.
  });

  document.addEventListener('htmx:beforeRequest', event => {
    const input = inputFor(event);
    if (!isScore(input)) return;
    input.readOnly = true;
    status(input, 'SAVING', 'saving', 'กำลังบันทึก');
  });

  const confirmed = xhr => xhr?.status === 200 && xhr.getResponseHeader('X-Gradebook-Saved') === '1';
  document.addEventListener('htmx:beforeSwap', event => {
    const input = inputFor(event);
    if (!isScore(input)) return;
    if (!confirmed(event.detail.xhr)) {
      event.detail.shouldSwap = false;
      event.detail.isError = true;
      return;
    }
    const key = keyOf(input);
    if (document.activeElement === input && activeKey === key) {
      pendingRestore = { key, revision: focusRevision, oldInput: input };
    }
  });

  document.addEventListener('htmx:afterSwap', event => {
    const input = inputFor(event);
    if (!isScore(input)) return;
    if (activeKey === keyOf(input)) {
      const cell = cellForKey(activeKey);
      if (cell) cell.dataset.activeCell = 'true';
    }
  });

  document.addEventListener('htmx:afterSettle', event => {
    const input = inputFor(event);
    if (!isScore(input) || !pendingRestore || pendingRestore.oldInput !== input || input.isConnected) return;
    const { key, revision } = pendingRestore;
    pendingRestore = null;
    if (activeKey !== key || focusRevision !== revision) return;
    const replacement = inputForKey(key);
    if (editable(replacement) && (document.activeElement === document.body || document.activeElement === null)) {
      replacement.focus({ preventScroll: true });
    }
  });

  document.addEventListener('htmx:afterRequest', event => {
    const input = inputFor(event);
    if (!isScore(input)) return;
    input.readOnly = false;
    if (confirmed(event.detail.xhr) && event.detail.successful) {
      phases.set(keyOf(input), 'ACTIVE'); // The server's replacement fragment displays the saved state.
      return;
    }
    let message = 'ผิดพลาด — บันทึกไม่สำเร็จ กรุณาลองใหม่หรือโหลดหน้าใหม่';
    const xhr = event.detail.xhr;
    if (xhr?.status === 419) message = 'ผิดพลาด — เซสชันหมดอายุ กรุณาโหลดหน้าใหม่';
    else if (xhr?.status === 422 || xhr?.status === 409) {
      const error = new DOMParser().parseFromString(xhr.responseText, 'text/html').querySelector('[data-save-state="error"]');
      if (error) message = error.textContent;
    }
    // Keep the exact typed value for correction or retry, including an invalid score.
    status(input, 'ERROR', 'error', message);
  });
})();
