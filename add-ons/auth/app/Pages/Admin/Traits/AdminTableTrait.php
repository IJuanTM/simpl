<?php

declare(strict_types=1);

namespace app\Pages\Admin\Traits;

use app\Controllers\AppController;
use app\Models\Page;
use JsonException;

/**
 * Shared search, filter, sort, pagination and table-rendering logic for admin
 * table pages. A page opts in by calling initTable()/applyPagination(), or
 * opts out by simply not calling them.
 */
trait AdminTableTrait
{
    public int $page = 0;
    public int $maxPage = 0;
    public int $startIndex = 0;
    public int $endIndex = 0;
    public int $total = 0;
    public string $search = '';
    public string $searchDisplay = '';
    public bool $hasActiveFilters = false;
    public array $activeQueryParams = [];
    public array $activeFilterParams = [];
    public ?string $sortColumn = null;
    public ?string $sortDirection = null;
    public string $itemLabel = 'items';

    // Column defs: array{key, label, sortable, width, visible}[]
    public array $tableColumns = [];
    // Active filter values, keyed by param name
    public array $filters = [];
    // Filter param => allowed values ([] means any value is accepted)
    protected array $filterDefinitions = [];

    /**
     * JSON-encoded list of column indexes hidden by default, for data-hidden-cols.
     *
     * @throws JsonException
     */
    public function hiddenColumnsJson(): string
    {
        return json_encode(array_keys(array_filter($this->tableColumns, static fn(array $c): bool => empty($c['visible']))), JSON_THROW_ON_ERROR);
    }

