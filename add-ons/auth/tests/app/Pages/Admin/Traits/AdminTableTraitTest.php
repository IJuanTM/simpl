<?php

declare(strict_types=1);

namespace tests\Pages\Admin\Traits;

use app\Models\Page;
use app\Pages\Admin\Traits\AdminTableTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

final class AdminTableTraitHost
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

final class AdminTableTraitTest extends TestCase
{
    public function testBuildColumnsMapsPositionalTuplesToNamedKeys(): void
    {
        // Act
        $columns = $this->call(new AdminTableTraitHost(), 'buildColumns', [[
            ['id', 'ID', true, 60, false],
            ['name', 'Name', false, null, true],
        ]]);

        // Assert
        $this->assertSame([
            ['key' => 'id', 'label' => 'ID', 'sortable' => true, 'width' => 60, 'visible' => false],
            ['key' => 'name', 'label' => 'Name', 'sortable' => false, 'width' => null, 'visible' => true],
        ], $columns);
    }

    private function call(AdminTableTraitHost $host, string $method, array $args = []): mixed
    {
        return (new ReflectionMethod(AdminTableTraitHost::class, $method))->invoke($host, ...$args);
    }

    public function testHiddenColumnsJsonListsThePositionalIndexesOfHiddenColumns(): void
    {
        // Arrange
        // Returns array positions (for data-hidden-cols), not the columns' 'key' values.
        $host = new AdminTableTraitHost();
        $host->tableColumns = [
            ['key' => 'id', 'visible' => true],
            ['key' => 'internal_note', 'visible' => false],
        ];

        // Act + Assert
        $this->assertSame('[1]', $host->hiddenColumnsJson());
    }

    public function testRenderTheadLinksSortableColumnsAndPlainLabelsOtherwise(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $host->tableColumns = [
            ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'width' => 120],
            ['key' => 'actions', 'label' => 'Actions', 'sortable' => false, 'width' => null],
        ];

        // Act
        $html = $host->renderThead();

