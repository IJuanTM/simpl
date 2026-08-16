<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\FormController;
use PHPUnit\Framework\TestCase;

/**
 * Covers validatePasswords() specifically - the one method @addon-insert-merged into core's
 * FormController. Named distinctly from core's own FormControllerTest.php so it doesn't collide
 * with the already-shipped file of that name and get skipped on install.
 */
class FormControllerPasswordsTest extends TestCase
{
    public function testAcceptsAStrongMatchingPassword(): void
    {
        $_POST['password'] = 'Abcdefg1';
        $_POST['password-check'] = 'Abcdefg1';

        $this->assertTrue(FormController::validatePasswords('password', 'password-check'));
    }

    public function testRejectsAWeakPasswordAndClearsBothFields(): void
    {
        $_POST['password'] = 'weak';
        $_POST['password-check'] = 'weak';

        $this->assertFalse(FormController::validatePasswords('password', 'password-check'));
        $this->assertSame('', $_POST['password']);
        $this->assertSame('', $_POST['password-check']);
    }

    public function testRejectsMismatchedPasswordsAndClearsBothFields(): void
    {
        $_POST['password'] = 'Abcdefg1';
        $_POST['password-check'] = 'Abcdefg2';

        $this->assertFalse(FormController::validatePasswords('password', 'password-check'));
        $this->assertSame('', $_POST['password']);
        $this->assertSame('', $_POST['password-check']);
        $this->assertStringContainsString('do not match', FormController::formAlerts());
    }

    protected function setUp(): void
    {
        $_POST = [];
        FormController::$alerts = [];
    }
}
