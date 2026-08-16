<?php

declare(strict_types=1);

namespace tests\Pages\Admin\Traits;

use app\Models\Page;
use app\Pages\Admin\Traits\AdminTableTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

class AdminTableTraitHost
{
    use AdminTableTrait;

    public int $perPage = 10;
    public array $perPageOptions = [10, 25, 50];

    public function __construct(private array $rows = [])
    {
    }

    public function renderCell(array $column, array $row): string
    {
        return (string)($row[$column['key']] ?? '');
    }

    private function routePath(): string
    {
        return '/admin/test';
    }

    private function pageRows(): array
    {
        return $this->rows;
    }
}

class AdminTableTraitTest extends TestCase
{
    public function testApplyPaginationOnAFullPage(): void
    {
        $host = new AdminTableTraitHost();

        $offset = $this->call($host, 'applyPagination', [25]);

        $this->assertSame(0, $offset);
        $this->assertSame(2, $host->maxPage);
        $this->assertSame(1, $host->startIndex);
        $this->assertSame(10, $host->endIndex);
    }

    private function call(AdminTableTraitHost $host, string $method, array $args = []): mixed
    {
        return (new ReflectionMethod(AdminTableTraitHost::class, $method))->invoke($host, ...$args);
    }

    public function testApplyPaginationClampsAnOutOfRangePage(): void
    {
        $host = new AdminTableTraitHost();
        $host->page = 99;

        $offset = $this->call($host, 'applyPagination', [25]);

        $this->assertSame(2, $host->page);
        $this->assertSame(20, $offset);
        $this->assertSame(25, $host->endIndex);
    }

    public function testApplyPaginationWithNoRows(): void
    {
        $host = new AdminTableTraitHost();

        $this->call($host, 'applyPagination', [0]);

        $this->assertSame(0, $host->maxPage);
        $this->assertSame(0, $host->startIndex);
        $this->assertSame(0, $host->endIndex);
    }

    public function testReadTableParamsReadsPageAndSearch(): void
    {
        $host = new AdminTableTraitHost();
        $page = new Page('admin', ['test'], ['page' => '2', 'search' => '  foo  ']);

        $this->call($host, 'readTableParams', [$page]);

        $this->assertSame(2, $host->page);
        $this->assertSame('foo', $host->search);
    }

    public function testReadTableParamsIgnoresAPerPageValueNotInTheAllowList(): void
    {
        $host = new AdminTableTraitHost();
        $page = new Page('admin', ['test'], ['per_page' => '999']);

        $this->call($host, 'readTableParams', [$page]);

        $this->assertSame(10, $host->perPage);
    }

    public function testReadTableParamsAcceptsAnAllowedPerPageValue(): void
    {
        $host = new AdminTableTraitHost();
        $page = new Page('admin', ['test'], ['per_page' => '25']);

        $this->call($host, 'readTableParams', [$page]);

        $this->assertSame(25, $host->perPage);
    }

    public function testReadFiltersRejectsAValueNotInTheAllowedList(): void
    {
        $host = new AdminTableTraitHost();
        $this->setFilterDefinitions($host, ['status' => ['active', 'inactive']]);
        $page = new Page('admin', ['test'], ['status' => 'bogus']);

        $this->call($host, 'readFilters', [$page]);

        $this->assertSame('', $host->filters['status']);
    }

    private function setFilterDefinitions(AdminTableTraitHost $host, array $definitions): void
    {
        (new ReflectionProperty(AdminTableTraitHost::class, 'filterDefinitions'))->setValue($host, $definitions);
    }

    public function testReadFiltersAcceptsAnyValueWhenNoAllowListIsDefined(): void
    {
        $host = new AdminTableTraitHost();
        $this->setFilterDefinitions($host, ['role' => []]);
        $page = new Page('admin', ['test'], ['role' => 'admin']);

        $this->call($host, 'readFilters', [$page]);

        $this->assertSame('admin', $host->filters['role']);
    }

    public function testReadFiltersLeavesAMissingParamAsEmpty(): void
    {
        $host = new AdminTableTraitHost();
        $this->setFilterDefinitions($host, ['role' => []]);
        $page = new Page('admin', ['test'], []);

        $this->call($host, 'readFilters', [$page]);

        $this->assertSame('', $host->filters['role']);
    }

    public function testResolveSortSetsColumnAndNormalizesDirection(): void
    {
        $host = new AdminTableTraitHost();
        $host->tableColumns = [['key' => 'name', 'sortable' => true]];

        $this->call($host, 'resolveSort', [['sort' => 'name', 'dir' => 'DESC']]);

        $this->assertSame('name', $host->sortColumn);
        $this->assertSame('desc', $host->sortDirection);
    }

    public function testResolveSortDefaultsToAscendingWhenDirIsInvalid(): void
    {
        $host = new AdminTableTraitHost();
        $host->tableColumns = [['key' => 'name', 'sortable' => true]];

        $this->call($host, 'resolveSort', [['sort' => 'name', 'dir' => 'sideways']]);

        $this->assertSame('asc', $host->sortDirection);
    }

    public function testResolveSortIgnoresANonSortableColumn(): void
    {
        $host = new AdminTableTraitHost();
        $host->tableColumns = [['key' => 'name', 'sortable' => false]];

        $this->call($host, 'resolveSort', [['sort' => 'name', 'dir' => 'asc']]);

        $this->assertNull($host->sortColumn);
    }

    public function testGetNextSortParamsStartsAscendingForAnUnsortedColumn(): void
    {
        $host = new AdminTableTraitHost();

        $params = $this->call($host, 'getNextSortParams', [['key' => 'name', 'sortable' => true]]);

        $this->assertSame(['sort' => 'name', 'dir' => 'asc'], $params);
    }

