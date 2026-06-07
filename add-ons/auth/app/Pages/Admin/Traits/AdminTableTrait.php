<?php

declare(strict_types=1);

namespace app\Pages\Admin\Traits;

use app\Models\Page;

/**
 * Shared pagination and query-param reading for admin table pages.
 *
 * Using classes must declare $perPage (int) and $perPageOptions (array) as
 * public properties with their own defaults before calling readTableParams().
 */
trait AdminTableTrait
{
    public int $page = 0;
    public int $maxPage = 0;
    public int $startIndex = 0;
    public int $endIndex = 0;
    public string $search = '';
    public array $activeQueryParams = [];

    private function readTableParams(Page $page): void
    {
        if ($page->param('page') !== null) $this->page = max(0, (int)$page->param('page'));
        if ($page->param('per_page') !== null && in_array((int)$page->param('per_page'), $this->perPageOptions, true)) $this->perPage = (int)$page->param('per_page');
        if ($page->param('search') !== null) $this->search = trim((string)$page->param('search'));
    }

    /**
     * Clamps $page, then computes maxPage, startIndex, endIndex.
     * Returns the row offset for use in LIMIT/OFFSET queries.
     */
    private function applyPagination(int $total): int
    {
        $this->maxPage = $total > 0 ? (int)ceil($total / $this->perPage) - 1 : 0;
        $this->page = max(0, min($this->page, $this->maxPage));
        $offset = $this->page * $this->perPage;
        $this->startIndex = $total > 0 ? $offset + 1 : 0;
        $this->endIndex = min($offset + $this->perPage, $total);
        return $offset;
    }
}
