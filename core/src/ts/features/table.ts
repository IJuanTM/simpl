import {storage} from '../helpers/storage.ts';

const HIDDEN_KEY = 'table-hidden-cols';
const MIN_COL_WIDTH = 64;

export interface TableHandle {
  // Re-applies the stored column widths, hidden columns and resize handles after the thead/tbody markup was replaced.
  refresh(): void;
}

const handles = new WeakMap<HTMLTableElement, TableHandle>();

function hiddenKeyFor(table: HTMLTableElement): string {
  return `${HIDDEN_KEY}-${table.dataset.tableId ?? table.id ?? 'table'}`;
}

function getHeaders(table: HTMLTableElement): HTMLTableCellElement[] {
  return Array.from(table.querySelectorAll<HTMLTableCellElement>('thead th'));
}

function getColToggleCheckbox(controls: Element | null, col: number): HTMLInputElement | null {
  return controls?.querySelector<HTMLInputElement>(`.col-toggle-panel input[data-col="${col}"]`) ?? null;
}

function setColumnHidden(table: HTMLTableElement, col: number, hidden: boolean): void {
  getHeaders(table)[col]?.toggleAttribute('data-hidden', hidden);

  const tbody = table.tBodies[0];
  if (!tbody) return;

  for (const row of Array.from(tbody.rows)) {
    if (!row.classList.contains('table-empty-row')) row.cells[col]?.toggleAttribute('data-hidden', hidden);
  }
}

function applyHiddenColumns(table: HTMLTableElement, hiddenKey: string): void {
  for (const col of getHiddenCols(hiddenKey, table)) setColumnHidden(table, col, true);
}

function setCellWidth(cell: HTMLElement, width: number): void {
  cell.style.width = cell.style.minWidth = cell.style.maxWidth = `${width}px`;
}

function parseStorage<T>(key: string, fallback: T): T {
  try {
    return JSON.parse(storage.get(key) ?? JSON.stringify(fallback));
  } catch {
    return fallback;
  }
}

function getDefaultHiddenCols(table: HTMLTableElement): number[] {
  try {
    return JSON.parse(table.dataset.hiddenCols ?? '[]');
  } catch {
    return [];
  }
}

function getHiddenCols(key: string, table: HTMLTableElement): number[] {
  if (storage.get(key) === null) storage.set(key, JSON.stringify(getDefaultHiddenCols(table).sort((a, b) => a - b)));
  return parseStorage<number[]>(key, []);
}

function getDefaultWidths(table: HTMLTableElement): number[] {
  return getHeaders(table).map(th => th.dataset.width
    ? Number.parseInt(th.dataset.width, 10)
    : Math.max(MIN_COL_WIDTH, th.offsetWidth || 100)
  );
}

// Sibling key to hiddenKey, derived instead of threaded through every caller.
function widthsKeyFor(hiddenKey: string): string {
  return hiddenKey.replace(HIDDEN_KEY, 'table-widths');
}

