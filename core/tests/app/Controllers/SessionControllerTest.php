<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\SessionController;
use PHPUnit\Framework\TestCase;

/**
 * Only the static accessors are covered - the constructor calls session_start() for real,
 * which would take over $_SESSION for the rest of this PHPUnit process and affect every
 * other test relying on a plain array.
 */
final class SessionControllerTest extends TestCase
{
    public function testSetAndGetRoundTrip(): void
    {
        // Arrange
        SessionController::set('key', 'value');

        // Act + Assert
        $this->assertSame('value', SessionController::get('key'));
    }

    public function testGetReturnsNullForAMissingKey(): void
    {
        // Act + Assert
        $this->assertNull(SessionController::get('missing'));
    }

    public function testHasReflectsWhetherTheKeyIsSet(): void
    {
        // Act + Assert
        $this->assertFalse(SessionController::has('key'));

        // Arrange
        SessionController::set('key', 'value');

        // Act + Assert
        $this->assertTrue(SessionController::has('key'));
    }

    public function testSettingAKeyToNullReadsBackAsNotPresent(): void
    {
        // Arrange
        // has() uses isset(), which treats a null value the same as absent - get() stays consistent with that since it checks has() first.
        // This is intentional, not a leak.
        SessionController::set('key', null);

        // Act + Assert
        $this->assertFalse(SessionController::has('key'));
        $this->assertNull(SessionController::get('key'));
    }

    public function testRemoveDeletesTheKey(): void
    {
        // Arrange
        SessionController::set('key', 'value');

        // Act
        SessionController::remove('key');

        // Assert
        $this->assertFalse(SessionController::has('key'));
    }

    protected function setUp(): void
    {
        $_SESSION = [];
    }
}
