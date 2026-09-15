<?php

declare(strict_types=1);

namespace tests\Feature\Security;

use app\Controllers\AppController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class CsrfTest extends TestCase
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
