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
    table.classList.toggle(`hide-col-${col}`, hidden);
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

    getHeaders(table).forEach((th, col) => addResizeHandle(table, th, col, defaultWidths, hiddenKey, onStateChange, onWidthChange));
}

function restoreState(table: HTMLTableElement, container: Element, hiddenKey: string, defaultWidths: number[]): void {
    for (const col of getHiddenCols(hiddenKey, table)) {
        setColumnHidden(table, col, true);
        const cb = getColToggleCheckbox(container, col);
        if (cb) cb.checked = false;
    }

    defaultWidths.forEach((w, col) => applyColumnWidth(table, col, w));
    syncColToggleState(table, container, hiddenKey);
}

function initReset(table: HTMLTableElement, container: Element, hiddenKey: string, defaultWidths: number[], onStateChange: () => void, onWidthChange: (isDirty: boolean) => void): void {
    const resetBtn = container.querySelector<HTMLButtonElement>('.table-reset-btn');
    if (!resetBtn) return;

    resetBtn.addEventListener('click', () => {
        storage.remove(hiddenKey);
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
    const apiUrl = new URL('/api/users', window.location.origin);
    apiUrl.search = params.toString();

    const tbody = table.tBodies[0];
    const paginationRow = section.querySelector<HTMLElement>('.pagination-row');
    const paginationLinks = section.querySelector<HTMLElement>('.users-pagination');

    if (tbody) tbody.style.opacity = '0.4';

    try {
        const res = await fetch(apiUrl.toString());
        if (!res.ok) return;

        const data = await res.json() as { thead: string; tbody: string; pagination: string; info: string; total: number };

        if (table.tHead) {
            table.tHead.innerHTML = data.thead;
            getHeaders(table).forEach((th, col) => {
                setCellWidth(th as HTMLElement, Math.max(MIN_COL_WIDTH, defaultWidths[col] ?? 100));
                addResizeHandle(table, th, col, defaultWidths, hiddenKey, onStateChange, onWidthChange);
            });
            initSortLinks(section, table, defaultWidths, hiddenKey, onStateChange, onWidthChange);
        }

        if (tbody) {
            tbody.style.opacity = '';
            tbody.innerHTML = data.tbody;
        }

        if (paginationLinks) {
            paginationLinks.innerHTML = data.pagination;
            initPaginationLinks(section, paginationLinks, table, defaultWidths, hiddenKey, onStateChange, onWidthChange);
        }

        const paginationInfo = paginationRow?.querySelector<HTMLElement>('p');
        if (paginationInfo) paginationInfo.textContent = data.info;
        if (paginationRow) paginationRow.style.display = data.total === 0 ? 'none' : '';

    } catch {
        if (tbody) tbody.style.opacity = '';
    }
}

function initTable(table: HTMLTableElement): void {
    const container = table.closest('.table-container')?.parentElement;
    if (!container) return;

    const id = table.dataset.tableId ?? table.id ?? 'table';
    const styleId = `hide-col-styles-${id}`;

    if (!document.getElementById(styleId)) {
        const rules = getHeaders(table).map((_, i) =>
            `table[data-table-id="${id}"].hide-col-${i} th:nth-child(${i + 1}),table[data-table-id="${id}"].hide-col-${i} td:nth-child(${i + 1}){display:none}`
        ).join('');
        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = rules;
        document.head.appendChild(style);
    }

    const hiddenKey = `${HIDDEN_KEY}-${id}`;
    const defaultWidths = getDefaultWidths(table);
    const resetBtn = container.querySelector<HTMLButtonElement>('.table-reset-btn');
    let hasCustomWidths = false;

    const syncState = (): void => {
        syncColToggleState(table, container, hiddenKey);
        syncResetBtnState(table, resetBtn, hiddenKey, hasCustomWidths);
    };

    initColToggle(table, container, hiddenKey, syncState);
    initResize(table, defaultWidths, hiddenKey, syncState, isDirty => {
        hasCustomWidths = isDirty;
    });
    restoreState(table, container, hiddenKey, defaultWidths);
    initReset(table, container, hiddenKey, defaultWidths, syncState, isDirty => {
        hasCustomWidths = isDirty;
    });
    syncState();
}

function initFilters(): void {
    const section = document.querySelector<HTMLElement>('section.users');
    if (!section) return;

    const table = section.querySelector<HTMLTableElement>('table.data-table');
    if (!table) return;

    const baseUrl = window.location.pathname;
    const searchInput = section.querySelector<HTMLInputElement>('#users-search');
    const searchClear = section.querySelector<HTMLElement>('.users-search-clear');
    const perPageSelect = section.querySelector<HTMLSelectElement>('#users-per-page');
    const filtersResetBtn = section.querySelector<HTMLButtonElement>('.filters-reset-btn');
    const hiddenKey = `${HIDDEN_KEY}-${table.dataset.tableId ?? table.id ?? 'table'}`;
    const defaultWidths = getDefaultWidths(table);
    const resetBtn = section.querySelector<HTMLButtonElement>('.table-reset-btn');
    let hasCustomWidths = false;

    const syncState = (): void => {
        syncColToggleState(table, section, hiddenKey);
        syncResetBtnState(table, resetBtn, hiddenKey, hasCustomWidths);
    };

    const onWidthChange = (isDirty: boolean): void => {
        hasCustomWidths = isDirty;
    };

    const navigate = (newParams: Record<string, string | number | null>): void => {
        const url = buildUrl(baseUrl, newParams);
        window.history.pushState(null, '', url.toString());
        fetchTableData(section, table, defaultWidths, hiddenKey, syncState, onWidthChange, url.searchParams);
    };

    section.querySelectorAll<HTMLFormElement>('form.users-search-form, form.users-per-page-form').forEach(form => {
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

    if (filtersResetBtn) {
        filtersResetBtn.inert = !new URLSearchParams(window.location.search).get('search')?.trim();
        filtersResetBtn.addEventListener('click', () => navigate({search: null, page: 0}));
    }

    initSortLinks(section, table, defaultWidths, hiddenKey, syncState, onWidthChange);

    const paginationLinks = section.querySelector<HTMLElement>('.users-pagination');
    if (paginationLinks) initPaginationLinks(section, paginationLinks, table, defaultWidths, hiddenKey, syncState, onWidthChange);

    window.addEventListener('popstate', () => fetchTableData(section, table, defaultWidths, hiddenKey, syncState, onWidthChange, new URLSearchParams(window.location.search)));
}

export const tableModule = {
    init(): void {
        document.querySelectorAll<HTMLTableElement>('table.data-table').forEach(initTable);
        initFilters();
    }
};
