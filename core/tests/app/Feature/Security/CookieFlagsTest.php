<?php

declare(strict_types=1);

namespace tests\Feature\Security;

use app\Controllers\AppController;
use PHPUnit\Framework\TestCase;

final class CookieFlagsTest extends TestCase
{
    public function testSecureCookieFlagsAreHardenedAndTrackHttps(): void
    {
        // Arrange
        $previous = $_SERVER['HTTPS'] ?? null;

        try {
            // Act
            unset($_SERVER['HTTPS']);
            $plain = AppController::secureCookieFlags();
            $_SERVER['HTTPS'] = 'off';
            $off = AppController::secureCookieFlags();
            $_SERVER['HTTPS'] = 'on';
            $secure = AppController::secureCookieFlags();

            // Assert
            $this->assertSame('/', $plain['path']);
            $this->assertTrue($plain['httponly']);
            $this->assertSame('Strict', $plain['samesite']);
            $this->assertFalse($plain['secure']);
            $this->assertFalse($off['secure']);
            $this->assertTrue($secure['secure']);
        } finally {
            // Cleanup
            if ($previous === null) unset($_SERVER['HTTPS']);
            else $_SERVER['HTTPS'] = $previous;
        }
    }
}