        // Assert
        $this->assertStringContainsString('<a class="table-sort-link" href="/admin/test?sort=name&dir=asc">Name</a>', $html);
        $this->assertStringContainsString('data-width="120"', $html);
        $this->assertStringContainsString('<p class="table-header-label">Actions</p>', $html);
        $this->assertStringNotContainsString('sort=actions', $html);
    }

    public function testGetNextSortParamsStartsAscendingForAnUnsortedColumn(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();

        // Act
        $params = $this->call($host, 'getNextSortParams', [['key' => 'name', 'sortable' => true]]);

        // Assert
        $this->assertSame(['sort' => 'name', 'dir' => 'asc'], $params);
    }

    public function testGetNextSortParamsTogglesFromAscToDesc(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $host->sortColumn = 'name';
        $host->sortDirection = 'asc';

        // Act
        $params = $this->call($host, 'getNextSortParams', [['key' => 'name', 'sortable' => true]]);

        // Assert
        $this->assertSame(['sort' => 'name', 'dir' => 'desc'], $params);
    }

    public function testGetNextSortParamsClearsSortFromDesc(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $host->sortColumn = 'name';
        $host->sortDirection = 'desc';

        // Act
        $params = $this->call($host, 'getNextSortParams', [['key' => 'name', 'sortable' => true]]);

        // Assert
        $this->assertSame([], $params);
    }

    public function testGetNextSortParamsIgnoresANonSortableColumn(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();

        // Act
        $params = $this->call($host, 'getNextSortParams', [['key' => 'name', 'sortable' => false]]);

        // Assert
        $this->assertSame([], $params);
    }

    public function testGetColumnSortClassForTheActiveAscendingColumn(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $host->sortColumn = 'name';
        $host->sortDirection = 'asc';

        // Act + Assert
        $this->assertSame('sort-asc', $this->call($host, 'getColumnSortClass', [['key' => 'name', 'sortable' => true]]));
    }

    public function testGetColumnSortClassForAnInactiveColumn(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $host->sortColumn = 'name';
        $host->sortDirection = 'asc';

        // Act + Assert
        $this->assertSame('', $this->call($host, 'getColumnSortClass', [['key' => 'email', 'sortable' => true]]));
    }

    public function testRenderTbodyShowsTheEmptyRowWhenThereAreNoRows(): void
    {
        // Arrange
        $host = new AdminTableTraitHost([]);
        $host->tableColumns = [['key' => 'name']];
        $host->itemLabel = 'users';

        // Act + Assert
        $this->assertStringContainsString('No users found.', $host->renderTbody());
    }

    public function testRenderTbodyShowsTheFilteredEmptyMessageWhenFiltersAreActive(): void
    {
        // Arrange
        $host = new AdminTableTraitHost([]);
        $host->tableColumns = [['key' => 'name']];
        $host->itemLabel = 'users';
        $host->hasActiveFilters = true;

        // Act + Assert
        $this->assertStringContainsString('No users match your search or filters.', $host->renderTbody());
    }

    public function testRenderTbodyRendersACellPerColumn(): void
    {
        // Arrange
        $host = new AdminTableTraitHost([['name' => 'Alice']]);
        $host->tableColumns = [['key' => 'name']];

        // Act + Assert
        $this->assertSame('<tr><td>Alice</td></tr>', $host->renderTbody());
    }

    public function testRenderPaginationMarksPreviousInertOnTheFirstPage(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $host->page = 0;
        $host->maxPage = 2;

        // Act
        $html = $host->renderPagination();

        // Assert
        $this->assertMatchesRegularExpression('/href="[^"]*page=-1[^"]*" inert>/', $html);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*page=1[^"]*" inert>/', $html);
    }

    public function testRenderPaginationMarksNextInertOnTheLastPage(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $host->page = 2;
        $host->maxPage = 2;

        // Act
        $html = $host->renderPagination();

        // Assert
        $this->assertMatchesRegularExpression('/href="[^"]*page=3[^"]*" inert>/', $html);
    }

    public function testRenderPaginationInfoFormatsTheRange(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $host->startIndex = 1;
        $host->endIndex = 10;
        $host->total = 42;
        $host->itemLabel = 'users';

        // Act + Assert
        $this->assertSame('1 - 10 of 42 users', $host->renderPaginationInfo());
    }

    public function testBlockIfRunsTheCallbackAndReturnsTrueWhenBlocked(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $ran = false;

        // Act
        $blocked = $this->call($host, 'blockIf', [true, function () use (&$ran): void {
            $ran = true;
        }]);

        // Assert
        $this->assertTrue($blocked);
        $this->assertTrue($ran);
    }

    public function testBlockIfSkipsTheCallbackAndReturnsFalseWhenNotBlocked(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $ran = false;

        // Act
        $blocked = $this->call($host, 'blockIf', [false, function () use (&$ran): void {
            $ran = true;
        }]);

        // Assert
        $this->assertFalse($blocked);
        $this->assertFalse($ran);
    }

    public function testRenderBadgeUsesTheSuccessOrErrorClass(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();

        // Act + Assert
        $this->assertSame('<span class="badge badge-success">Yes</span>', $this->call($host, 'renderBadge', [true, 'Yes']));
        $this->assertSame('<span class="badge badge-error">No</span>', $this->call($host, 'renderBadge', [false, 'No']));
    }

    public function testInitTableComposesSearchFiltersAndSortIntoTheQueryParams(): void
    {
        // Arrange
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

        // Act
        $this->call($host, 'initTable', [$page]);

        // Assert
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

    private function setFilterDefinitions(AdminTableTraitHost $host, array $definitions): void
    {
        (new ReflectionProperty(AdminTableTraitHost::class, 'filterDefinitions'))->setValue($host, $definitions);
    }

    public function testInitTableOmitsPerPageFromActiveParamsWhenItMatchesTheDefault(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $page = new Page('admin', ['test'], ['search' => 'ann']);

        // Act
        $this->call($host, 'initTable', [$page]);

        // Assert
        $this->assertArrayNotHasKey('per_page', $host->activeFilterParams);
    }

    public function testReadTableParamsReadsPageAndSearch(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $page = new Page('admin', ['test'], ['page' => '2', 'search' => '  foo  ']);

        // Act
        $this->call($host, 'readTableParams', [$page]);

        // Assert
        $this->assertSame(2, $host->page);
        $this->assertSame('foo', $host->search);
    }

    public function testReadTableParamsIgnoresAPerPageValueNotInTheAllowList(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $page = new Page('admin', ['test'], ['per_page' => '999']);

        // Act
        $this->call($host, 'readTableParams', [$page]);

        // Assert
        $this->assertSame(10, $host->perPage);
    }

    public function testReadTableParamsAcceptsAnAllowedPerPageValue(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $page = new Page('admin', ['test'], ['per_page' => '25']);

        // Act
        $this->call($host, 'readTableParams', [$page]);

        // Assert
        $this->assertSame(25, $host->perPage);
    }

    public function testReadFiltersRejectsAValueNotInTheAllowedList(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $this->setFilterDefinitions($host, ['status' => ['active', 'inactive']]);
        $page = new Page('admin', ['test'], ['status' => 'bogus']);

        // Act
        $this->call($host, 'readFilters', [$page]);

        // Assert
        $this->assertSame('', $host->filters['status']);
    }

    public function testReadFiltersAcceptsAnyValueWhenNoAllowListIsDefined(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $this->setFilterDefinitions($host, ['role' => []]);
        $page = new Page('admin', ['test'], ['role' => 'admin']);

        // Act
        $this->call($host, 'readFilters', [$page]);

        // Assert
        $this->assertSame('admin', $host->filters['role']);
    }

    public function testReadFiltersLeavesAMissingParamAsEmpty(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $this->setFilterDefinitions($host, ['role' => []]);
        $page = new Page('admin', ['test'], []);

        // Act
        $this->call($host, 'readFilters', [$page]);

        // Assert
        $this->assertSame('', $host->filters['role']);
    }

    public function testResolveSortSetsColumnAndNormalizesDirection(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $host->tableColumns = [['key' => 'name', 'sortable' => true]];

        // Act
        $this->call($host, 'resolveSort', [['sort' => 'name', 'dir' => 'DESC']]);

        // Assert
        $this->assertSame('name', $host->sortColumn);
        $this->assertSame('desc', $host->sortDirection);
    }

    public function testResolveSortDefaultsToAscendingWhenDirIsInvalid(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $host->tableColumns = [['key' => 'name', 'sortable' => true]];

        // Act
        $this->call($host, 'resolveSort', [['sort' => 'name', 'dir' => 'sideways']]);

        // Assert
        $this->assertSame('asc', $host->sortDirection);
    }

    public function testResolveSortIgnoresANonSortableColumn(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $host->tableColumns = [['key' => 'name', 'sortable' => false]];

        // Act
        $this->call($host, 'resolveSort', [['sort' => 'name', 'dir' => 'asc']]);

        // Assert
        $this->assertNull($host->sortColumn);
    }

    public function testApplyPaginationOnAFullPage(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();

        // Act
        $offset = $this->call($host, 'applyPagination', [25]);

        // Assert
        $this->assertSame(0, $offset);
        $this->assertSame(2, $host->maxPage);
        $this->assertSame(1, $host->startIndex);
        $this->assertSame(10, $host->endIndex);
    }

    public function testApplyPaginationClampsAnOutOfRangePage(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();
        $host->page = 99;

        // Act
        $offset = $this->call($host, 'applyPagination', [25]);

        // Assert
        $this->assertSame(2, $host->page);
        $this->assertSame(20, $offset);
        $this->assertSame(25, $host->endIndex);
    }

    public function testApplyPaginationWithNoRows(): void
    {
        // Arrange
        $host = new AdminTableTraitHost();

        // Act
        $this->call($host, 'applyPagination', [0]);

        // Assert
        $this->assertSame(0, $host->maxPage);
        $this->assertSame(0, $host->startIndex);
        $this->assertSame(0, $host->endIndex);
    }

    protected function setUp(): void
    {
        $_SESSION = [];
    }
}