    /**
     * JSON endpoint for the front-end AJAX table (thead/tbody/pagination/info/total).
     *
     * @throws JsonException
     */
    final public function api(): void
    {
        header('Content-Type: application/json');

        echo json_encode([
            'thead' => $this->renderThead(),
            'tbody' => $this->renderTbody(),
            'pagination' => $this->renderPagination(),
            'info' => $this->renderPaginationInfo(),
            'total' => $this->total,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Renders the table header row, with sortable column links where applicable.
     * Called directly from views for the initial render, and from api() for AJAX.
     */
    public function renderThead(): string
    {
        $html = '<tr>';

        foreach ($this->tableColumns as $column) {
            $sortParams = array_merge($this->activeFilterParams, $this->getNextSortParams($column));
            $href = $this->routePath() . ($sortParams ? '?' . http_build_query($sortParams) : '');
            $widthAttr = $column['width'] ? ' data-width="' . $column['width'] . '"' : '';
            $sortClass = !empty($column['sortable']) ? 'sortable ' . $this->getColumnSortClass($column) : '';

            $html .= '<th class="' . $sortClass . '"' . $widthAttr . '>';
            $html .= !empty($column['sortable'])
                ? '<a class="table-sort-link" href="' . $href . '">' . $column['label'] . '</a>'
                : '<p class="table-header-label">' . $column['label'] . '</p>';
            $html .= '</th>';
        }

        return $html . '</tr>';
    }

    /**
     * Query params for clicking this column header: toggles asc -> desc -> unsorted.
     *
     * @param array<string, mixed> $column
     *
     * @return array<string, mixed>
     */
    private function getNextSortParams(array $column): array
    {
        if (empty($column['sortable'])) return [];
        if ($this->sortColumn !== $column['key']) return ['sort' => $column['key'], 'dir' => 'asc'];
        if ($this->sortDirection === 'asc') return ['sort' => $column['key'], 'dir' => 'desc'];
        return [];
    }

    /**
     * Base admin route path (e.g. '/admin/users'), used for pagination/sort-link hrefs.
     */
    abstract private function routePath(): string;

    /**
     * CSS sort-direction class for the given column, or '' if not the active sort column.
     */
    private function getColumnSortClass(array $column): string
    {
        if (empty($column['sortable']) || $this->sortColumn !== $column['key']) return '';
        return $this->sortDirection === 'asc' ? 'sort-asc' : 'sort-desc';
    }

    /**
     * Row renderer: renderCell() per column, 'actions' column via renderActionsCell().
     */
    public function renderTbody(): string
    {
        $rows = $this->pageRows();
        if (!$rows) return $this->renderEmptyRow();

        $html = '';

        foreach ($rows as $row) {
            $class = $this->rowClass($row);
            $html .= '<tr' . ($class ? ' class="' . $class . '"' : '') . '>';

            foreach ($this->tableColumns as $column) {
                $html .= $column['key'] === 'actions' ? $this->renderActionsCell($row) : '<td>' . $this->renderCell($column, $row) . '</td>';
            }

            $html .= '</tr>';
        }

        return $html;
    }

    /**
     * Row data for the current page, used by renderTbody().
     */
    abstract private function pageRows(): array;

    /**
     * Renders a single row spanning every column, shown instead of the normal rows
     * when there's nothing to display (no records, or none matching the active
     * search/filters).
     */
    private function renderEmptyRow(): string
    {
        $message = $this->hasActiveFilters
            ? "No $this->itemLabel match your search or filters."
            : "No $this->itemLabel found.";

        return '<tr class="table-empty-row text-center"><td colspan="' . count($this->tableColumns) . '">' . $message . '</td></tr>';
    }

    /**
     * CSS class for a row's <tr>, or '' for none. Override to flag row states.
     *
     * @param array<string, mixed> $row
     */
    private function rowClass(array $row): string
    {
        return '';
    }

    /**
     * Renders the <td> for the 'actions' column. Override to add action buttons.
     *
     * @param array<string, mixed> $row
     */
    private function renderActionsCell(array $row): string
    {
        return '<td>' . $this->renderCell(['key' => 'actions'], $row) . '</td>';
    }

    /**
     * Renders a single cell's content for the given column and row.
     *
     * @param array<string, mixed> $column
     * @param array<string, mixed> $row
     */
    abstract public function renderCell(array $column, array $row): string;

    /**
     * Renders previous/next pagination links with active query params preserved.
     */
    private function renderPagination(): string
    {
        $cls = 'class="col link lh-1 g-col-0.5 center f-0"';
        $prev = $this->routePath() . '?' . http_build_query(['page' => $this->page - 1] + $this->activeQueryParams);
        $next = $this->routePath() . '?' . http_build_query(['page' => $this->page + 1] + $this->activeQueryParams);

        return '<a ' . $cls . ' href="' . $prev . '" ' . ($this->page > 0 ? '' : 'inert') . '><i class="fas fa-chevron-left"></i>Previous</a>'
            . '<a ' . $cls . ' href="' . $next . '" ' . ($this->page < $this->maxPage ? '' : 'inert') . '>Next<i class="fas fa-chevron-right"></i></a>';
    }

    /**
     * Renders the "X - Y of Z <items>" info string for the current page.
     */
    private function renderPaginationInfo(): string
    {
        return $this->startIndex . ' - ' . $this->endIndex . ' of ' . $this->total . ' ' . $this->itemLabel;
    }

    /**
     * Reads search/filter/sort/pagination params and derived state. Call once
     * from the constructor, after $tableColumns/$filterDefinitions are set.
     */
    private function initTable(Page $page): void
    {
        // Captured before the URL can override it, so we know the page's real default.
        $defaultPerPage = $this->perPage;

        $this->readTableParams($page);
        $this->readFilters($page);
        $this->resolveSort($page->params);

        $this->searchDisplay = AppController::sanitize($this->search);
        $this->hasActiveFilters = $this->search !== '' || array_filter($this->filters) !== [];

        $this->activeFilterParams = array_filter(
            array_merge(
                ['search' => $this->search, 'per_page' => $this->perPage !== $defaultPerPage ? $this->perPage : null],
                $this->filters
            ),
            static fn(mixed $v): bool => $v !== '' && $v !== null
        );
        $this->activeQueryParams = ($this->sortColumn ? ['sort' => $this->sortColumn, 'dir' => $this->sortDirection] : []) + $this->activeFilterParams;
    }

    /**
     * Reads page/per_page/search query params into their properties.
     */
    private function readTableParams(Page $page): void
    {
        if ($page->param('page') !== null) $this->page = max(0, (int)$page->param('page'));
        if ($page->param('per_page') !== null && in_array((int)$page->param('per_page'), $this->perPageOptions, true)) $this->perPage = (int)$page->param('per_page');
        if ($page->param('search') !== null) $this->search = trim((string)$page->param('search'));
    }

    /**
     * Reads each $filterDefinitions param into $filters, validating allowed values.
     */
    private function readFilters(Page $page): void
    {
        foreach ($this->filterDefinitions as $param => $allowedValues) {
            $this->filters[$param] = '';

            $value = $page->param($param);
            if ($value === null) continue;

            $value = trim((string)$value);
            if ($allowedValues && !in_array($value, $allowedValues, true)) continue;

            $this->filters[$param] = $value;
        }
    }

    /**
     * Reads sort params from the request, falling back to whatever
     * $sortColumn/$sortDirection were pre-set to (a page's default sort, or null).
     */
    private function resolveSort(array $params): void
    {
        $sortable = array_column(array_filter($this->tableColumns, static fn(array $c): bool => !empty($c['sortable'])), 'key');
        if (!isset($params['sort']) || !in_array($params['sort'], $sortable, true)) return;

        $this->sortColumn = $params['sort'];
        $dir = isset($params['dir']) ? strtolower((string)$params['dir']) : 'asc';
        $this->sortDirection = in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc';
    }

    /**
     * Clamps $page, computes maxPage/startIndex/endIndex, returns the LIMIT/OFFSET offset.
     */
    private function applyPagination(int $total): int
    {
        $this->total = $total;
        $this->maxPage = $total > 0 ? (int)ceil($total / $this->perPage) - 1 : 0;
        $this->page = max(0, min($this->page, $this->maxPage));
        $offset = $this->page * $this->perPage;
        $this->startIndex = $total > 0 ? $offset + 1 : 0;
        $this->endIndex = min($offset + $this->perPage, $total);
        return $offset;
    }
}
