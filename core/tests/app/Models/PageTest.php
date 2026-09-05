<?php

declare(strict_types=1);

namespace tests\Models;

use app\Models\Page;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    public function testTitleAndSubtitleAreDerivedFromThePage(): void
    {
        // Arrange + Act
        $page = new Page('user-settings');

        // Assert
        $this->assertSame(APP_NAME, $page->title);
        $this->assertSame('User Settings', $page->subtitle);
    }

    public function testConstructingAPagePushesItsSubUrlOntoHistory(): void
    {
        // Arrange + Act
        $page = new Page('shop', ['category']);

        // Assert
        $this->assertSame([$page->subUrl()], Page::history());
    }

    public function testHistoryDoesNotDuplicateTheSameSubUrlConsecutively(): void
    {
        // Arrange + Act
        new Page('shop');
        new Page('shop');

        // Assert
        $this->assertCount(1, Page::history());
    }

    public function testHistoryKeepsOnlyTheLastHistoryDepthEntries(): void
    {
        // Arrange + Act
        foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $page) new Page($page);
        $history = Page::history();

        // Assert
        $this->assertCount(HISTORY_DEPTH, $history);
        $this->assertSame('/f/', end($history));
        $this->assertSame('/b/', $history[0]);
    }

    public function testSubUrlBuildsThePathWithSubpagesAndQuery(): void
    {
        // Arrange
        $page = new Page('shop', ['category'], ['sort' => 'asc']);

        // Act + Assert
        $this->assertSame('/shop/category?sort=asc', $page->subUrl());
    }

    public function testSubUrlWithNoSubpagesOrParams(): void
    {
        // Arrange
        $page = new Page('home');

        // Act + Assert
        $this->assertSame('/home/', $page->subUrl());
    }

    public function testSubpageReturnsTheRequestedSegment(): void
    {
        // Arrange
        $page = new Page('shop', ['category', 'shoes']);

        // Act + Assert
        $this->assertSame('category', $page->subpage());
        $this->assertSame('shoes', $page->subpage(1));
        $this->assertNull($page->subpage(2));
    }

    public function testParamReturnsTheValueOrTheDefault(): void
    {
        // Arrange
        $page = new Page('shop', [], ['sort' => 'asc']);

        // Act + Assert
        $this->assertSame('asc', $page->param('sort'));
        $this->assertSame('fallback', $page->param('missing', 'fallback'));
        $this->assertNull($page->param('missing'));
    }

    protected function setUp(): void
    {
        $_SESSION = [];
    }
}
