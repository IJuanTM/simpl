<?php

declare(strict_types=1);

namespace tests\Feature\Rendering;

use app\Controllers\BreadcrumbController;
use app\Models\Page;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class BreadcrumbsTest extends TestCase
{
    public function testGenerateBuildsATrailFromThePageAndSubpages(): void
    {
        // Arrange
        $page = new Page('user-settings', ['profile-info']);

        // Act
        BreadcrumbController::generate($page);
        $trail = BreadcrumbController::get();

        // Assert
        $this->assertSame('User settings', $trail[0]['label']);
        $this->assertSame('user-settings', $trail[0]['url']);
        $this->assertSame('Profile info', $trail[1]['label']);
    }

    public function testGenerateNullsOutTheUrlOfTheLastCrumb(): void
    {
        // Arrange
        $page = new Page('user-settings', ['profile-info']);

        // Act
        BreadcrumbController::generate($page);
        $trail = BreadcrumbController::get();

        // Assert
        $this->assertNull($trail[array_key_last($trail)]['url']);
    }

    public function testGenerateSanitizesTheLabel(): void
    {
        // Arrange
        $page = new Page('<script>', []);

        // Act
        BreadcrumbController::generate($page);
        $trail = BreadcrumbController::get();

        // Assert
        $this->assertStringNotContainsString('<script>', $trail[0]['label']);
    }

    public function testSetAndGetRoundTrip(): void
    {
        // Arrange
        $trail = [['label' => 'Home', 'url' => '/home']];

        // Act
        BreadcrumbController::set($trail);

        // Assert
        $this->assertSame($trail, BreadcrumbController::get());
    }

    public function testSetSanitizesLabelAndUrl(): void
    {
        // Act
        BreadcrumbController::set([['label' => '<script>', 'url' => '"><script>']]);
        $trail = BreadcrumbController::get();

        // Assert
        $this->assertStringNotContainsString('<script>', $trail[0]['label']);
        $this->assertStringNotContainsString('<script>', (string)$trail[0]['url']);
    }

    public function testGetReturnsAnEmptyArrayByDefault(): void
    {
        // Act + Assert
        $this->assertSame([], BreadcrumbController::get());
    }

    public function testIsSuppressedIsFalseByDefault(): void
    {
        // Act + Assert
        $this->assertFalse(BreadcrumbController::isSuppressed());
    }

    public function testSuppressBlocksFurtherSetAndGenerateCalls(): void
    {
        // Arrange
        BreadcrumbController::set([['label' => 'Home', 'url' => '/home']]);

        // Act
        BreadcrumbController::suppress();
        BreadcrumbController::set([['label' => 'Changed', 'url' => '/changed']]);
        BreadcrumbController::generate(new Page('changed', []));

        // Assert
        $this->assertTrue(BreadcrumbController::isSuppressed());
        $this->assertSame('Home', BreadcrumbController::get()[0]['label']);
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        new ReflectionProperty(BreadcrumbController::class, 'suppressed')->setValue(null, false);
        BreadcrumbController::set([]);
    }
}
