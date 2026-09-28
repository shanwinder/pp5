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

  // Score TDs and row/column coordinates survive inner score-cell HTMX replacement.
  const scoreCells = new Map();
  for (const cell of grid.querySelectorAll('tbody td[data-grid-score-cell]')) {
    scoreCells.set(`${cell.dataset.gridRow}:${cell.dataset.gridColumn}`, cell);
  }
  const rowCount = grid.querySelectorAll('tbody tr[data-enrollment-id]').length;
  const at = (row, column) => scoreCells.get(`${row}:${column}`);
  const coordinate = cell => ({ row: Number(cell.dataset.gridRow), column: Number(cell.dataset.gridColumn) });
  const rectangle = (anchor, extent) => ({
    rowStart: anchor.row < extent.row ? anchor.row : extent.row,
    rowEnd: anchor.row > extent.row ? anchor.row : extent.row,
    columnStart: anchor.column < extent.column ? anchor.column : extent.column,
    columnEnd: anchor.column > extent.column ? anchor.column : extent.column,
  });
  const rangeStatus = document.getElementById('gradebook-range-status');
  let selectionAnchor = null;
  let selectionExtent = null;
  let dragging = null;

  const visit = (rect, callback) => {
    if (!rect) return;
    for (let row = rect.rowStart; row <= rect.rowEnd; row++) {
      for (let column = rect.columnStart; column <= rect.columnEnd; column++) {
        const cell = at(row, column);
        if (cell) callback(cell, row, column);
      }
    }
  };
  const includes = (rect, row, column) => rect && row >= rect.rowStart && row <= rect.rowEnd
    && column >= rect.columnStart && column <= rect.columnEnd;
  const setRange = (anchor, extent, announce = false) => {
    const old = selectionAnchor && rectangle(selectionAnchor, selectionExtent);
    const next = anchor && rectangle(anchor, extent);
    visit(old, (cell, row, column) => {
      if (!includes(next, row, column)) { delete cell.dataset.gridSelected; delete cell.dataset.gridEdge; }
    });
    selectionAnchor = anchor;
    selectionExtent = extent;
    visit(next, (cell, row, column) => {
      if (cell.dataset.gridSelected !== 'true') cell.dataset.gridSelected = 'true';
      const edge = [row === next.rowStart ? 'top' : '', row === next.rowEnd ? 'bottom' : '',
        column === next.columnStart ? 'left' : '', column === next.columnEnd ? 'right' : ''].filter(Boolean).join(' ');
      if (cell.dataset.gridEdge !== edge) cell.dataset.gridEdge = edge;
    });
    if (announce && rangeStatus) rangeStatus.textContent = next
      ? `เลือก ${next.rowEnd - next.rowStart + 1} แถว × ${next.columnEnd - next.columnStart + 1} หัวข้อคะแนน`
      : 'ล้างช่วงคะแนนที่เลือกแล้ว';
  };
  const scoreTd = target => target instanceof Element ? target.closest('td[data-grid-score-cell]') : null;
  const displayedValue = cell => {
    const input = cell.querySelector(selector);
    if (input) return input.value;
    return cell.querySelector('[data-grid-value]')?.textContent ?? '';
  };
  const rangeTsv = () => {
    const rect = rectangle(selectionAnchor, selectionExtent);
    const lines = [];
    for (let row = rect.rowStart; row <= rect.rowEnd; row++) {
      const fields = [];
      for (let column = rect.columnStart; column <= rect.columnEnd; column++) {
        fields.push(displayedValue(at(row, column)));
      }
      lines.push(fields.join('\t'));
    }
    return lines.join('\n');
  };

  document.addEventListener('pointerdown', event => {
    if (event.pointerType === 'touch' || !event.isPrimary || event.button !== 0) return;
    const cell = scoreTd(event.target);
    if (!cell || !grid.contains(cell)) return;
    if (event.target.closest('input, button, a, textarea, select')) {
      setRange(null, null);
      return;
    }
    // Padding/display text starts the range; an input retains native caret selection.
    event.preventDefault();
    window.getSelection()?.removeAllRanges();
    dragging = { pointerId: event.pointerId };
    const start = coordinate(cell);
    setRange(start, start);
  });
  document.addEventListener('pointermove', event => {
    if (!dragging || event.pointerId !== dragging.pointerId) return;
    if (event.buttons === 0) { dragging = null; setRange(null, null, true); return; }
    const cell = scoreTd(document.elementFromPoint(event.clientX, event.clientY));
    if (!cell || !grid.contains(cell)) return;
    const extent = coordinate(cell);
    if (selectionExtent.row !== extent.row || selectionExtent.column !== extent.column) {
      setRange(selectionAnchor, extent);
    }
  });
  const finishDrag = (event, cancelled) => {
    if (!dragging || event.pointerId !== dragging.pointerId) return;
    dragging = null;
    if (cancelled) setRange(null, null, true);
    else setRange(selectionAnchor, selectionExtent, true);
  };
  document.addEventListener('pointerup', event => finishDrag(event, false));
  document.addEventListener('pointercancel', event => finishDrag(event, true));
  window.addEventListener('blur', () => {
    if (dragging) { dragging = null; setRange(null, null, true); }
  });
  document.addEventListener('copy', event => {
    if (!selectionAnchor || !event.clipboardData) return;
    const focused = document.activeElement;
    if (focused instanceof HTMLInputElement && focused.selectionStart !== null
      && focused.selectionEnd > focused.selectionStart) return;
    const nativeSelection = window.getSelection();
    if (nativeSelection && !nativeSelection.isCollapsed && !grid.contains(nativeSelection.anchorNode)) return;
    if (focused !== document.body && focused !== grid && !grid.contains(focused)) return;
    try {
      event.clipboardData.setData('text/plain', rangeTsv());
      event.preventDefault();
      if (rangeStatus) rangeStatus.textContent = 'คัดลอกช่วงคะแนนแล้ว';
    } catch (_) { /* Keep native copy when clipboardData is unavailable. */ }
  });

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
    if (!grid.contains(event.target)) setRange(null, null);
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
    if (event.key === 'Escape' && selectionAnchor && !composing && !event.isComposing) {
      event.preventDefault();
      setRange(null, null, true);
      return;
    }
    if (!editable(input) || composing || event.isComposing || event.repeat
      || event.altKey || event.ctrlKey || event.metaKey) return;
    if (event.shiftKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
      const cell = scoreTd(input);
      if (!cell) return;
      const anchor = selectionAnchor || coordinate(cell);
      const extent = selectionExtent || anchor;
      const row = extent.row + (event.key === 'ArrowUp' ? -1 : 1);
      if (row < 0 || row >= rowCount || !at(row, extent.column)) return;
      event.preventDefault();
      setRange(anchor, { row, column: extent.column }, true);
      return;
    }
    if (event.key === 'Enter') {
      setRange(null, null);
      event.preventDefault();
      move(input, event.shiftKey ? -1 : 1, true);
    } else if (!event.shiftKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
      setRange(null, null);
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
