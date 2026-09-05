<?php

declare(strict_types=1);

namespace tests\Pages;

use app\Enums\ErrorCode;
use app\Models\Page;
use app\Pages\ErrorPage;
use PHPUnit\Framework\TestCase;
use tests\Support\HeadersAssertionTrait;

/**
 * The invalid-error-code branch calls PageController::error() followed by a real exit,
 * which would kill the test process - not exercised here, same as PageControllerTest's
 * documented exclusion of exit paths. ERROR_AUTO_REDIRECT is a hardcoded true in app.php
 * (not env-driven), so every construction here also sends a delayed refresh redirect.
 */
final class ErrorPageTest extends TestCase
{
    use HeadersAssertionTrait;

    public function testValidCodeSetsCodeAndMessage(): void
    {
        // Arrange + Act
        $errorPage = new ErrorPage(new Page('error', ['404']));

        // Assert
        $this->assertSame('404', $errorPage->code);
        $this->assertSame(ErrorCode::NOT_FOUND->message(), $errorPage->message);
    }

    public function testSetsTheMatchingHttpResponseCode(): void
    {
        // Arrange + Act
        new ErrorPage(new Page('error', ['403']));

        // Assert
        $this->assertSame(403, http_response_code());
    }

    public function testSetsASubtitleIncludingTheCode(): void
    {
        // Arrange
        $page = new Page('error', ['403']);

        // Act
        new ErrorPage($page);

        // Assert
        $this->assertSame('Error 403', $page->subtitle);
    }

    public function testRedirectPageDefaultsWhenNoRedirectParamGiven(): void
    {
        // Arrange + Act
        $errorPage = new ErrorPage(new Page('error', ['404']));

        // Assert
        $this->assertSame(REDIRECT, $errorPage->redirectPage);
    }

    public function testRedirectPageUsesASafeRedirectParam(): void
    {
        // Arrange + Act
        $errorPage = new ErrorPage(new Page('error', ['404'], ['redirect' => '/some/page?x=1&y=2']));

        // Assert
        $this->assertSame('/some/page?x=1&y=2', $errorPage->redirectPage);
    }

    public function testRedirectPageRejectsARedirectParamWithDisallowedCharacters(): void
    {
        // Arrange + Act
        $errorPage = new ErrorPage(new Page('error', ['404'], ['redirect' => 'javascript:alert(1)']));

        // Assert
        $this->assertSame(REDIRECT, $errorPage->redirectPage);
    }

    public function testAutoRedirectsWithADelayedRefresh(): void
    {
        // Arrange + Act
        new ErrorPage(new Page('error', ['404']));

        // Assert
        $this->assertTrue($this->headersContain('refresh: 2;'));
    }

    protected function setUp(): void
    {
        $_SESSION = [];
    }
}
