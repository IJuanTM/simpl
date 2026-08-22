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
class SessionControllerTest extends TestCase
{
    public function testSetAndGetRoundTrip(): void
    {
        SessionController::set('key', 'value');

        $this->assertSame('value', SessionController::get('key'));
    }

    public function testGetReturnsNullForAMissingKey(): void
    {
        $this->assertNull(SessionController::get('missing'));
    }

    public function testHasReflectsWhetherTheKeyIsSet(): void
    {
        $this->assertFalse(SessionController::has('key'));

        SessionController::set('key', 'value');

        $this->assertTrue(SessionController::has('key'));
    }

    public function testRemoveDeletesTheKey(): void
    {
        SessionController::set('key', 'value');
        SessionController::remove('key');

        $this->assertFalse(SessionController::has('key'));
    }

    public function testSettingAKeyToNullReadsBackAsNotPresent(): void
    {
        // has() uses isset(), which treats a null value the same as absent - get() stays consistent with that since it checks has() first.
        // This is intentional, not a leak.
        SessionController::set('key', null);

        $this->assertFalse(SessionController::has('key'));
        $this->assertNull(SessionController::get('key'));
    }

    protected function setUp(): void
    {
        $_SESSION = [];
    }
}
