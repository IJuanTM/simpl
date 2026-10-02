<?php

declare(strict_types=1);

namespace tests\Unit\Controllers;

use app\Controllers\AuthController;
use PHPUnit\Framework\TestCase;

final class AuthControllerTokenTest extends TestCase
{
    public function testGenerateTokenReturnsTheRequestedLengthUppercasedByDefault(): void
    {
        // Act
        $token = AuthController::generateToken(10);

        // Assert
        $this->assertSame(10, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{10}$/', $token);
    }

    public function testGenerateTokenCanReturnLowercase(): void
    {
        // Act
        $token = AuthController::generateToken(10, false);

        // Assert
        $this->assertMatchesRegularExpression('/^[0-9a-f]{10}$/', $token);
    }
}
