import {storage} from '../utils/storage.ts';

const HIDDEN_KEY = 'table-hidden-cols';
const MIN_COL_WIDTH = 64;

function getHeaders(table: HTMLTableElement): HTMLTableCellElement[] {
  return Array.from(table.querySelectorAll<HTMLTableCellElement>('thead th'));
}

function getColToggleCheckbox(container: Element, col: number): HTMLInputElement | null {
  return container.querySelector<HTMLInputElement>(`.col-toggle-panel input[data-col="${col}"]`);
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

// Hidden columns report offsetWidth 0, so their previous (or default) width is kept
// instead of overwriting it with 0 - resizing one column must not corrupt another's.
function persistWidths(table: HTMLTableElement, hiddenKey: string, defaultWidths: number[]): void {
  const hidden = new Set(getHiddenCols(hiddenKey, table));
  const previous = getStoredWidths(hiddenKey);
  const widths = getHeaders(table).map((th, col) => hidden.has(col) ? (previous?.[col] ?? defaultWidths[col] ?? 100) : th.offsetWidth);
  storage.set(widthsKeyFor(hiddenKey), JSON.stringify(widths));
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

function syncColToggleState(table: HTMLTableElement, container: Element, hiddenKey: string): void {
  const hidden = new Set(getHiddenCols(hiddenKey, table));
  const visibleCount = getHeaders(table).length - hidden.size;

  for (const label of Array.from(container.querySelectorAll<HTMLLabelElement>('.col-toggle-panel label'))) {
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

function initColToggle(table: HTMLTableElement, container: Element, hiddenKey: string, onStateChange: () => void): void {
  const btn = container.querySelector<HTMLButtonElement>('.col-toggle-btn');
  const panel = container.querySelector<HTMLElement>('.col-toggle-panel');
  if (!btn || !panel) return;

  panel.addEventListener('click', e => e.stopPropagation());

  for (const cb of Array.from(panel.querySelectorAll<HTMLInputElement>('input[data-col]'))) {
    const col = Number.parseInt(cb.dataset.col!, 10);

    cb.addEventListener('change', () => {
      const hidden = new Set(getHiddenCols(hiddenKey, table));
      const visibleCount = getHeaders(table).length - hidden.size;

      if (!cb.checked) {
        if (visibleCount <= 1) {
          cb.checked = true;
          syncColToggleState(table, container, hiddenKey);
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
      syncColToggleState(table, container, hiddenKey);
      onStateChange();
    });
  }

  btn.addEventListener('click', e => {
    e.stopPropagation();
    panel.classList.toggle('open');
  });
  document.addEventListener('click', () => panel.classList.remove('open'));
}

function addResizeHandle(table: HTMLTableElement, th: HTMLTableCellElement, col: number, defaultWidths: number[], hiddenKey: string, onStateChange: () => void, onWidthChange: (isDirty: boolean) => void): void {
  const handle = document.createElement('span');
  handle.className = 'col-resize-handle';
  th.appendChild(handle);

  handle.addEventListener('mousedown', e => {
    e.preventDefault();
    const startX = e.clientX;
    const startW = th.offsetWidth;

    const onMove = (mv: MouseEvent) => {
      applyColumnWidth(table, col, startW + mv.clientX - startX);
      onWidthChange(hasWidthChanges(table, defaultWidths, hiddenKey));
      onStateChange();
    };

    const onUp = () => {
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      persistWidths(table, hiddenKey, defaultWidths);
      onWidthChange(hasWidthChanges(table, defaultWidths, hiddenKey));
      onStateChange();
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  });
}

function initResize(table: HTMLTableElement, defaultWidths: number[], hiddenKey: string, onStateChange: () => void, onWidthChange: (isDirty: boolean) => void): void {
  defaultWidths.forEach((w, col) => applyColumnWidth(table, col, w));

  table.style.tableLayout = 'fixed';
  table.style.width = 'max-content';
  table.style.minWidth = '100%';

  const headers = getHeaders(table);
  headers.forEach((th, col) => {
    if (col < headers.length - 1) addResizeHandle(table, th, col, defaultWidths, hiddenKey, onStateChange, onWidthChange);
  });
}

function restoreState(table: HTMLTableElement, container: Element, hiddenKey: string, defaultWidths: number[]): void {
  const hidden = new Set(getHiddenCols(hiddenKey, table));

  getHeaders(table).forEach((_, col) => {
    const isHidden = hidden.has(col);
    setColumnHidden(table, col, isHidden);
    const cb = getColToggleCheckbox(container, col);
    if (cb) cb.checked = !isHidden;
  });

  getEffectiveWidths(table, defaultWidths, hiddenKey).forEach((w, col) => applyColumnWidth(table, col, w));
  syncColToggleState(table, container, hiddenKey);
}

function initReset(table: HTMLTableElement, container: Element, hiddenKey: string, defaultWidths: number[], onStateChange: () => void, onWidthChange: (isDirty: boolean) => void): void {
  const resetBtn = container.querySelector<HTMLButtonElement>('.table-reset-btn');
  if (!resetBtn) return;

  resetBtn.addEventListener('click', () => {
    storage.remove(hiddenKey);
    storage.remove(widthsKeyFor(hiddenKey));
    const defaults = new Set(getDefaultHiddenCols(table));

    getHeaders(table).forEach((_, col) => {
      const hidden = defaults.has(col);
      setColumnHidden(table, col, hidden);
      const cb = getColToggleCheckbox(container, col);
      if (cb) cb.checked = !hidden;
      applyColumnWidth(table, col, defaultWidths[col] ?? 100);
    });

    onWidthChange(false);
    syncColToggleState(table, container, hiddenKey);
    onStateChange();
  });
}

function buildUrl(baseUrl: string, newParams: Record<string, string | number | null>): URL {
  const params = new URLSearchParams(window.location.search);

  for (const [key, value] of Object.entries(newParams)) {
    if (value === null || value === '') params.delete(key);
    else params.set(key, String(value));
  }

  const url = new URL(baseUrl, window.location.origin);
  url.search = params.toString();
  return url;
}

function initSortLinks(section: HTMLElement, table: HTMLTableElement, defaultWidths: number[], hiddenKey: string, onStateChange: () => void, onWidthChange: (isDirty: boolean) => void): void {
  section.querySelectorAll<HTMLAnchorElement>('a.table-sort-link').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const url = new URL(link.href);
      window.history.pushState(null, '', url.toString());
      fetchTableData(section, table, defaultWidths, hiddenKey, onStateChange, onWidthChange, url.searchParams);
    });
  });
}

function initPaginationLinks(section: HTMLElement, container: Element, table: HTMLTableElement, defaultWidths: number[], hiddenKey: string, onStateChange: () => void, onWidthChange: (isDirty: boolean) => void): void {
  for (const link of Array.from(container.querySelectorAll<HTMLAnchorElement>('a[href]'))) {
    link.addEventListener('click', e => {
      if (link.inert) return;
      e.preventDefault();
      const url = new URL(link.href);
      window.history.pushState(null, '', url.toString());
      fetchTableData(section, table, defaultWidths, hiddenKey, onStateChange, onWidthChange, url.searchParams);
    });
  }
}

async function fetchTableData(section: HTMLElement, table: HTMLTableElement, defaultWidths: number[], hiddenKey: string, onStateChange: () => void, onWidthChange: (isDirty: boolean) => void, params: URLSearchParams): Promise<void> {
  const api = table.dataset.api;
  if (!api) return;

  const apiUrl = new URL(api, window.location.origin);
  apiUrl.search = params.toString();

  const tbody = table.tBodies[0];
  const paginationRow = section.querySelector<HTMLElement>('.pagination-row');
  const paginationLinks = section.querySelector<HTMLElement>('.table-pagination');

  if (tbody) tbody.style.opacity = '0.4';

  try {
    const res = await fetch(apiUrl.toString());
    if (!res.ok) return;

    const data = await res.json() as { thead: string; tbody: string; pagination: string; info: string; total: number };

    if (table.tHead) {
      table.tHead.innerHTML = data.thead;
      const headers = getHeaders(table);
      const widths = getEffectiveWidths(table, defaultWidths, hiddenKey);
      headers.forEach((th, col) => {
        setCellWidth(th as HTMLElement, Math.max(MIN_COL_WIDTH, widths[col] ?? 100));
        if (col < headers.length - 1) addResizeHandle(table, th, col, defaultWidths, hiddenKey, onStateChange, onWidthChange);
      });
      initSortLinks(section, table, defaultWidths, hiddenKey, onStateChange, onWidthChange);
    }

    if (tbody) {
      tbody.style.opacity = '';
      tbody.innerHTML = data.tbody;
    }

    applyHiddenColumns(table, hiddenKey);

    if (paginationLinks) {
      paginationLinks.innerHTML = data.pagination;
      initPaginationLinks(section, paginationLinks, table, defaultWidths, hiddenKey, onStateChange, onWidthChange);
    }

    if (paginationRow) paginationRow.classList.toggle('hidden', data.total === 0);

    const paginationInfo = paginationRow?.querySelector<HTMLElement>('p');
    if (paginationInfo) paginationInfo.textContent = data.info;

  } catch {
    if (tbody) tbody.style.opacity = '';
  }
}

interface WidthsState {
  hasCustomWidths: boolean;
}

function initTable(table: HTMLTableElement): { defaultWidths: number[]; widthsState: WidthsState } | undefined {
  const container = table.closest('.table-container')?.parentElement;
  if (!container) return undefined;

  const id = table.dataset.tableId ?? table.id ?? 'table';
  const hiddenKey = `${HIDDEN_KEY}-${id}`;
  const defaultWidths = getDefaultWidths(table);
  const resetBtn = container.querySelector<HTMLButtonElement>('.table-reset-btn');
  const widthsState: WidthsState = {hasCustomWidths: getStoredWidths(hiddenKey) !== null};

  const syncState = (): void => {
    syncColToggleState(table, container, hiddenKey);
    syncResetBtnState(table, resetBtn, hiddenKey, widthsState.hasCustomWidths);
  };

  initColToggle(table, container, hiddenKey, syncState);
  initResize(table, defaultWidths, hiddenKey, syncState, isDirty => {
    widthsState.hasCustomWidths = isDirty;
  });
  restoreState(table, container, hiddenKey, defaultWidths);
  initReset(table, container, hiddenKey, defaultWidths, syncState, isDirty => {
    widthsState.hasCustomWidths = isDirty;
  });
  syncState();

  return {defaultWidths, widthsState};
}

function initTableFilters(section: HTMLElement, table: HTMLTableElement, defaultWidths: number[], widthsState: WidthsState): void {
  const baseUrl = window.location.pathname;
  const searchInput = section.querySelector<HTMLInputElement>('.table-search');
  const searchClear = section.querySelector<HTMLElement>('.table-search-clear');
  const perPageSelect = section.querySelector<HTMLSelectElement>('.table-per-page');
  const filtersResetBtn = section.querySelector<HTMLButtonElement>('.filters-reset-btn');
  const filterSelects = Array.from(section.querySelectorAll<HTMLSelectElement>('.table-filter'));
  const filterParams = filterSelects.map(s => s.dataset.filter).filter((p): p is string => !!p);
  const hiddenKey = `${HIDDEN_KEY}-${table.dataset.tableId ?? table.id ?? 'table'}`;
  const resetBtn = section.querySelector<HTMLButtonElement>('.table-reset-btn');

  const syncState = (): void => {
    syncColToggleState(table, section, hiddenKey);
    syncResetBtnState(table, resetBtn, hiddenKey, widthsState.hasCustomWidths);
  };

  const onWidthChange = (isDirty: boolean): void => {
    widthsState.hasCustomWidths = isDirty;
  };

  const syncFiltersResetBtn = (): void => {
    if (!filtersResetBtn) return;
    const params = new URLSearchParams(window.location.search);
    filtersResetBtn.inert = !(params.get('search')?.trim() || filterParams.some(p => params.get(p)));
  };

  const navigate = (newParams: Record<string, string | number | null>): void => {
    const url = buildUrl(baseUrl, newParams);
    window.history.pushState(null, '', url.toString());
    syncFiltersResetBtn();
    fetchTableData(section, table, defaultWidths, hiddenKey, syncState, onWidthChange, url.searchParams);
  };

  section.querySelectorAll<HTMLFormElement>('form.table-tools-form').forEach(form => {
    form.addEventListener('submit', e => e.preventDefault());
  });

  if (searchInput) {
    const syncClear = (): void => {
      if (searchClear) searchClear.inert = !searchInput.value.trim();
    };
    let timeoutId: number | undefined;

    const clearSearch = (): void => {
      if (!searchInput.value.trim()) return;
      if (timeoutId !== undefined) window.clearTimeout(timeoutId);
      searchInput.value = '';
      syncClear();
      navigate({search: null, page: 0});
    };

    if (new URLSearchParams(window.location.search).get('search')?.trim()) {
      requestAnimationFrame(() => {
        searchInput.focus();
        searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
      });
    }

    searchInput.addEventListener('input', () => {
      if (timeoutId !== undefined) window.clearTimeout(timeoutId);
      window.history.replaceState(null, '', buildUrl(baseUrl, {search: searchInput.value || null, page: 0}).toString());
      syncClear();
      syncFiltersResetBtn();
      timeoutId = window.setTimeout(() => navigate({search: searchInput.value || null, page: 0}), 250);
    });

    searchInput.addEventListener('keydown', e => {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      if (timeoutId !== undefined) window.clearTimeout(timeoutId);
      navigate({search: searchInput.value || null, page: 0});
    });

    if (searchClear) {
      searchClear.addEventListener('click', clearSearch);
      searchClear.addEventListener('keydown', e => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        e.preventDefault();
        clearSearch();
      });
    }

    syncClear();
  }

  if (perPageSelect) perPageSelect.addEventListener('change', () => navigate({per_page: perPageSelect.value, page: 0}));

  filterSelects.forEach(select => {
    const param = select.dataset.filter;
    if (param) select.addEventListener('change', () => navigate({[param]: select.value || null, page: 0}));
  });

  if (filtersResetBtn) {
    filtersResetBtn.addEventListener('click', () => {
      if (searchInput) {
        searchInput.value = '';
        if (searchClear) searchClear.inert = true;
      }
      filterSelects.forEach(s => s.value = '');
      const reset: Record<string, string | number | null> = {search: null, page: 0};
      filterParams.forEach(p => reset[p] = null);
      navigate(reset);
    });
  }

  syncFiltersResetBtn();

  initSortLinks(section, table, defaultWidths, hiddenKey, syncState, onWidthChange);

  const paginationLinks = section.querySelector<HTMLElement>('.table-pagination');
  if (paginationLinks) initPaginationLinks(section, paginationLinks, table, defaultWidths, hiddenKey, syncState, onWidthChange);

  window.addEventListener('popstate', () => fetchTableData(section, table, defaultWidths, hiddenKey, syncState, onWidthChange, new URLSearchParams(window.location.search)));
}

export const tableModule = {
  init(): void {
    const defaultWidths = new Map<HTMLTableElement, number[]>();
    const widthsStates = new Map<HTMLTableElement, WidthsState>();

    document.querySelectorAll<HTMLTableElement>('table.data-table').forEach(table => {
      const result = initTable(table);
      if (result) {
        defaultWidths.set(table, result.defaultWidths);
        widthsStates.set(table, result.widthsState);
      }
    });

    document.querySelectorAll<HTMLTableElement>('table.data-table[data-api]').forEach(table => {
      const section = table.closest<HTMLElement>('section');
      if (section) initTableFilters(section, table, defaultWidths.get(table) ?? getDefaultWidths(table), widthsStates.get(table) ?? {hasCustomWidths: false});
    });
  }
};