function getStoredWidths(hiddenKey: string): number[] | null {
  const raw = storage.get(widthsKeyFor(hiddenKey));
  if (raw === null) return null;

  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

function getEffectiveWidths(table: HTMLTableElement, defaultWidths: number[], hiddenKey: string): number[] {
  const stored = getStoredWidths(hiddenKey);
  if (!stored) return defaultWidths;
  return getHeaders(table).map((_, col) => stored[col] ?? defaultWidths[col] ?? 100);
}

// Hidden columns report offsetWidth 0, so their previous (or default) width is kept instead of overwriting it with 0; resizing one column must not corrupt another's.
function persistWidths(table: HTMLTableElement, hiddenKey: string, defaultWidths: number[]): void {
  const hidden = new Set(getHiddenCols(hiddenKey, table));
  const previous = getStoredWidths(hiddenKey);
  const widths = getHeaders(table).map((th, col) => hidden.has(col) ? (previous?.[col] ?? defaultWidths[col] ?? 100) : th.offsetWidth);
  storage.set(widthsKeyFor(hiddenKey), JSON.stringify(widths));
}

// Compares the persisted widths themselves against the defaults, unlike hasWidthChanges() below (which reads live offsetWidth).
// Needed at page-load time, before restoreState() has applied any stored widths to the DOM.
// A resize handle clicked without being dragged persists widths equal to the defaults, so comparing values here (not just presence) keeps that from being reported as a real customization.
function hasStoredWidthChanges(table: HTMLTableElement, defaultWidths: number[], hiddenKey: string): boolean {
  const stored = getStoredWidths(hiddenKey);
  if (!stored) return false;

  const hidden = new Set(getHiddenCols(hiddenKey, table));
  return getHeaders(table).some((_, col) => !hidden.has(col) && (stored[col] ?? defaultWidths[col] ?? 100) !== defaultWidths[col]);
}

function hasWidthChanges(table: HTMLTableElement, defaultWidths: number[], hiddenKey: string): boolean {
  const hidden = new Set(getHiddenCols(hiddenKey, table));
  return getHeaders(table).some((th, i) => !hidden.has(i) && th.offsetWidth !== defaultWidths[i]);
}

function applyColumnWidth(table: HTMLTableElement, col: number, width: number): void {
  const clamped = Math.max(MIN_COL_WIDTH, width);
  const headers = getHeaders(table);
  if (headers[col]) setCellWidth(headers[col] as HTMLElement, clamped);

  const tbody = table.tBodies[0];
  if (!tbody) return;
  for (const row of Array.from(tbody.rows)) {
    if (row.cells[col]) setCellWidth(row.cells[col] as HTMLElement, clamped);
  }
}

function syncColToggleState(table: HTMLTableElement, controls: Element | null, hiddenKey: string): void {
  if (!controls) return;

  const hidden = new Set(getHiddenCols(hiddenKey, table));
  const visibleCount = getHeaders(table).length - hidden.size;

  for (const label of Array.from(controls.querySelectorAll<HTMLLabelElement>('.col-toggle-panel label'))) {
    const cb = label.querySelector<HTMLInputElement>('input[data-col]');
    if (cb) label.inert = visibleCount === 1 && cb.checked;
  }
}

function syncResetBtnState(table: HTMLTableElement, resetBtn: HTMLButtonElement | null, hiddenKey: string, hasCustomWidths: boolean): void {
  if (!resetBtn) return;
  const hidden = getHiddenCols(hiddenKey, table);
  const defaults = getDefaultHiddenCols(table);
  resetBtn.inert = !hasCustomWidths && hidden.length === defaults.length && hidden.every((c, i) => c === defaults[i]);
}

function initColToggle(table: HTMLTableElement, controls: Element, hiddenKey: string, onStateChange: () => void): void {
  const btn = controls.querySelector<HTMLButtonElement>('.col-toggle-btn');
  const panel = controls.querySelector<HTMLElement>('.col-toggle-panel');
  if (!btn || !panel) return;

  for (const cb of Array.from(panel.querySelectorAll<HTMLInputElement>('input[data-col]'))) {
    const col = Number.parseInt(cb.dataset.col!, 10);

    cb.addEventListener('change', () => {
      const hidden = new Set(getHiddenCols(hiddenKey, table));
      const visibleCount = getHeaders(table).length - hidden.size;

      if (!cb.checked) {
        if (visibleCount <= 1) {
          cb.checked = true;
          onStateChange();
          return;
        }
        hidden.add(col);
        setColumnHidden(table, col, true);
      } else {
        hidden.delete(col);
        setColumnHidden(table, col, false);
      }

      storage.set(hiddenKey, JSON.stringify(Array.from(hidden).sort((a, b) => a - b)));
      onStateChange();
    });
  }

  panel.addEventListener('toggle', e => btn.setAttribute('aria-expanded', String((e as ToggleEvent).newState === 'open')));
}

function addResizeHandle(table: HTMLTableElement, th: HTMLTableCellElement, col: number, defaultWidths: number[], hiddenKey: string, onStateChange: () => void, onWidthChange: (isDirty: boolean) => void): void {
  const handle = document.createElement('span');
  handle.className = 'col-resize-handle';
  th.appendChild(handle);

  handle.addEventListener('pointerdown', e => {
    e.preventDefault();
    handle.setPointerCapture(e.pointerId);
    const startX = e.clientX;
    const startW = th.offsetWidth;

    const onMove = (mv: PointerEvent) => {
      applyColumnWidth(table, col, startW + mv.clientX - startX);
      onWidthChange(hasWidthChanges(table, defaultWidths, hiddenKey));
      onStateChange();
    };

    const onUp = () => {
      handle.removeEventListener('pointermove', onMove);
      handle.removeEventListener('pointerup', onUp);
      handle.removeEventListener('pointercancel', onUp);
      persistWidths(table, hiddenKey, defaultWidths);
      onWidthChange(hasWidthChanges(table, defaultWidths, hiddenKey));
      onStateChange();
    };

    handle.addEventListener('pointermove', onMove);
    handle.addEventListener('pointerup', onUp);
    handle.addEventListener('pointercancel', onUp);
  });
}

function addResizeHandles(table: HTMLTableElement, defaultWidths: number[], hiddenKey: string, onStateChange: () => void, onWidthChange: (isDirty: boolean) => void): void {
  const headers = getHeaders(table);
  headers.forEach((th, col) => {
    if (col < headers.length - 1) addResizeHandle(table, th, col, defaultWidths, hiddenKey, onStateChange, onWidthChange);
  });
}

function restoreState(table: HTMLTableElement, controls: Element | null, hiddenKey: string, defaultWidths: number[]): void {
  const hidden = new Set(getHiddenCols(hiddenKey, table));

  getHeaders(table).forEach((_, col) => {
    const isHidden = hidden.has(col);
    setColumnHidden(table, col, isHidden);
    const cb = getColToggleCheckbox(controls, col);
    if (cb) cb.checked = !isHidden;
  });

  getEffectiveWidths(table, defaultWidths, hiddenKey).forEach((w, col) => applyColumnWidth(table, col, w));
}

function initReset(table: HTMLTableElement, controls: Element, resetBtn: HTMLButtonElement, hiddenKey: string, defaultWidths: number[], onStateChange: () => void, onWidthChange: (isDirty: boolean) => void): void {
  resetBtn.addEventListener('click', () => {
    storage.remove(hiddenKey);
    storage.remove(widthsKeyFor(hiddenKey));
    const defaults = new Set(getDefaultHiddenCols(table));

    getHeaders(table).forEach((_, col) => {
      const hidden = defaults.has(col);
      setColumnHidden(table, col, hidden);
      const cb = getColToggleCheckbox(controls, col);
      if (cb) cb.checked = !hidden;
      applyColumnWidth(table, col, defaultWidths[col] ?? 100);
    });

    onWidthChange(false);
    onStateChange();
  });
}

// Delegated on the table rather than the rows, so rows swapped in by an AJAX reload link too.
function initRowLinks(table: HTMLTableElement): void {
  const rowFor = (event: Event): HTMLTableRowElement | null => {
    const target = event.target as HTMLElement;
    if (target.closest('a, button, input, select, textarea, label, [role="button"]')) return null;
    return target.closest<HTMLTableRowElement>('tbody tr[data-href]');
  };

  const open = (row: HTMLTableRowElement, newTab: boolean): void => {
    const href = row.dataset.href ?? '';
    if (newTab) window.open(href, '_blank', 'noopener');
    else window.location.assign(href);
  };

  table.addEventListener('click', e => {
    const row = rowFor(e);
    if (row && !window.getSelection()?.toString()) open(row, e.ctrlKey || e.metaKey);
  });

  table.addEventListener('auxclick', e => {
    const row = rowFor(e);
    if (row && e.button === 1) open(row, true);
  });

  table.addEventListener('keydown', e => {
    const row = rowFor(e);
    if (row && e.key === 'Enter' && e.target === row) open(row, e.ctrlKey || e.metaKey);
  });
}

// Idempotent, so an extension (e.g. one that reloads the rows via AJAX) can grab the handle regardless of which module initialises first.
export function initTable(table: HTMLTableElement): TableHandle {
  const existing = handles.get(table);
  if (existing) return existing;

  const hiddenKey = hiddenKeyFor(table);
  const defaultWidths = getDefaultWidths(table);
  const controls = table.dataset.tableId ? document.querySelector(`.col-toggle-wrapper[data-table-controls="${table.dataset.tableId}"]`) : null;
  const resetBtn = controls?.querySelector<HTMLButtonElement>('.table-reset-btn') ?? null;
  let hasCustomWidths = hasStoredWidthChanges(table, defaultWidths, hiddenKey);

  const syncState = (): void => {
    syncColToggleState(table, controls, hiddenKey);
    syncResetBtnState(table, resetBtn, hiddenKey, hasCustomWidths);
  };

  const onWidthChange = (isDirty: boolean): void => {
    hasCustomWidths = isDirty;
  };

  defaultWidths.forEach((w, col) => applyColumnWidth(table, col, w));
  table.style.tableLayout = 'fixed';
  table.style.width = 'max-content';
  table.style.minWidth = '100%';
  addResizeHandles(table, defaultWidths, hiddenKey, syncState, onWidthChange);

  initRowLinks(table);
  if (controls) initColToggle(table, controls, hiddenKey, syncState);
  restoreState(table, controls, hiddenKey, defaultWidths);
  if (controls && resetBtn) initReset(table, controls, resetBtn, hiddenKey, defaultWidths, syncState, onWidthChange);
  syncState();

  const handle: TableHandle = {
    refresh(): void {
      const widths = getEffectiveWidths(table, defaultWidths, hiddenKey);
      widths.forEach((w, col) => applyColumnWidth(table, col, w));
      if (!getHeaders(table).some(th => th.querySelector('.col-resize-handle'))) addResizeHandles(table, defaultWidths, hiddenKey, syncState, onWidthChange);
      applyHiddenColumns(table, hiddenKey);
      syncState();
    }
  };

  handles.set(table, handle);
  return handle;
}

export const tableModule = {
  init(): void {
    document.querySelectorAll<HTMLTableElement>('table.data-table').forEach(initTable);
  }
};
