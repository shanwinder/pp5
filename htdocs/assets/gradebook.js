(() => {
  'use strict';
  const host = document.getElementById('gradebook-tabulator');
  const source = document.getElementById('gradebook-grid-data');
  const fallback = document.querySelector('.pp5-gradebook');
  if (!host || !source || !fallback) return;
  const status = document.getElementById('gradebook-batch-status');
  const rangeStatus = document.getElementById('gradebook-range-status');
  const actions = document.getElementById('gradebook-range-actions');
  const fillInput = document.getElementById('gradebook-fill-value');
  const fillButton = document.getElementById('gradebook-fill-submit');
  const clearButton = document.getElementById('gradebook-clear-submit');
  let config;
  try { config = JSON.parse(source.textContent); }
  catch (_) { return; }
  if (typeof Tabulator !== 'function' || !Number.isSafeInteger(config.offeringId)
    || !Array.isArray(config.components) || !Array.isArray(config.rows)) return;

  const fields = config.components.map(item => `score_${item.id}`);
  const componentByField = new Map(config.components.map(item => [`score_${item.id}`, item]));
  const rowData = config.rows.map(row => {
    const mapped = { id: row.enrollmentId, enrollmentId: row.enrollmentId,
      identity: `${row.studentCode}\n${row.name}`, rowType: row.rowType === 'CURRENT' ? 'รายชื่อปัจจุบัน' : 'ประวัติ — อ่านอย่างเดียว',
      historical: row.rowType !== 'CURRENT', total: row.total, max: row.max,
      count: `${row.entered} / ${row.componentCount}`, complete: row.complete ? 'ครบ' : 'ยังไม่ครบ' };
    for (const component of config.components) mapped[`score_${component.id}`] = row.scores[component.id] ?? '';
    return mapped;
  });
  const rowById = new Map(rowData.map(row => [row.id, row]));
  const authoritative = new Map(rowData.flatMap(row => config.components.map(component =>
    [`${row.enrollmentId}:${component.id}`, row[`score_${component.id}`]])));
  const decimal = value => typeof value === 'string' && /^[0-9]+\.[0-9]{2}$/.test(value);
  const integer = value => Number.isSafeInteger(value) && value >= 0;
  const text = value => String(value ?? '');
  const escapeHtml = value => text(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
  const message = (value, state = 'idle') => {
    if (status) { status.textContent = value; status.dataset.batchState = state; }
  };
  // Keep the initial hint's space while short save messages and an empty success
  // message replace it. Otherwise document scroll anchoring moves the page.
  const statusHint = status?.textContent ?? '';
  const reserveStatusHeight = () => {
    if (!status) return;
    const probe = status.cloneNode(false);
    probe.removeAttribute('id');
    probe.textContent = statusHint;
    probe.style.position = 'absolute';
    probe.style.visibility = 'hidden';
    probe.style.width = `${status.getBoundingClientRect().width}px`;
    probe.style.minHeight = '0';
    status.after(probe);
    status.style.minHeight = `${probe.getBoundingClientRect().height}px`;
    probe.remove();
  };
  reserveStatusHeight();
  window.addEventListener('resize', reserveStatusHeight);
  const markError = cell => {
    const element = cell.getElement();
    if (!element) return;
    element.dataset.pp5Error = 'true';
    element.setAttribute('aria-invalid', 'true');
    if (status) element.setAttribute('aria-describedby', status.id);
  };
  const clearError = cell => {
    const element = cell.getElement();
    element?.removeAttribute('data-pp5-error');
    element?.removeAttribute('aria-invalid');
    if (element?.getAttribute('aria-describedby') === status?.id) element.removeAttribute('aria-describedby');
  };
  const state = { active: null, anchor: null, extent: null, editing: null, editIntent: null,
    composing: false, fillComposing: false,
    pendingCharacter: null, pendingSingles: new Map(), pendingBatch: false, uncertain: false,
    permissionLost: false, applying: false, revision: 0, rowApplied: new Map(), errors: new Map(),
    singleQueue: Promise.resolve(), focusOwner: 'external' };
  let table;
  const token = () => document.getElementById('gradebook-csrf')?.value;
  const isScoreCell = cell => Boolean(cell && componentByField.has(cell.getField()));
  const isNavigableScoreCell = cell => Boolean(isScoreCell(cell) && cell.getRow().getData());
  const isWritableScoreCell = cell => Boolean(isNavigableScoreCell(cell) && config.canScore
    && !state.permissionLost && !state.uncertain && !cell.getRow().getData().historical);
  const cellKey = cell => `${cell.getRow().getData().enrollmentId}:${componentByField.get(cell.getField())?.id}`;
  const rows = () => table.getRows('active');
  const cellAt = (y, x) => rows()[y]?.getCell(fields[x]) ?? null;
  const indices = cell => ({ y: rows().findIndex(row => row.getData().enrollmentId === cell.getRow().getData().enrollmentId),
    x: fields.indexOf(cell.getField()) });
  const cellFromElement = element => {
    const cellElement = element instanceof Element ? element.closest('.tabulator-cell') : null;
    const rowElement = cellElement?.closest('.tabulator-row');
    const row = rowElement && table.getRow(rowElement);
    return row?.getCell(cellElement.getAttribute('tabulator-field')) || null;
  };
  const liveCell = cell => {
    if (!cell || !table) return null;
    return table.getRow(cell.getRow().getData().enrollmentId)?.getCell(cell.getField()) || null;
  };
  const visible = cell => {
    const element = cell?.getElement(), holder = host.querySelector('.tabulator-tableholder');
    if (!element?.isConnected || !holder) return;
    const rect = element.getBoundingClientRect(), bounds = holder.getBoundingClientRect();
    let frozen = bounds.left;
    for (const node of element.closest('.tabulator-row').querySelectorAll('.tabulator-frozen-left'))
      frozen = Math.max(frozen, node.getBoundingClientRect().right);
    const left = Math.max(bounds.left, frozen);
    if (rect.left < left - 0.5) holder.scrollLeft -= left - rect.left;
    else if (rect.right > bounds.right + 0.5) holder.scrollLeft += rect.right - bounds.right;
    if (rect.top < bounds.top - 0.5) holder.scrollTop -= bounds.top - rect.top;
    else if (rect.bottom > bounds.bottom + 0.5) holder.scrollTop += rect.bottom - bounds.bottom;
  };
  const syncTabStops = activeElement => {
    const liveActive = activeElement?.isConnected ? activeElement : null;
    const holder = host.querySelector('.tabulator-tableholder');
    if (holder) holder.tabIndex = -1;
    for (const element of host.querySelectorAll('.tabulator-cell[tabindex="0"]'))
      if (element !== liveActive) element.tabIndex = -1;
    if (liveActive && componentByField.has(liveActive.getAttribute('tabulator-field'))) liveActive.tabIndex = 0;
    host.tabIndex = liveActive ? -1 : 0;
  };
  const activate = (cell, focus = true, reveal = true) => {
    if (!isNavigableScoreCell(cell)) return;
    const current = liveCell(cell) || cell;
    const element = current.getElement();
    const previous = state.active?.getElement();
    if (previous !== element) previous?.removeAttribute('data-pp5-active');
    state.active = current;
    syncTabStops(element?.isConnected ? element : null);
    if (element?.isConnected) {
      if (element.dataset.pp5Active !== 'true') element.dataset.pp5Active = 'true';
      if (focus && !state.editing && !state.composing) {
        if (document.activeElement !== element) element.focus({ preventScroll: true });
        if (reveal) visible(current);
      }
    }
  };
  let paintedSelection = new Set();
  const paintSelection = () => {
    const anchor = state.anchor, extent = state.extent;
    const a = anchor && indices(anchor), b = extent && indices(extent);
    const top = a && b ? Math.min(a.y, b.y) : -1, bottom = a && b ? Math.max(a.y, b.y) : -1;
    const left = a && b ? Math.min(a.x, b.x) : -1, right = a && b ? Math.max(a.x, b.x) : -1;
    const next = new Set();
    for (let y = top; y <= bottom; y++) for (let x = left; x <= right; x++) {
      const element = cellAt(y, x)?.getElement();
      if (element?.isConnected) next.add(element);
    }
    for (const element of paintedSelection)
      if (!next.has(element)) element.classList.remove('tabulator-range-selected');
    for (const element of next)
      if (!paintedSelection.has(element)) element.classList.add('tabulator-range-selected');
    paintedSelection = next;
  };
  const selected = () => {
    if (!isNavigableScoreCell(state.anchor) || !isNavigableScoreCell(state.extent)) return null;
    const a = indices(state.anchor), b = indices(state.extent);
    if (a.x < 0 || b.x < 0 || a.y < 0 || b.y < 0) return null;
    const matrix = [];
    for (let y = Math.min(a.y, b.y); y <= Math.max(a.y, b.y); y++) {
      const line = [];
      for (let x = Math.min(a.x, b.x); x <= Math.max(a.x, b.x); x++) line.push(cellAt(y, x));
      matrix.push(line);
    }
    return matrix.every(line => line.every(isScoreCell)) ? { matrix } : null;
  };
  const select = (anchor, extent = anchor, focus = true) => {
    if (!isNavigableScoreCell(anchor) || !isNavigableScoreCell(extent)) return;
    state.anchor = anchor;
    state.extent = extent;
    paintSelection();
    activate(extent, focus);
    updateControls();
  };
  const selectedPlan = () => {
    const choice = selected();
    if (!choice) throw new Error('กรุณาเลือกช่วงคะแนนก่อน');
    const { matrix } = choice;
    const height = matrix.length, width = matrix[0].length;
    if (state.active && !matrix.some(line => line.includes(state.active)))
      throw new Error('กรุณาเลือกช่วงคะแนนใหม่ก่อนแก้ไข');
    if (height * width > config.batchLimit) throw new Error(`เลือกได้สูงสุด ${config.batchLimit} ช่องต่อครั้ง`);
    const enrollmentIds = [], componentIds = [], targets = new Map();
    for (let y = 0; y < height; y++) {
      if (matrix[y].length !== width) throw new Error('ช่วงคะแนนไม่เป็นรูปสี่เหลี่ยม');
      for (let x = 0; x < width; x++) {
        const cell = matrix[y][x];
        if (!isWritableScoreCell(cell)) throw new Error('ช่วงนี้มีแถวประวัติหรือช่องอ่านอย่างเดียว จึงแก้ไขพร้อมกันไม่ได้');
        const rowId = cell.getRow().getData().enrollmentId, componentId = componentByField.get(cell.getField())?.id;
        if (!integer(rowId) || !integer(componentId) || rowId === 0 || componentId === 0) throw new Error('ตารางคะแนนเปลี่ยนแปลง กรุณาโหลดหน้าใหม่');
        if (x === 0) enrollmentIds.push(rowId);
        if (y === 0) componentIds.push(componentId);
        if (enrollmentIds[y] !== rowId || componentIds[x] !== componentId || targets.has(`${rowId}:${componentId}`))
          throw new Error('ตารางคะแนนเปลี่ยนแปลง กรุณาโหลดหน้าใหม่');
        targets.set(`${rowId}:${componentId}`, cell);
      }
    }
    if (new Set(enrollmentIds).size !== height || new Set(componentIds).size !== width)
      throw new Error('ตารางคะแนนเปลี่ยนแปลง กรุณาโหลดหน้าใหม่');
    return { enrollmentIds, componentIds, targets, height, width };
  };
  const updateControls = () => {
    if (!actions) return;
    const choice = selected();
    let okay = false, reason = '';
    if (choice) {
      try { selectedPlan(); okay = true; } catch (error) { reason = error.message; }
    }
    const count = choice ? choice.matrix.length * choice.matrix[0].length : 0;
    document.getElementById('gradebook-range-summary').textContent = choice
      ? `เลือก ${choice.matrix.length} แถว × ${choice.matrix[0].length} หัวข้อคะแนน — ${count} ช่อง${reason ? ` · ${reason}` : ''}`
      : 'ยังไม่ได้เลือกช่วงคะแนน';
    clearButton.textContent = `ล้างคะแนน ${count} ช่องที่เลือก`;
    fillButton.disabled = clearButton.disabled = !okay || state.pendingBatch || state.uncertain || state.permissionLost;
    if (rangeStatus) {
      const announcement = choice
        ? `เลือก ${choice.matrix.length} แถว × ${choice.matrix[0].length} หัวข้อคะแนน`
        : (rangeStatus.textContent ? 'ยกเลิกการเลือกช่วงคะแนน' : '');
      if (rangeStatus.textContent !== announcement) rangeStatus.textContent = announcement;
    }
  };
  const readyForBatch = () => {
    if (state.pendingBatch) { message('กำลังบันทึกตารางคะแนน กรุณารอสักครู่', 'saving'); return false; }
    if (state.editing) { message('กรุณาบันทึกหรือยกเลิกการแก้ไขช่องปัจจุบันก่อน', 'error'); return false; }
    if (state.uncertain) { message('ผลการบันทึกยังไม่แน่นอน กรุณาโหลดหน้าใหม่ก่อนแก้ไขต่อ', 'error'); return false; }
    if (!config.canScore || state.permissionLost) { message('ไม่มีสิทธิ์แก้ไขคะแนนในหน้านี้', 'error'); return false; }
    return true;
  };
  const parseTsv = raw => {
    if (raw.length > 262144) throw new Error('ตารางคะแนนมีขนาดใหญ่เกินไป');
    let normalized = raw.replace(/\r\n?/g, '\n');
    if (normalized.endsWith('\n')) normalized = normalized.slice(0, -1);
    const values = normalized.split('\n').map(row => row.split('\t'));
    if (values.some(row => row.length !== values[0].length)) throw new Error('แต่ละแถวต้องมีจำนวนช่องเท่ากัน');
    if (values.length * values[0].length > config.batchLimit) throw new Error(`วางได้สูงสุด ${config.batchLimit} ช่องต่อครั้ง`);
    return values;
  };
  const planPaste = (start, values) => {
    const { x, y } = indices(start), end = cellAt(y + values.length - 1, x + values[0].length - 1);
    if (!end || y < 0 || x < 0) throw new Error('วางไม่ได้: ตารางเกินช่องคะแนนที่แก้ไขได้');
    const enrollmentIds = [], componentIds = [], targets = new Map();
    for (let row = 0; row < values.length; row++) {
      for (let column = 0; column < values[0].length; column++) {
        const cell = cellAt(y + row, x + column);
        if (!isWritableScoreCell(cell)) throw new Error('วางไม่ได้: ตารางเกินช่องคะแนนที่แก้ไขได้หรือมีแถวประวัติ');
        const rowId = cell.getRow().getData().enrollmentId, componentId = componentByField.get(cell.getField()).id;
        if (!integer(rowId) || !integer(componentId) || rowId === 0 || componentId === 0)
          throw new Error('ตารางคะแนนเปลี่ยนแปลง กรุณาโหลดหน้าใหม่');
        if (column === 0) enrollmentIds.push(rowId);
        if (row === 0) componentIds.push(componentId);
        if (enrollmentIds[row] !== rowId || componentIds[column] !== componentId || targets.has(`${rowId}:${componentId}`))
          throw new Error('ตารางคะแนนเปลี่ยนแปลง กรุณาโหลดหน้าใหม่');
        targets.set(`${rowId}:${componentId}`, cell);
      }
    }
    return { enrollmentIds, componentIds, targets, start, end, height: values.length, width: values[0].length };
  };
  const validateBatch = (data, matrix, plan) => {
    const bad = () => { throw new Error('บันทึกแล้ว แต่แสดงผลล่าสุดไม่สำเร็จ กรุณาโหลดหน้าใหม่เพื่อตรวจสอบคะแนน'); };
    if (!data || data.committed !== true || data.offering_id !== config.offeringId
      || data.row_count !== plan.height || data.column_count !== plan.width || data.targeted_count !== plan.targets.size
      || !integer(data.changed_count) || data.changed_count > plan.targets.size
      || !Array.isArray(data.cells) || data.cells.length !== plan.targets.size
      || !Array.isArray(data.rows) || data.rows.length !== plan.height) bad();
    const cells = [], summaries = [], seenCells = new Set(), seenRows = new Set();
    for (const item of data.cells) {
      if (!item || !integer(item.enrollment_id) || !integer(item.component_id) || typeof item.changed !== 'boolean'
        || (item.score !== null && !decimal(item.score))) bad();
      const key = `${item.enrollment_id}:${item.component_id}`, cell = plan.targets.get(key);
      if (!cell || seenCells.has(key) || !rowById.has(item.enrollment_id) || cellKey(cell) !== key) bad();
      seenCells.add(key); cells.push({ cell, value: item.score ?? '' });
    }
    for (const item of data.rows) {
      if (!item || !matrix.enrollment_ids.includes(item.enrollment_id) || seenRows.has(item.enrollment_id)
        || !decimal(item.entered_score_total) || !decimal(item.configured_max_total)
        || !integer(item.entered_component_count) || !integer(item.active_component_count)
        || item.entered_component_count > item.active_component_count || typeof item.complete !== 'boolean') bad();
      seenRows.add(item.enrollment_id);
      summaries.push({ id: item.enrollment_id, values: { total: item.entered_score_total,
        max: item.configured_max_total, count: `${item.entered_component_count} / ${item.active_component_count}`,
        complete: item.complete ? 'ครบ' : 'ยังไม่ครบ' } });
    }
    return { cells, summaries };
  };
  const apply = async (cells, summaries, revision) => {
    const scroller = host.querySelector('.tabulator-tableholder');
    const scroll = { left: scroller?.scrollLeft, top: scroller?.scrollTop };
    const restoreFocus = host.contains(document.activeElement);
    state.applying = true;
    try {
      for (const { cell, value } of cells) {
        if (cell.getValue() !== value) cell.setValue(value);
        authoritative.set(cellKey(cell), value);
        state.errors.delete(cellKey(cell));
        clearError(cell);
      }
      for (const { id, values } of summaries) {
        if ((state.rowApplied.get(id) ?? -1) > revision) continue;
        state.rowApplied.set(id, revision);
        Object.assign(rowById.get(id), values);
        await table.getRow(id).update(values);
      }
    } finally {
      state.applying = false;
      if (scroller) { scroller.scrollLeft = scroll.left; scroller.scrollTop = scroll.top; }
      if (state.active) {
        const active = liveCell(state.active);
        if (active) activate(active, restoreFocus && !state.editing && !state.composing
          && (host.contains(document.activeElement) || document.activeElement === document.body), false);
      }
    }
  };
  const request = async (url, body) => {
    const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 30000);
    try { return await fetch(url, { method: 'POST', credentials: 'same-origin', signal: controller.signal,
      headers: { Accept: 'application/json' }, body: new URLSearchParams(body) }); }
    finally { clearTimeout(timeout); }
  };
  const refreshBatchPlan = plan => {
    const targets = new Map();
    for (const rowId of plan.enrollmentIds) for (const componentId of plan.componentIds) {
      const row = table.getRow(rowId);
      const cell = row && row.getCell(`score_${componentId}`);
      if (!isWritableScoreCell(cell) || cellKey(cell) !== `${rowId}:${componentId}`)
        throw new Error('ตารางคะแนนเปลี่ยนแปลง กรุณาโหลดหน้าใหม่');
      targets.set(`${rowId}:${componentId}`, cell);
    }
    plan.targets = targets;
    if (plan.start) plan.start = liveCell(plan.start);
    if (plan.end) plan.end = liveCell(plan.end);
    return plan;
  };
  const submitBatch = async (matrix, plan, sourceName, completion = null) => {
    const revision = ++state.revision;
    const commandOwner = state.focusOwner;
    const priorSingles = state.singleQueue;
    const waitingForSingles = state.pendingSingles.size > 0;
    state.pendingBatch = true; host.setAttribute('aria-busy', 'true'); updateControls();
    message(waitingForSingles ? 'รอการบันทึกช่องก่อนหน้า แล้วจะทำคำสั่งช่วงที่เลือก' : `กำลังบันทึกคะแนน ${plan.targets.size} ช่อง`, 'saving');
    let committed = false;
    try {
      if (waitingForSingles) await priorSingles;
      if (state.uncertain) throw new Error('ผลการบันทึกก่อนหน้ายังไม่แน่นอน กรุณาโหลดหน้าใหม่ก่อนแก้ไขต่อ');
      if (!config.canScore || state.permissionLost) throw new Error('ไม่มีสิทธิ์แก้ไขคะแนนในหน้านี้');
      refreshBatchPlan(plan);
      message(`กำลังบันทึกคะแนน ${plan.targets.size} ช่อง`, 'saving');
      const response = await request(`/hx/gradebook/${config.offeringId}/scores/batch`, { _token: token(), batch: JSON.stringify(matrix) });
      const json = response.headers.get('Content-Type')?.split(';')[0].trim() === 'application/json';
      const data = json ? await response.json() : null;
      committed = data?.committed === true || response.headers.get('X-Gradebook-Batch-Saved') === '1';
      if (response.status !== 200 || response.headers.get('X-Gradebook-Batch-Saved') !== '1' || !json) {
        if (response.status === 403 || response.status === 422 && /สิทธิ์/.test(data?.message ?? '')) state.permissionLost = true;
        if (committed || response.status === 409 || response.status === 200 || response.status >= 500) state.uncertain = true;
        const location = data?.location;
        if (location && integer(location.enrollment_id) && integer(location.component_id)) {
          const offending = plan.targets.get(`${location.enrollment_id}:${location.component_id}`);
          if (offending) markError(offending);
        }
        throw new Error(typeof data?.message === 'string' ? data.message : 'ยืนยันผลการบันทึกไม่ได้ กรุณาโหลดหน้าใหม่');
      }
      const updates = validateBatch(data, matrix, plan);
      await apply(updates.cells, updates.summaries, revision);
      if (completion) completion(commandOwner !== 'external' && state.focusOwner !== 'external');
      message(`${sourceName} ${data.targeted_count} ช่องแล้ว (เปลี่ยนแปลง ${data.changed_count} ช่อง)`, 'saved');
    } catch (error) {
      if (committed || error.name === 'AbortError' || error instanceof TypeError || error instanceof SyntaxError) state.uncertain = true;
      message(state.uncertain ? 'ผลการบันทึกยังไม่แน่นอน กรุณาโหลดหน้าใหม่เพื่อตรวจสอบคะแนนก่อนแก้ไขต่อ' : error.message, 'error');
    } finally {
      state.pendingBatch = false; host.removeAttribute('aria-busy'); updateControls();
    }
  };
  const summaryFromFragment = (fragment, rowId) => {
    const values = {};
    for (const suffix of ['total', 'max', 'count', 'complete']) {
      const element = fragment.getElementById(`row-${config.offeringId}-${rowId}-${suffix}`);
      if (!element) throw new Error('ผลตอบกลับไม่มีสรุปคะแนนครบ');
      values[suffix] = element.textContent;
    }
    if (!decimal(values.total) || !decimal(values.max) || !/^\d+ \/ \d+$/.test(values.count)
      || !['ครบ', 'ยังไม่ครบ'].includes(values.complete)) throw new Error('ผลตอบกลับสรุปคะแนนไม่ถูกต้อง');
    return values;
  };
  const submitSingle = async (cell, typed, previous) => {
    const key = cellKey(cell), rowId = cell.getRow().getData().enrollmentId, componentId = componentByField.get(cell.getField()).id;
    const revision = ++state.revision;
    state.pendingSingles.set(key, revision);
    cell.getElement()?.setAttribute('data-pp5-saving', 'true');
    message('กำลังบันทึกคะแนน', 'saving'); updateControls();
    const ahead = state.singleQueue;
    let release;
    state.singleQueue = new Promise(resolve => { release = resolve; });
    let commitConfirmed = false;
    try {
      await ahead;
      if (state.uncertain || state.permissionLost) throw new Error('กรุณาโหลดหน้าใหม่ก่อนแก้ไขคะแนนต่อ');
      const response = await request(`/hx/gradebook/${config.offeringId}/components/${componentId}/enrollments/${rowId}/score`,
        { score: typed, _token: token() });
      if (response.status !== 200 || response.headers.get('X-Gradebook-Saved') !== '1') {
        if (response.status === 403) state.permissionLost = true;
        if (response.status === 409 || response.status === 200 || response.status >= 500) state.uncertain = true;
        const body = await response.text();
        const parsed = new DOMParser().parseFromString(body, 'text/html');
        throw new Error(parsed.querySelector('[data-save-state="error"]')?.textContent || 'บันทึกไม่สำเร็จ กรุณาตรวจสอบคะแนนหรือโหลดหน้าใหม่');
      }
      commitConfirmed = true;
      const fragment = new DOMParser().parseFromString(await response.text(), 'text/html');
      const returned = fragment.getElementById(`score-${config.offeringId}-${componentId}-${rowId}-input`);
      if (!returned || !returned.hasAttribute('value') || (returned.value !== '' && !decimal(returned.value)))
        throw new Error('ผลตอบกลับคะแนนไม่ถูกต้อง');
      const values = summaryFromFragment(fragment, rowId);
      // A later local edit or result owns the cell. The stale response may still carry a stale row summary.
      if (state.pendingSingles.get(key) !== revision || cell.getValue() !== typed) return;
      await apply([{ cell, value: returned.value }], [{ id: rowId, values }], revision);
      if (!state.pendingBatch) message('', 'saved');
    } catch (error) {
      if (commitConfirmed || error.name === 'AbortError' || error instanceof TypeError || error instanceof SyntaxError) state.uncertain = true;
      if (cell.getValue() === typed) {
        state.errors.set(key, typed);
        markError(cell);
      }
      message(state.uncertain ? 'ผลการบันทึกยังไม่แน่นอน กรุณาโหลดหน้าใหม่เพื่อตรวจสอบคะแนนก่อนแก้ไขต่อ' : error.message, 'error');
    } finally {
      release();
      if (state.pendingSingles.get(key) === revision) state.pendingSingles.delete(key);
      cell.getElement()?.removeAttribute('data-pp5-saving'); updateControls();
    }
  };
  const neighbor = (cell, direction, writableOnly = false) => {
    const { x, y } = indices(cell), dx = direction === 'right' ? 1 : direction === 'left' ? -1 : 0;
    const dy = direction === 'down' ? 1 : direction === 'up' ? -1 : 0;
    if (dx) { const target = cellAt(y, x + dx); return (writableOnly ? isWritableScoreCell(target) : isNavigableScoreCell(target)) ? target : null; }
    for (let next = y + dy; next >= 0 && next < rows().length; next += dy) {
      const target = cellAt(next, x);
      if (writableOnly ? isWritableScoreCell(target) : isNavigableScoreCell(target)) return target;
    }
    return null;
  };
  const leaveGrid = direction => {
    const candidates = [...document.querySelectorAll('a[href], button:not(:disabled), input:not(:disabled), summary, [tabindex="0"]')]
      .filter(node => !host.contains(node) && !fallback.contains(node) && node.getClientRects().length);
    const position = candidates.findIndex(node => host.compareDocumentPosition(node) & Node.DOCUMENT_POSITION_FOLLOWING);
    const target = direction === 'right' ? candidates[position] : candidates[position - 1];
    (target || document.querySelector('main a, main button'))?.focus();
  };
  const editor = (cell, onRendered, success, cancel) => {
    const input = document.createElement('input');
    input.type = 'text'; input.inputMode = 'decimal'; input.autocomplete = 'off';
    input.setAttribute('aria-label', `คะแนน ${cell.getRow().getData().identity.replace('\n', ' ')} ${componentByField.get(cell.getField()).name}`);
    // Tabulator's native double-click opens the existing value; keyboard openings set intent first.
    const intent = state.editIntent || 'explicit';
    state.editIntent = intent;
    const replace = intent === 'direct';
    input.value = replace ? state.pendingCharacter : text(cell.getValue());
    state.pendingCharacter = null;
    let done = false;
    const finish = (commit, direction = null, tabBoundary = false) => {
      if (done) return;
      done = true; state.editing = null; state.editIntent = null;
      if (commit) success(input.value); else cancel();
      if (direction) queueMicrotask(() => {
        const target = neighbor(cell, direction, true);
        if (target) select(target);
        else if (tabBoundary) leaveGrid(direction);
        else activate(cell);
      });
    };
    input.addEventListener('compositionstart', () => { state.composing = true; });
    input.addEventListener('compositionend', () => { state.composing = false; });
    input.addEventListener('keydown', event => {
      if (state.composing || event.isComposing) return;
      if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); finish(false); activate(cell); return; }
      let direction = null;
      if (event.key === 'Enter') direction = event.shiftKey ? 'up' : 'down';
      else if (event.key === 'Tab') direction = event.shiftKey ? 'left' : 'right';
      else if (event.key === 'ArrowUp') direction = 'up';
      else if (event.key === 'ArrowDown') direction = 'down';
      else if (intent === 'direct' && event.key === 'ArrowLeft') direction = 'left';
      else if (intent === 'direct' && event.key === 'ArrowRight') direction = 'right';
      if (direction) { event.preventDefault(); event.stopPropagation(); finish(true, direction, event.key === 'Tab'); }
    });
    input.addEventListener('blur', () => { if (!state.composing) finish(true); });
    onRendered(() => { input.focus(); if (!replace) input.setSelectionRange(input.value.length, input.value.length); });
    return input;
  };
  const columns = [
    { title: 'นักเรียน', field: 'identity', width: 190, frozen: true, variableHeight: true,
      formatter: cell => escapeHtml(cell.getValue()).replace('\n', '<br>'), cssClass: 'pp5-grid-identity' },
    { title: 'ประเภทแถว', field: 'rowType', width: 130, cssClass: 'pp5-grid-readonly' },
    ...config.components.map(component => ({
      title: `${escapeHtml(component.name)}<br><small>เต็ม ${escapeHtml(component.max)}</small>`,
      field: `score_${component.id}`, width: 92, hozAlign: 'right', editor,
      editable: cell => isWritableScoreCell(cell) && !state.pendingBatch && !state.pendingSingles.has(cellKey(cell)),
      formatter: cell => escapeHtml(cell.getValue()), cssClass: 'pp5-grid-score',
    })),
    ...[['total', 'คะแนนที่บันทึกรวม', 112], ['max', 'คะแนนเต็มรวม', 105],
      ['count', 'บันทึกแล้ว / ทั้งหมด', 128], ['complete', 'ความครบถ้วน', 104]]
      .map(([field, title, width]) => ({ title, field, width, cssClass: 'pp5-grid-readonly pp5-grid-summary' })),
  ];
  host.classList.add('pp5-grid-initializing');
  host.hidden = false;
  try {
    table = new Tabulator(host, {
      data: rowData, index: 'id', columns, layout: 'fitData', height: 'min(64vh, 560px)',
      renderVertical: 'virtual', columnDefaults: { headerSort: false, resizable: false },
      selectableRange: false, editTriggerEvent: 'dblclick', clipboard: false,
      rowFormatter: row => {
        if (row.getData().historical) row.getElement().classList.add('pp5-grid-historical');
        for (const element of row.getElement().querySelectorAll('.tabulator-cell'))
          element.tabIndex = componentByField.has(element.getAttribute('tabulator-field'))
            && state.active && row.getData().enrollmentId === state.active.getRow().getData().enrollmentId
            && element.getAttribute('tabulator-field') === state.active.getField() ? 0 : -1;
      },
    });
  } catch (_) { host.hidden = true; host.classList.remove('pp5-grid-initializing'); return; }
  table.on('tableBuilt', () => {
    for (const input of fallback.querySelectorAll('input[data-score-input]')) input.disabled = true;
    fallback.hidden = true;
    host.classList.remove('pp5-grid-initializing');
    if (actions) { actions.hidden = false; updateControls(); }
    syncTabStops(null);
  });
  table.on('renderComplete', () => {
    syncTabStops(state.active ? liveCell(state.active)?.getElement() : null);
    paintSelection();
  });
  let pointerDrag = false;
  table.on('cellClick', (_event, cell) => {
    if (!isNavigableScoreCell(cell) || pointerDrag) return;
    activate(cell, false);
    // Tabulator completes its pointer selection after the click callback.
    setTimeout(() => {
      if (state.editing || !cell.getElement()?.isConnected) return;
      if (!pointerDrag) select(cell);
    }, 0);
  });
  let pointerAnchor = null, pointerExtent = null;
  table.on('cellEditing', cell => { state.editing = cell; activate(cell, false); });
  table.on('cellEditCancelled', () => { state.editing = null; state.editIntent = null; });
  table.on('cellEdited', cell => {
    if (state.applying) return;
    state.editing = null; state.editIntent = null;
    const key = cellKey(cell), value = text(cell.getValue());
    const prior = authoritative.get(key) ?? '';
    if (value === prior && !state.errors.has(key)) return;
    rowById.get(cell.getRow().getData().enrollmentId)[cell.getField()] = value;
    submitSingle(cell, value, prior);
  });
  const beginPointerSelection = event => {
    const cell = cellFromElement(event.target);
    pointerAnchor = isScoreCell(cell) ? cell : null;
    pointerExtent = pointerAnchor;
    pointerDrag = false;
    if (pointerAnchor) select(pointerAnchor, pointerAnchor, false);
  };
  const movePointerSelection = event => {
    if (!pointerAnchor) return;
    const cell = cellFromElement(event.target);
    if (isScoreCell(cell)) {
      if (cell !== pointerAnchor) pointerDrag = true;
      pointerExtent = cell;
      if (pointerDrag) select(pointerAnchor, pointerExtent, false);
    }
  };
  const endPointerSelection = event => {
    if (!pointerAnchor) return;
    const end = cellFromElement(event.target);
    const anchor = pointerAnchor, extent = isScoreCell(end) ? end : pointerExtent;
    pointerAnchor = null; pointerExtent = null;
    // Range extent is final only after pointerup; focusing during rangeChanged interrupts dragging.
    setTimeout(() => {
      if (!anchor || state.editing) return;
      select(anchor, extent || anchor);
    }, 0);
  };
  host.addEventListener('pointerdown', event => { if (event.pointerType !== 'touch') beginPointerSelection(event); }, true);
  host.addEventListener('mousedown', beginPointerSelection, true);
  host.addEventListener('pointermove', event => { if (event.pointerType !== 'touch') movePointerSelection(event); }, true);
  host.addEventListener('mousemove', movePointerSelection, true);
  document.addEventListener('pointerup', event => { if (event.pointerType !== 'touch') endPointerSelection(event); }, true);
  document.addEventListener('mouseup', endPointerSelection, true);
  document.addEventListener('focusin', event => {
    if (host.contains(event.target)) state.focusOwner = 'grid';
    else if (actions?.contains(event.target)) state.focusOwner = 'range';
    else state.focusOwner = 'external';
  });
  host.addEventListener('focusin', event => {
    if (event.target !== host) return;
    if (state.active) { activate(state.active); return; }
    const first = rows().find(row => fields.length && row.getCell(fields[0]));
    if (first) select(first.getCell(fields[0]));
  });
  host.addEventListener('compositionstart', () => { state.composing = true; }, true);
  host.addEventListener('compositionend', () => { state.composing = false; }, true);
  host.addEventListener('keydown', event => {
    if (state.composing || event.isComposing || state.editing || event.target instanceof HTMLInputElement) return;
    if (!host.contains(event.target)) return;
    const active = state.active;
    const shortcut = event.metaKey || event.ctrlKey;
    if (shortcut || event.altKey) return;
    if (event.key === 'Escape') {
      event.preventDefault(); event.stopPropagation();
      state.anchor = null; state.extent = null; paintSelection();
      if (state.active) activate(state.active);
      updateControls(); return;
    }
    if ((event.key === 'Delete' || event.key === 'Backspace') && selected()) {
      event.preventDefault(); event.stopPropagation();
      if (!readyForBatch()) return;
      try { const plan = selectedPlan();
        const matrix = { enrollment_ids: plan.enrollmentIds, component_ids: plan.componentIds,
          values: plan.enrollmentIds.map(() => plan.componentIds.map(() => '')) };
        const topLeft = selected().matrix[0][0];
        submitBatch(matrix, plan, 'ล้างคะแนน', restore => {
          activate(topLeft, restore);
        });
      } catch (error) { message(error.message, 'error'); }
      return;
    }
    if (!active) return;
    if (event.shiftKey && event.key.startsWith('Arrow')) {
      const direction = event.key.slice(5).toLowerCase(), choice = selected();
      const contained = choice?.matrix.some(line => line.includes(active));
      const extent = contained && isNavigableScoreCell(state.extent) ? state.extent : active;
      const target = neighbor(extent, direction);
      if (target) { event.preventDefault(); event.stopPropagation(); select(contained ? state.anchor || active : active, target); }
      return;
    }
    if (event.key.length === 1 && !event.shiftKey && isWritableScoreCell(active) && !state.pendingSingles.has(cellKey(active))) {
      event.preventDefault(); event.stopPropagation();
      state.pendingCharacter = event.key; state.editIntent = 'direct';
      active.edit();
      if (!state.editing) { state.pendingCharacter = null; state.editIntent = null; }
      return;
    }
    if ((event.key === 'Enter' || event.key === 'F2') && isWritableScoreCell(active)) {
      event.preventDefault(); event.stopPropagation();
      state.pendingCharacter = null; state.editIntent = 'explicit';
      active.edit();
      if (!state.editing) state.editIntent = null;
      return;
    }
    if (event.key === 'Tab' || event.key.startsWith('Arrow')) {
      const direction = event.key === 'Tab' ? (event.shiftKey ? 'left' : 'right') : event.key.slice(5).toLowerCase();
      const target = neighbor(active, direction);
      if (target) { event.preventDefault(); event.stopPropagation(); select(target); }
      else if (event.key === 'Tab') { event.preventDefault(); event.stopPropagation(); leaveGrid(direction); }
    }
  }, true);
  host.addEventListener('copy', event => {
    if (state.editing || !event.clipboardData || !selected()) return;
    const selection = selected().matrix;
    const tsv = selection.map(line => line.map(cell => text(cell.getValue())).join('\t')).join('\n');
    event.clipboardData.setData('text/plain', tsv); event.preventDefault(); event.stopImmediatePropagation();
    if (rangeStatus) rangeStatus.textContent = 'คัดลอกช่วงคะแนนแล้ว';
  }, true);
  host.addEventListener('paste', event => {
    if (state.editing || event.target instanceof HTMLInputElement) return;
    if (state.composing || !event.clipboardData || !event.clipboardData.types.includes('text/plain')) return;
    event.preventDefault(); event.stopImmediatePropagation();
    if (!readyForBatch()) return;
    const choice = selected();
    const start = choice && choice.matrix.length * choice.matrix[0].length > 1
      ? choice.matrix[0][0] : state.active;
    if (!isWritableScoreCell(start)) { message('ช่องนี้ไม่สามารถวางคะแนนได้', 'error'); return; }
    try { const values = parseTsv(event.clipboardData.getData('text/plain'));
      const plan = planPaste(start, values);
      submitBatch({ enrollment_ids: plan.enrollmentIds, component_ids: plan.componentIds, values }, plan, 'บันทึกคะแนน', restore => {
        select(plan.start, plan.end, restore);
        activate(plan.start, restore);
      });
    } catch (error) { message(error.message, 'error'); }
  }, true);
  if (actions) {
    fillInput.addEventListener('compositionstart', () => { state.fillComposing = true; });
    fillInput.addEventListener('compositionend', () => { state.fillComposing = false; });
    fillInput.addEventListener('keydown', event => {
      if (event.key === 'Enter' && (state.fillComposing || event.isComposing)) event.preventDefault();
    });
    const runRange = (value, sourceName) => {
      if (!readyForBatch()) return;
      try { const plan = selectedPlan();
        const topLeft = selected().matrix[0][0];
        submitBatch({ enrollment_ids: plan.enrollmentIds, component_ids: plan.componentIds,
          values: plan.enrollmentIds.map(() => plan.componentIds.map(() => value)) }, plan, sourceName,
        restore => activate(topLeft, restore));
      } catch (error) { message(error.message, 'error'); }
    };
    actions.addEventListener('submit', event => {
      event.preventDefault(); if (state.fillComposing || event.isComposing) return;
      if (fillInput.value === '') { message('กรุณากรอกคะแนน หรือใช้ปุ่มล้างคะแนน', 'error'); return; }
      runRange(fillInput.value, 'ใส่คะแนน');
    });
    clearButton.addEventListener('click', () => runRange('', 'ล้างคะแนน'));
  }
})();
