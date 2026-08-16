<?php

declare(strict_types=1);

namespace tests\Models;

use app\Models\Page;
use PHPUnit\Framework\TestCase;

class PageTest extends TestCase
{
    public function testSubUrlBuildsThePathWithSubpagesAndQuery(): void
    {
        $page = new Page('shop', ['category'], ['sort' => 'asc']);

        $this->assertSame('/shop/category?sort=asc', $page->subUrl());
    }

    public function testSubUrlWithNoSubpagesOrParams(): void
    {
        $page = new Page('home');

        $this->assertSame('/home/', $page->subUrl());
    }

    public function testSubpageReturnsTheRequestedSegment(): void
    {
        $page = new Page('shop', ['category', 'shoes']);

        $this->assertSame('category', $page->subpage());
        $this->assertSame('shoes', $page->subpage(1));
        $this->assertNull($page->subpage(2));
    }

    public function testParamReturnsTheValueOrTheDefault(): void
    {
        $page = new Page('shop', [], ['sort' => 'asc']);

        $this->assertSame('asc', $page->param('sort'));
        $this->assertSame('fallback', $page->param('missing', 'fallback'));
        $this->assertNull($page->param('missing'));
    }

    public function testTitleAndSubtitleAreDerivedFromThePage(): void
    {
        $page = new Page('user-settings');

        $this->assertSame(APP_NAME, $page->title);
        $this->assertSame('User Settings', $page->subtitle);
    }

    public function testConstructingAPagePushesItsSubUrlOntoHistory(): void
    {
        $page = new Page('shop', ['category']);

        $this->assertSame([$page->subUrl()], Page::history());
    }

    public function testHistoryDoesNotDuplicateTheSameSubUrlConsecutively(): void
    {
        new Page('shop');
        new Page('shop');

        $this->assertCount(1, Page::history());
    }

    public function testHistoryKeepsOnlyTheLastFiveEntries(): void
    {
        foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $page) new Page($page);

        $history = Page::history();

        $this->assertCount(5, $history);
        $this->assertSame('/f/', end($history));
        $this->assertSame('/b/', $history[0]);
    }

    protected function setUp(): void
    {
        $_SESSION = [];
    }
}
