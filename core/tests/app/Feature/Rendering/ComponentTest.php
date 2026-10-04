<?php

declare(strict_types=1);

namespace tests\Feature\Rendering;

use app\Controllers\BreadcrumbController;
use app\Controllers\PageController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use tests\Support\OutputCaptureTrait;

final class ComponentTest extends TestCase
{
    use OutputCaptureTrait;

    public function testPartEchoesAVisibleMessageInDevModeWhenNotFound(): void
    {
        // Arrange
        $page = $this->instanceWithoutConstructor();
        $method = new ReflectionMethod(PageController::class, 'part');
        $name = 'does-not-exist-' . uniqid();

        // Act
        $output = $this->captured(static fn() => $method->invoke($page, $name));

        // Assert
        // Exact match, not substring: the non-DEV branch's comment output also contains "not found".
        $this->assertSame("Part \"$name\" not found", $output);
    }

    private function instanceWithoutConstructor(): PageController
    {
        return new ReflectionClass(PageController::class)->newInstanceWithoutConstructor();
    }

    public function testComponentRendersAnExistingComponentFile(): void
    {
        // Arrange
        BreadcrumbController::set([['label' => 'Home', 'url' => null]]);
        $page = $this->instanceWithoutConstructor();

        // Act
        $output = $this->captured(static fn() => $page->component('nav/breadcrumbs'));

        // Assert
        $this->assertStringContainsString('Home', $output);
        $this->assertStringContainsString('breadcrumbs', $output);
    }

    public function testComponentEchoesAVisibleMessageWhenNotFound(): void
    {
        // Arrange
        $page = $this->instanceWithoutConstructor();
        $name = 'does-not-exist-' . uniqid();

        // Act
        $output = $this->captured(static fn() => $page->component($name));

        // Assert
        // Exact match, not substring: the non-DEV branch's comment output also contains "not found".
        $this->assertSame("Component \"$name\" not found", $output);
    }

    protected function setUp(): void
    {
        $_SESSION = [];
    }
}
