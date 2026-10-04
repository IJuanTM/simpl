<?php

declare(strict_types=1);

namespace tests\Feature\History;

use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Models\Page;
use PHPUnit\Framework\TestCase;
use tests\Support\HeadersAssertionTrait;

final class NavigationHistoryTest extends TestCase
{
    use HeadersAssertionTrait;

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

    public function testRecordHistoryFalseSkipsPushingOntoHistory(): void
    {
        // Arrange + Act
        new Page('api', ['user', '1', 'update-profile-image'], [], false);

        // Assert
        $this->assertSame([], Page::history());
    }

    public function testPrevReturnsTheSecondToLastHistoryEntry(): void
    {
        // Arrange
        SessionController::set('history', ['/a', '/b', '/c']);

        // Act + Assert
        $this->assertSame('/b', PageController::prev());
    }

    public function testPrevFallsBackToRedirectWhenHistoryIsTooShort(): void
    {
        // Arrange
        SessionController::set('history', ['/a']);

        // Act + Assert
        $this->assertSame('/' . REDIRECT, PageController::prev());
    }

    public function testBackTrimsHistoryAndRedirectsToTheNewLastEntry(): void
    {
        // Arrange
        SessionController::set('history', ['/a', '/b', '/c']);

        // Act
        PageController::back();

        // Assert
        $this->assertSame(['/a', '/b'], SessionController::get('history'));
        $this->assertTrue($this->headersContain('Location: /b'));
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        http_response_code(200);
    }
}
