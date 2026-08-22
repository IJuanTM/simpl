<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\BreadcrumbController;
use app\Models\Page;
use PHPUnit\Framework\TestCase;

class BreadcrumbControllerTest extends TestCase
{
    public function testSetAndGetRoundTrip(): void
    {
        $trail = [['label' => 'Home', 'url' => '/home']];
        BreadcrumbController::set($trail);

        $this->assertSame($trail, BreadcrumbController::get());
    }

    public function testSetSanitizesLabelAndUrl(): void
    {
        BreadcrumbController::set([['label' => '<script>', 'url' => '"><script>']]);

        $trail = BreadcrumbController::get();

        $this->assertStringNotContainsString('<script>', $trail[0]['label']);
        $this->assertStringNotContainsString('<script>', (string)$trail[0]['url']);
    }

    public function testGetReturnsAnEmptyArrayByDefault(): void
    {
        $this->assertSame([], BreadcrumbController::get());
    }

    public function testGenerateBuildsATrailFromThePageAndSubpages(): void
    {
        $page = new Page('user-settings', ['profile-info']);
        BreadcrumbController::generate($page);

        $trail = BreadcrumbController::get();

        $this->assertSame('User settings', $trail[0]['label']);
        $this->assertSame('user-settings', $trail[0]['url']);
        $this->assertSame('Profile info', $trail[1]['label']);
    }

    public function testGenerateNullsOutTheUrlOfTheLastCrumb(): void
    {
        $page = new Page('user-settings', ['profile-info']);
        BreadcrumbController::generate($page);

        $trail = BreadcrumbController::get();

        $this->assertNull($trail[array_key_last($trail)]['url']);
    }

    public function testGenerateSanitizesTheLabel(): void
    {
        $page = new Page('<script>', []);
        BreadcrumbController::generate($page);

        $trail = BreadcrumbController::get();

        $this->assertStringNotContainsString('<script>', $trail[0]['label']);
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        BreadcrumbController::set([]);
    }
}
