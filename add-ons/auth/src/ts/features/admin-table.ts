import {initTable, type TableHandle} from './table.ts';

// Aborts a still-in-flight request for the same table instead of just letting it complete and discarding the result.
const abortControllers = new WeakMap<HTMLTableElement, AbortController>();

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

function bindNavLinks(links: NodeListOf<HTMLAnchorElement>, load: (params: URLSearchParams) => void): void {
  links.forEach(link => {
    link.addEventListener('click', e => {
      if (link.inert) return;
      e.preventDefault();
      const url = new URL(link.href);
      window.history.pushState(null, '', url.toString());
      load(url.searchParams);
    });
  });
}

async function fetchTableData(section: HTMLElement, table: HTMLTableElement, handle: TableHandle, params: URLSearchParams, load: (params: URLSearchParams) => void): Promise<void> {
  const api = table.dataset.api;
  if (!api) return;

  abortControllers.get(table)?.abort();
  const controller = new AbortController();
  abortControllers.set(table, controller);

  const apiUrl = new URL(api, window.location.origin);
  apiUrl.search = params.toString();

  const tbody = table.tBodies[0];
  const paginationRow = section.querySelector<HTMLElement>('.pagination-row');
  const paginationLinks = section.querySelector<HTMLElement>('.table-pagination');

  if (tbody) tbody.classList.add('loading');

  try {
    const res = await fetch(apiUrl.toString(), {signal: controller.signal});
    if (!res.ok) {
      tbody?.classList.remove('loading');
      return;
    }

    const data = await res.json() as { thead: string; tbody: string; pagination: string; info: string; total: number };

    if (table.tHead) {
      table.tHead.innerHTML = data.thead;
      bindNavLinks(table.tHead.querySelectorAll<HTMLAnchorElement>('a.table-sort-link'), load);
    }

    if (tbody) {
      tbody.classList.remove('loading');
      tbody.innerHTML = data.tbody;
    }

    handle.refresh();

    if (paginationLinks) {
      paginationLinks.innerHTML = data.pagination;
      bindNavLinks(paginationLinks.querySelectorAll<HTMLAnchorElement>('a[href]'), load);
    }

    if (paginationRow) paginationRow.classList.toggle('hidden', data.total === 0);

    const paginationInfo = paginationRow?.querySelector<HTMLElement>('p');
    if (paginationInfo) paginationInfo.textContent = data.info;
  } catch (error) {
    // A newer request for this table aborted this one; the newer request owns the loading state now.
    if (!(error instanceof DOMException && error.name === 'AbortError')) tbody?.classList.remove('loading');
  }
}

function initAdminTable(section: HTMLElement, table: HTMLTableElement): void {
  const handle = initTable(table);
  const baseUrl = window.location.pathname;
  const searchInput = section.querySelector<HTMLInputElement>('.table-search');
  const searchClear = section.querySelector<HTMLElement>('.table-search-clear');
  const perPageSelect = section.querySelector<HTMLSelectElement>('.table-per-page');
  const filtersResetBtn = section.querySelector<HTMLButtonElement>('.filters-reset-btn');
  const filterSelects = Array.from(section.querySelectorAll<HTMLSelectElement>('.table-filter'));
  const filterParams = filterSelects.map(s => s.dataset.filter).filter((p): p is string => !!p);

  const load = (params: URLSearchParams): void => {
    void fetchTableData(section, table, handle, params, load);
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
    load(url.searchParams);
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

  if (table.tHead) bindNavLinks(table.tHead.querySelectorAll<HTMLAnchorElement>('a.table-sort-link'), load);

  const paginationLinks = section.querySelector<HTMLElement>('.table-pagination');
  if (paginationLinks) bindNavLinks(paginationLinks.querySelectorAll<HTMLAnchorElement>('a[href]'), load);

  window.addEventListener('popstate', () => load(new URLSearchParams(window.location.search)));
}

export const adminTableModule = {
  init(): void {
    document.querySelectorAll<HTMLTableElement>('table.data-table[data-api]').forEach(table => {
      const section = table.closest<HTMLElement>('section');
      if (section) initAdminTable(section, table);
    });
  }
};
