<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\AppController;
use PHPUnit\Framework\TestCase;

class AppControllerTest extends TestCase
{
    public function testSanitizeEscapesHtmlSpecialCharacters(): void
    {
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', AppController::sanitize('<script>alert("x")</script>'));
    }

    public function testSanitizeTrimsSurroundingWhitespace(): void
    {
        $this->assertSame('value', AppController::sanitize("  value  \n"));
    }

    public function testSanitizeEscapesSingleQuotes(): void
    {
        // ENT_HTML5 renders the apostrophe as the named entity, not the numeric one.
        $this->assertSame('&apos;', AppController::sanitize("'"));
    }

    public function testCsrfMetaContainsATokenAcceptedByValidateCsrf(): void
    {
        $meta = AppController::csrfMeta();
        preg_match('/content="([^"]+)"/', $meta, $matches);
        $token = $matches[1] ?? null;

        $this->assertNotNull($token);

        $_POST['csrf_token'] = $token;
        $this->assertTrue(AppController::validateCsrf());
    }

    public function testValidateCsrfRejectsAWrongToken(): void
    {
        AppController::csrfMeta();

        $_POST['csrf_token'] = 'not-the-real-token';
        $this->assertFalse(AppController::validateCsrf());
    }

    public function testValidateCsrfRejectsAMissingToken(): void
    {
        AppController::csrfMeta();

        $this->assertFalse(AppController::validateCsrf());
    }

    public function testValidateCsrfAcceptsTheHeaderVariant(): void
    {
        $meta = AppController::csrfMeta();
        preg_match('/content="([^"]+)"/', $meta, $matches);

        $_SERVER['HTTP_X_CSRF_TOKEN'] = $matches[1];
        $this->assertTrue(AppController::validateCsrf());
    }

    public function testSvgReturnsFileContentsWhenPresent(): void
    {
        $this->assertStringContainsString('<svg', AppController::svg('simpl'));
    }

    public function testSvgReturnsAPlaceholderWhenMissing(): void
    {
        $this->assertStringContainsString('SVG "does-not-exist" not found', AppController::svg('does-not-exist'));
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        unset($_POST['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    }
}