    public function testGetNextSortParamsTogglesFromAscToDesc(): void
    {
        $host = new AdminTableTraitHost();
        $host->sortColumn = 'name';
        $host->sortDirection = 'asc';

        $params = $this->call($host, 'getNextSortParams', [['key' => 'name', 'sortable' => true]]);

        $this->assertSame(['sort' => 'name', 'dir' => 'desc'], $params);
    }

    public function testGetNextSortParamsClearsSortFromDesc(): void
    {
        $host = new AdminTableTraitHost();
        $host->sortColumn = 'name';
        $host->sortDirection = 'desc';

        $params = $this->call($host, 'getNextSortParams', [['key' => 'name', 'sortable' => true]]);

        $this->assertSame([], $params);
    }

    public function testGetNextSortParamsIgnoresANonSortableColumn(): void
    {
        $host = new AdminTableTraitHost();

        $params = $this->call($host, 'getNextSortParams', [['key' => 'name', 'sortable' => false]]);

        $this->assertSame([], $params);
    }

    public function testGetColumnSortClassForTheActiveAscendingColumn(): void
    {
        $host = new AdminTableTraitHost();
        $host->sortColumn = 'name';
        $host->sortDirection = 'asc';

        $this->assertSame('sort-asc', $this->call($host, 'getColumnSortClass', [['key' => 'name', 'sortable' => true]]));
    }

    public function testGetColumnSortClassForAnInactiveColumn(): void
    {
        $host = new AdminTableTraitHost();
        $host->sortColumn = 'name';
        $host->sortDirection = 'asc';

        $this->assertSame('', $this->call($host, 'getColumnSortClass', [['key' => 'email', 'sortable' => true]]));
    }

    public function testHiddenColumnsJsonListsThePositionalIndexesOfHiddenColumns(): void
    {
        // Returns array positions (for data-hidden-cols), not the columns' 'key' values.
        $host = new AdminTableTraitHost();
        $host->tableColumns = [
            ['key' => 'id', 'visible' => true],
            ['key' => 'internal_note', 'visible' => false],
        ];

        $this->assertSame('[1]', $host->hiddenColumnsJson());
    }

    public function testRenderPaginationInfoFormatsTheRange(): void
    {
        $host = new AdminTableTraitHost();
        $host->startIndex = 1;
        $host->endIndex = 10;
        $host->total = 42;
        $host->itemLabel = 'users';

        $this->assertSame('1 - 10 of 42 users', $host->renderPaginationInfo());
    }

    public function testRenderPaginationMarksPreviousInertOnTheFirstPage(): void
    {
        $host = new AdminTableTraitHost();
        $host->page = 0;
        $host->maxPage = 2;

        $html = $host->renderPagination();

        $this->assertMatchesRegularExpression('/href="[^"]*page=-1[^"]*" inert>/', $html);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*page=1[^"]*" inert>/', $html);
    }

    public function testRenderPaginationMarksNextInertOnTheLastPage(): void
    {
        $host = new AdminTableTraitHost();
        $host->page = 2;
        $host->maxPage = 2;

        $html = $host->renderPagination();

        $this->assertMatchesRegularExpression('/href="[^"]*page=3[^"]*" inert>/', $html);
    }

    public function testRenderTbodyShowsTheEmptyRowWhenThereAreNoRows(): void
    {
        $host = new AdminTableTraitHost([]);
        $host->tableColumns = [['key' => 'name']];
        $host->itemLabel = 'users';

        $this->assertStringContainsString('No users found.', $host->renderTbody());
    }

    public function testRenderTbodyShowsTheFilteredEmptyMessageWhenFiltersAreActive(): void
    {
        $host = new AdminTableTraitHost([]);
        $host->tableColumns = [['key' => 'name']];
        $host->itemLabel = 'users';
        $host->hasActiveFilters = true;

        $this->assertStringContainsString('No users match your search or filters.', $host->renderTbody());
    }

    public function testRenderTbodyRendersACellPerColumn(): void
    {
        $host = new AdminTableTraitHost([['name' => 'Alice']]);
        $host->tableColumns = [['key' => 'name']];

        $this->assertSame('<tr><td>Alice</td></tr>', $host->renderTbody());
    }

    public function testInitTableComposesSearchFiltersAndSortIntoTheQueryParams(): void
    {
        $host = new AdminTableTraitHost();
        $host->tableColumns = [['key' => 'name', 'sortable' => true]];
        $this->setFilterDefinitions($host, ['status' => []]);
        $page = new Page('admin', ['test'], [
            'search' => 'ann',
            'per_page' => '25',
            'status' => 'active',
            'sort' => 'name',
            'dir' => 'desc',
        ]);

        $this->call($host, 'initTable', [$page]);

        $this->assertSame('ann', $host->search);
        $this->assertTrue($host->hasActiveFilters);
        $this->assertSame(
            ['search' => 'ann', 'per_page' => 25, 'status' => 'active'],
            $host->activeFilterParams
        );
        $this->assertSame(
            ['sort' => 'name', 'dir' => 'desc', 'search' => 'ann', 'per_page' => 25, 'status' => 'active'],
            $host->activeQueryParams
        );
    }

    public function testInitTableOmitsPerPageFromActiveParamsWhenItMatchesTheDefault(): void
    {
        $host = new AdminTableTraitHost();
        $page = new Page('admin', ['test'], ['search' => 'ann']);

        $this->call($host, 'initTable', [$page]);

        $this->assertArrayNotHasKey('per_page', $host->activeFilterParams);
    }

    protected function setUp(): void
    {
        $_SESSION = [];
    }
}
