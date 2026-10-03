<?php

declare(strict_types=1);

namespace tests\Unit\Controllers;

use app\Controllers\AuthController;
use PHPUnit\Framework\TestCase;

final class AuthControllerUsernameTest extends TestCase
{
    public function testIsReservedUsernameMatchesLookalikes(): void
    {
        // Act + Assert
        foreach (['admin', 'ADMIN', 'ad_m.i-n', '4dm1n', 'admln', 'm0derat0r'] as $username) {
            $this->assertTrue(AuthController::isReservedUsername($username, ['admin', 'Moderator']), $username);
        }
    }

    public function testIsReservedUsernameAllowsNamesThatOnlyContainAReservedWord(): void
    {
        // Act + Assert
        foreach (['admin123', 'badminton', 'modern'] as $username) {
            $this->assertFalse(AuthController::isReservedUsername($username, ['admin', 'mod']), $username);
        }
    }
}
