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
  const scoreHeaders = [...grid.querySelectorAll('thead th[data-component-id]')];
  const at = (row, column) => scoreCells.get(`${row}:${column}`);
  const coordinate = cell => ({ row: Number(cell.dataset.gridRow), column: Number(cell.dataset.gridColumn) });
  const rectangle = (anchor, extent) => ({
    rowStart: anchor.row < extent.row ? anchor.row : extent.row,
    rowEnd: anchor.row > extent.row ? anchor.row : extent.row,
    columnStart: anchor.column < extent.column ? anchor.column : extent.column,
    columnEnd: anchor.column > extent.column ? anchor.column : extent.column,
  });
  const rangeStatus = document.getElementById('gradebook-range-status');
  const rangeActions = document.getElementById('gradebook-range-actions');
  const rangeSummary = document.getElementById('gradebook-range-summary');
  const fillValue = document.getElementById('gradebook-fill-value');
  const fillButton = document.getElementById('gradebook-fill-submit');
  const clearButton = document.getElementById('gradebook-clear-submit');
  const batchLimit = Number(grid.dataset.batchLimit);
  let batchPending = false;
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
  const currentRectangle = () => selectionAnchor && selectionExtent ? rectangle(selectionAnchor, selectionExtent) : null;
  const updateRangeControls = () => {
    if (!rangeActions) return;
    const rect = currentRectangle();
    let writable = false;
    if (!rect) rangeSummary.textContent = 'ยังไม่ได้เลือกช่วงคะแนน';
    else {
      const rows = rect.rowEnd - rect.rowStart + 1, columns = rect.columnEnd - rect.columnStart + 1;
      const count = rows * columns;
      let reason = '';
      if (count > batchLimit) reason = `เลือกได้สูงสุด ${batchLimit} ช่องต่อครั้ง`;
      else if (!dragging && !batchPending) {
        try { resolveEditableRectangle(rect); writable = true; }
        catch (error) { reason = error.message; }
      }
      rangeSummary.textContent = `เลือก ${rows} แถว × ${columns} หัวข้อคะแนน — ${count} ช่อง`
        + (reason ? ` · ${reason}` : '');
      clearButton.textContent = `ล้างคะแนน ${count} ช่องที่เลือก`;
    }
    fillButton.disabled = !writable || batchPending;
    clearButton.disabled = !writable || batchPending;
  };
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
    updateRangeControls();
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
    if (batchPending) return;
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
    if (state !== 'error') input.setAttribute('aria-describedby', input.getAttribute('aria-describedby').replace(' gradebook-batch-status', ''));
  };

  const editable = input => isScore(input) && grid.contains(input) && !input.readOnly && !input.disabled
    && input.closest('tr')?.classList.contains('pp5-current');

  // Geometry is only a locator. Recheck every live cell and stable ID before any write.
  const resolveEditableRectangle = (rect, offeringId = null) => {
    const enrollmentIds = [], componentIds = [], targets = new Map();
    const invalid = () => { throw new Error('ตารางคะแนนเปลี่ยนแปลง กรุณาโหลดหน้าใหม่'); };
    for (let row = rect.rowStart; row <= rect.rowEnd; row++) {
      for (let column = rect.columnStart; column <= rect.columnEnd; column++) {
        const td = at(row, column), target = td?.querySelector(selector);
        if (!td || !td.isConnected || !grid.contains(td) || !target || !editable(target)
          || td.dataset.gridEditable !== 'true') {
          throw new Error('ช่วงนี้มีข้อมูลประวัติหรือช่องอ่านอย่างเดียว จึงแก้ไขพร้อมกันไม่ได้');
        }
        const enrollment = Number(td.dataset.enrollmentId), component = Number(td.dataset.componentId);
        if (!Number.isSafeInteger(enrollment) || enrollment <= 0 || !Number.isSafeInteger(component) || component <= 0
          || Number(td.dataset.gridRow) !== row || Number(td.dataset.gridColumn) !== column
          || td.closest('tr')?.dataset.enrollmentId !== td.dataset.enrollmentId
          || target.dataset.enrollmentId !== td.dataset.enrollmentId || target.dataset.componentId !== td.dataset.componentId
          || !Number.isSafeInteger(Number(target.dataset.offeringId)) || Number(target.dataset.offeringId) <= 0
          || target.dataset.offeringId !== grid.dataset.offeringId
          || (offeringId !== null && Number(target.dataset.offeringId) !== offeringId)) invalid();
        if (offeringId === null) offeringId = Number(target.dataset.offeringId);
        const header = scoreHeaders[column];
        if (!header || !header.isConnected || header.dataset.componentId !== td.dataset.componentId) invalid();
        const rowIndex = row - rect.rowStart, columnIndex = column - rect.columnStart;
        if (columnIndex === 0) enrollmentIds.push(enrollment);
        if (rowIndex === 0) componentIds.push(component);
        if (enrollmentIds[rowIndex] !== enrollment || componentIds[columnIndex] !== component
          || targets.has(`${enrollment}:${component}`)) invalid();
        targets.set(`${enrollment}:${component}`, target);
      }
    }
    if (new Set(enrollmentIds).size !== enrollmentIds.length || new Set(componentIds).size !== componentIds.length) invalid();
    return { enrollmentIds, componentIds, targets, offeringId };
  };

  const batchStatus = document.getElementById('gradebook-batch-status');
  const batchUrl = grid.dataset.batchUrl;
  // A confirmed batch already saved these exact input values. A later blur must not post them again.
  const batchValues = new WeakMap();
  const batchMessage = (message, state) => {
    if (batchStatus) { batchStatus.textContent = message; batchStatus.dataset.batchState = state; }
  };
  const parseTsv = text => {
    if (text.length > 262144) throw new Error('ตารางคะแนนมีขนาดใหญ่เกินไป');
    let normalized = text.replace(/\r\n?/g, '\n');
    // Remove exactly one clipboard row terminator. Tabs and additional empty rows are meaningful.
    if (normalized.endsWith('\n')) normalized = normalized.slice(0, -1);
    const rows = normalized.split('\n').map(row => row.split('\t'));
    const width = rows[0].length;
    if (rows.some(row => row.length !== width)) throw new Error('แต่ละแถวต้องมีจำนวนช่องเท่ากัน กรุณาคัดลอกตารางสี่เหลี่ยมอีกครั้ง');
    if (rows.length * width > batchLimit) throw new Error(`วางได้สูงสุด ${batchLimit} ช่องต่อครั้ง`);
    return rows;
  };
  const responseUpdates = (data, matrix, targets, offeringId) => {
    const invalid = () => { throw new Error('บันทึกแล้ว แต่แสดงผลล่าสุดไม่สำเร็จ กรุณาโหลดหน้าใหม่เพื่อตรวจสอบคะแนน'); };
    const decimal = value => typeof value === 'string' && /^[0-9]+\.[0-9]{2}$/.test(value);
    const integer = value => Number.isSafeInteger(value) && value >= 0;
    if (!data || data.committed !== true || data.offering_id !== offeringId
      || data.row_count !== matrix.enrollment_ids.length || data.column_count !== matrix.component_ids.length
      || data.targeted_count !== targets.size || !integer(data.changed_count) || data.changed_count > targets.size
      || !Array.isArray(data.cells) || data.cells.length !== targets.size
      || !Array.isArray(data.rows) || data.rows.length !== matrix.enrollment_ids.length) invalid();
    const cells = [], rows = [], seen = new Set(), seenRows = new Set();
    for (const cell of data.cells) {
      if (!cell || !integer(cell.enrollment_id) || !integer(cell.component_id)) invalid();
      const key = `${cell.enrollment_id}:${cell.component_id}`;
      const input = targets.get(key);
      if (!input || seen.has(key) || !input.isConnected || !grid.contains(input)
        || Number(input.dataset.enrollmentId) !== cell.enrollment_id || Number(input.dataset.componentId) !== cell.component_id
        || (cell.score !== null && !decimal(cell.score)) || typeof cell.changed !== 'boolean') invalid();
      seen.add(key); cells.push({ input, score: cell.score ?? '' });
    }
    for (const row of data.rows) {
      if (!row || !matrix.enrollment_ids.includes(row.enrollment_id) || seenRows.has(row.enrollment_id)
        || !decimal(row.entered_score_total) || !decimal(row.configured_max_total)
        || !integer(row.entered_component_count) || !integer(row.active_component_count)
        || row.entered_component_count > row.active_component_count || typeof row.complete !== 'boolean') invalid();
      seenRows.add(row.enrollment_id);
      const values = { total: row.entered_score_total, max: row.configured_max_total,
        count: `${row.entered_component_count} / ${row.active_component_count}`, complete: row.complete ? 'ครบ' : 'ยังไม่ครบ' };
      for (const [suffix, value] of Object.entries(values)) {
        const node = document.getElementById(`row-${offeringId}-${row.enrollment_id}-${suffix}`);
        if (!node || !grid.contains(node)) invalid();
        rows.push({ node, value });
      }
    }
    return { cells, rows };
  };
  const batchReady = (source = 'paste') => {
    if (batchPending) { batchMessage('กำลังบันทึกตารางคะแนน กรุณารอสักครู่', 'saving'); return false; }
    if ([...phases.values()].includes('SAVING')) {
      batchMessage(source === 'paste' ? 'รอการบันทึกช่องปัจจุบันให้เสร็จก่อน แล้ววางอีกครั้ง'
        : 'รอการบันทึกช่องปัจจุบันให้เสร็จก่อน แล้วลองอีกครั้ง', 'error'); return false;
    }
    return true;
  };
  // Paste and explicit range actions share ownership, transport, validation and DOM reconciliation.
  const submitBatch = async ({ matrix, targets, offeringId, source = 'paste' }) => {
    batchPending = true;
    grid.setAttribute('aria-busy', 'true');
    updateRangeControls();
    const frozen = [...grid.querySelectorAll(selector)].map(node => ({ node, readOnly: node.readOnly }));
    for (const { node } of frozen) node.readOnly = true;
    batchMessage(`กำลังบันทึกคะแนน ${targets.size} ช่อง`, 'saving');
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 30000);
    let committed = false;
    try {
      const response = await fetch(batchUrl, { method: 'POST', credentials: 'same-origin', signal: controller.signal,
        headers: { 'Accept': 'application/json' },
        body: new URLSearchParams({ _token: document.getElementById('gradebook-csrf').value, batch: JSON.stringify(matrix) }) });
      committed = response.headers.get('X-Gradebook-Batch-Saved') === '1';
      const json = response.headers.get('Content-Type')?.split(';')[0].trim() === 'application/json';
      const data = json ? await response.json() : null;
      if (data?.committed === true) committed = true;
      if (response.status !== 200 || response.headers.get('X-Gradebook-Batch-Saved') !== '1' || !json) {
        if (data && typeof data.message === 'string') {
          const location = data.location;
          if (location && Number.isSafeInteger(location.enrollment_id) && Number.isSafeInteger(location.component_id)) {
            const offending = targets.get(`${location.enrollment_id}:${location.component_id}`);
            if (offending) {
              offending.setAttribute('aria-invalid', 'true');
              const described = offending.getAttribute('aria-describedby');
              if (!described.includes('gradebook-batch-status')) offending.setAttribute('aria-describedby', `${described} gradebook-batch-status`);
            }
          }
          throw new Error(data.message);
        }
        throw new Error(response.status === 419 ? `เซสชันหมดอายุ กรุณาโหลดหน้าใหม่ก่อน${source === 'paste' ? 'วางคะแนน' : source === 'clear' ? 'ล้างคะแนนช่วงที่เลือก' : 'ใส่คะแนนช่วงที่เลือก'}`
          : 'ยืนยันผลการบันทึกไม่ได้ กรุณาโหลดหน้าใหม่เพื่อตรวจสอบคะแนนและสิทธิ์ก่อนลองอีกครั้ง');
      }
      // Validate every identity, field and DOM destination before changing any visible value.
      const updates = responseUpdates(data, matrix, targets, offeringId);
      for (const { input: target, score } of updates.cells) {
        target.value = score; target.defaultValue = score; batchValues.set(target, score);
        status(target, 'ACTIVE', 'saved', ''); // One batch announcement instead of one per cell.
      }
      for (const { node, value } of updates.rows) node.textContent = value;
      const action = source === 'clear' ? 'ล้างคะแนน' : source === 'fill' ? 'ใส่คะแนน' : 'บันทึกคะแนน';
      batchMessage(`${action} ${data.targeted_count} ช่องแล้ว (เปลี่ยนแปลง ${data.changed_count} ช่อง)`, 'saved');
    } catch (error) {
      // Suppress an unchanged stale blur after any unconfirmed command; explicit typing still saves normally.
      for (const target of targets.values()) batchValues.set(target, target.value);
      // No speculative cell values were displayed. Transport failures have unknown commit status.
      if (committed) {
        batchMessage('บันทึกคะแนนแล้ว แต่แสดงผลล่าสุดไม่สำเร็จ กรุณาโหลดหน้าใหม่เพื่อตรวจสอบคะแนนก่อนแก้ไขต่อ', 'error');
      } else batchMessage(error.name === 'AbortError' || error instanceof TypeError || error instanceof SyntaxError
        ? 'การเชื่อมต่อขัดข้อง ผลการบันทึกยังไม่แน่นอน กรุณาโหลดหน้าใหม่เพื่อตรวจสอบคะแนนก่อนลองอีกครั้ง'
        : error.message, 'error');
    } finally {
      window.clearTimeout(timeout);
      for (const { node, readOnly } of frozen) if (node.isConnected) node.readOnly = readOnly;
      batchPending = false;
      grid.removeAttribute('aria-busy');
      updateRangeControls();
    }
  };

  document.addEventListener('paste', event => {
    const input = event.target;
    if (!batchUrl || !isScore(input) || !grid.contains(input) || document.activeElement !== input
      || composing || !event.clipboardData) return;
    // Once this is a Gradebook paste, never allow native insertion to escape an atomic failure.
    event.preventDefault();
    if (!batchReady()) return;
    if (!editable(input)) { batchMessage('ช่องนี้ไม่สามารถวางคะแนนได้', 'error'); return; }
    const text = event.clipboardData.getData('text/plain');
    // A clipboard without plain text is unsupported; an explicitly empty field is a 1×1 clear.
    if (!Array.from(event.clipboardData.types).includes('text/plain')) return;
    try {
      const values = parseTsv(text), start = coordinate(scoreTd(input));
      const end = { row: start.row + values.length - 1, column: start.column + values[0].length - 1 };
      const resolved = resolveEditableRectangle(rectangle(start, end), Number(input.dataset.offeringId));
      const matrix = { enrollment_ids: resolved.enrollmentIds, component_ids: resolved.componentIds, values };
      setRange(start, end);
      submitBatch({ matrix, targets: resolved.targets, offeringId: resolved.offeringId });
    } catch (error) {
      batchMessage(error.message === 'ช่วงนี้มีข้อมูลประวัติหรือช่องอ่านอย่างเดียว จึงแก้ไขพร้อมกันไม่ได้'
        ? 'วางไม่ได้: ตารางเกินช่องคะแนนที่แก้ไขได้ หรือมีแถวประวัติ กรุณาเลือกช่วงใหม่' : error.message, 'error');
    }
  });

  let fillComposing = false;
  if (rangeActions) {
    rangeActions.hidden = false;
    fillValue.addEventListener('compositionstart', () => { fillComposing = true; });
    fillValue.addEventListener('compositionend', () => { fillComposing = false; });
    fillValue.addEventListener('keydown', event => {
      if (event.key === 'Enter' && (fillComposing || event.isComposing)) event.preventDefault();
    });
    const runRangeAction = (scalar, source) => {
      if (!batchReady(source)) return;
      const rect = currentRectangle();
      if (!rect) { batchMessage('กรุณาเลือกช่วงคะแนนก่อน', 'error'); return; }
      const count = (rect.rowEnd - rect.rowStart + 1) * (rect.columnEnd - rect.columnStart + 1);
      if (count > batchLimit) { batchMessage(`เลือกได้สูงสุด ${batchLimit} ช่องต่อครั้ง`, 'error'); return; }
      try {
        const resolved = resolveEditableRectangle(rect);
        const values = resolved.enrollmentIds.map(() => resolved.componentIds.map(() => scalar));
        const matrix = { enrollment_ids: resolved.enrollmentIds, component_ids: resolved.componentIds, values };
        submitBatch({ matrix, targets: resolved.targets, offeringId: resolved.offeringId, source });
      } catch (error) { batchMessage(error.message, 'error'); updateRangeControls(); }
    };
    rangeActions.addEventListener('submit', event => {
      event.preventDefault();
      if (fillComposing || event.isComposing) return;
      if (fillValue.value === '') {
        batchMessage('กรุณากรอกคะแนน หรือใช้ปุ่ม “ล้างคะแนนในช่วงที่เลือก”', 'error'); return;
      }
      runRangeAction(fillValue.value, 'fill');
    });
    clearButton.addEventListener('click', () => runRangeAction('', 'clear'));
    updateRangeControls();
  }

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
    if (!grid.contains(event.target) && !rangeActions?.contains(event.target)) setRange(null, null);
    if (editable(event.target)) setActive(keyOf(event.target));
    else if (isScore(event.target)) setActive(keyOf(event.target)); // A frozen in-flight cell can still receive focus.
    else setActive(null);
  });

  document.addEventListener('compositionstart', event => { if (isScore(event.target)) composing = true; });
  document.addEventListener('compositionend', event => { if (isScore(event.target)) composing = false; });

  document.addEventListener('input', event => {
    if (editable(event.target)) {
      batchValues.delete(event.target);
      status(event.target, 'EDITING', 'idle', 'ยังไม่บันทึก');
    }
  });

  document.addEventListener('blur', event => {
    const input = event.target;
    if (!isScore(input)) return;
    composing = false;
    // Moving an unchanged cell into range controls is navigation, not a new score edit.
    // Dirty or failed cells still use Task 6 blur save and block the batch until it settles.
    if (rangeActions?.contains(event.relatedTarget) && input.value === input.defaultValue
      && phases.get(keyOf(input)) !== 'ERROR') { event.stopImmediatePropagation(); return; }
    // Freeze at the blur boundary, including time in HTMX's queue. Repeated blur cannot queue a duplicate.
    if (batchPending || input.readOnly || (batchValues.has(input) && batchValues.get(input) === input.value)) { event.stopImmediatePropagation(); return; }
    input.readOnly = true;
    status(input, 'SAVING', 'saving', 'กำลังบันทึก');
  }, true);

  document.addEventListener('keydown', event => {
    const input = event.target;
    if (event.key === 'Escape' && selectionAnchor && !composing && !fillComposing && !event.isComposing) {
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
    if (batchPending) { event.preventDefault(); return; }
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
