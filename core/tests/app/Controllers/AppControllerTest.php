<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\AppController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AppControllerTest extends TestCase
{
    public function testCsrfMetaContainsATokenAcceptedByValidateCsrf(): void
    {
        // Arrange
        $meta = AppController::csrfMeta();
        preg_match('/content="([^"]+)"/', $meta, $matches);
        $token = $matches[1] ?? null;
        $this->assertNotNull($token);
        $_POST['csrf_token'] = $token;

        // Act + Assert
        $this->assertTrue(AppController::validateCsrf());
    }

    public function testValidateCsrfRejectsAWrongToken(): void
    {
        // Arrange
        AppController::csrfMeta();
        $_POST['csrf_token'] = 'not-the-real-token';

        // Act + Assert
        $this->assertFalse(AppController::validateCsrf());
    }

    public function testValidateCsrfRejectsAMissingToken(): void
    {
        // Arrange
        AppController::csrfMeta();

        // Act + Assert
        $this->assertFalse(AppController::validateCsrf());
    }

    public function testValidateCsrfAcceptsTheHeaderVariant(): void
    {
        // Arrange
        $meta = AppController::csrfMeta();
        preg_match('/content="([^"]+)"/', $meta, $matches);
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $matches[1];

        // Act + Assert
        $this->assertTrue(AppController::validateCsrf());
    }

    public function testSanitizeEscapesHtmlSpecialCharacters(): void
    {
        // Act + Assert
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', AppController::sanitize('<script>alert("x")</script>'));
    }

    public function testSanitizeTrimsSurroundingWhitespace(): void
    {
        // Act + Assert
        $this->assertSame('value', AppController::sanitize("  value  \n"));
    }

    public function testSanitizeEscapesSingleQuotes(): void
    {
        // Act + Assert
        // ENT_HTML5 renders the apostrophe as the named entity, not the numeric one.
        $this->assertSame('&apos;', AppController::sanitize("'"));
    }

    public function testSecureCookieFlagsAreHardenedAndTrackHttps(): void
    {
        // Arrange
        $previous = $_SERVER['HTTPS'] ?? null;

        try {
            // Act
            unset($_SERVER['HTTPS']);
            $plain = AppController::secureCookieFlags();
            $_SERVER['HTTPS'] = 'on';
            $secure = AppController::secureCookieFlags();

            // Assert
            $this->assertSame('/', $plain['path']);
            $this->assertTrue($plain['httponly']);
            $this->assertSame('Strict', $plain['samesite']);
            $this->assertFalse($plain['secure']);
            $this->assertTrue($secure['secure']);
        } finally {
            // Cleanup
            if ($previous === null) unset($_SERVER['HTTPS']);
            else $_SERVER['HTTPS'] = $previous;
        }
    }

    public function testSvgReturnsFileContentsWhenPresent(): void
    {
        // Act + Assert
        $this->assertStringContainsString('<svg', AppController::svg('simpl'));
    }

    public function testSvgReturnsAPlaceholderWhenMissing(): void
    {
        // Act + Assert
        $this->assertStringContainsString('SVG "does-not-exist" not found', AppController::svg('does-not-exist'));
    }

    public function testInjectCsrfAddsAHiddenTokenAfterPostFormsOnly(): void
    {
        // Arrange
        $injectCsrf = new ReflectionMethod(AppController::class, 'injectCsrf');
        $html = '<form method="post"><input name="a"></form><form method="get"><input name="b"></form>';

        // Act
        $output = $injectCsrf->invoke(null, $html);

        // Assert
        $this->assertMatchesRegularExpression('/<form method="post"><input type="hidden" name="csrf_token" value="[^"]+">/', $output);
        $this->assertStringNotContainsString('method="get"><input type="hidden" name="csrf_token"', $output);
    }

    public function testInjectCsrfEmitsATokenAcceptedByValidateCsrf(): void
    {
        // Arrange
        $injectCsrf = new ReflectionMethod(AppController::class, 'injectCsrf');

        // Act
        $output = $injectCsrf->invoke(null, '<form method="post"></form>');
        preg_match('/value="([^"]+)"/', $output, $matches);

        // Assert
        $_POST['csrf_token'] = $matches[1] ?? null;
        $this->assertTrue(AppController::validateCsrf());
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        unset($_POST['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    }
}
