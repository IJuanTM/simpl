<?php

declare(strict_types=1);

namespace tests\Feature\Rendering;

use app\Controllers\PageController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use tests\Support\OutputCaptureTrait;

final class TableComponentTest extends TestCase
{
    use OutputCaptureTrait;

    public function testTableListsThePositionalIndexesOfHiddenColumns(): void
    {
        // Act
        $html = $this->render('table/table', ['tableId' => 'people', 'columns' => [['label' => 'Name'], ['label' => 'Note', 'visible' => false]]]);

        // Assert
        $this->assertStringContainsString('data-hidden-cols="[1]"', $html);
    }

    private function render(string $component, array $props): string
    {
        return $this->captured(static fn() => new ReflectionClass(PageController::class)->newInstanceWithoutConstructor()->component($component, $props));
    }

    public function testTableBuildsEscapedHeadersWithWidthsFromColumns(): void
    {
        // Act
        $html = $this->render('table/table', ['tableId' => 'people', 'columns' => [['label' => '<b>Name</b>', 'width' => 120]]]);

        // Assert
        $this->assertStringContainsString('&lt;b&gt;Name&lt;/b&gt;', $html);
        $this->assertStringContainsString('data-width="120"', $html);
    }

    public function testTableRendersRowCellsAsGiven(): void
    {
        // Act
        $html = $this->render('table/table', ['tableId' => 'people', 'columns' => [['label' => 'Name']], 'rows' => [['<a href="/alice">Alice</a>']]]);

        // Assert
        $this->assertStringContainsString('<a href="/alice">Alice</a>', $html);
    }

    public function testTableMakesARowWithAnHrefALinkRow(): void
    {
        // Act
        $html = $this->render('table/table', ['tableId' => 'people', 'columns' => [['label' => 'Name']], 'rows' => [['cells' => ['Alice'], 'href' => '/people/alice']]]);

        // Assert
        $this->assertStringContainsString('data-href="/people/alice"', $html);
        $this->assertStringContainsString('Alice', $html);
    }

    public function testTableShowsTheEmptyTextSpanningEveryColumnWithoutRows(): void
    {
        // Act
        $html = $this->render('table/table', ['tableId' => 'people', 'columns' => [['label' => 'Name'], ['label' => 'Age']], 'emptyText' => 'No people yet.']);

        // Assert
        $this->assertStringContainsString('No people yet.', $html);
        $this->assertStringContainsString('colspan="2"', $html);
    }

    public function testTableUsesPrebuiltHeadAndBodyAndExtraAttributes(): void
    {
        // Act
        $html = $this->render('table/table', ['tableId' => 'people', 'columns' => [['label' => 'Name']], 'thead' => '<tr><th>Custom</th></tr>', 'tbody' => '<tr><td>Row</td></tr>', 'attrs' => 'data-api="/api/people"']);

        // Assert
        $this->assertStringContainsString('<tr><th>Custom</th></tr>', $html);
        $this->assertStringContainsString('<tr><td>Row</td></tr>', $html);
        $this->assertStringContainsString('data-api="/api/people"', $html);
        $this->assertStringNotContainsString('table-header-label', $html);
    }

    public function testColumnToggleLinksToItsTableAndChecksOnlyVisibleColumns(): void
    {
        // Act
        $html = $this->render('table/column-toggle', ['tableId' => 'people', 'columns' => [['label' => 'Name'], ['label' => 'Note', 'visible' => false]]]);

        // Assert
        $this->assertStringContainsString('data-table-controls="people"', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('Note', $html);
        $this->assertSame(1, substr_count($html, 'checked'));
    }
}
