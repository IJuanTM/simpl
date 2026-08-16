<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use PHPUnit\Framework\TestCase;

/**
 * Only covers AuthController's config-driven, DB-free surface: password policy and
 * token generation. Everything else on AuthController touches DB, session or redirect.
 */
class AuthControllerTest extends TestCase
{
    public function testValidatePasswordAcceptsAPasswordMeetingThePolicy(): void
    {
        $this->assertTrue(AuthController::validatePassword('Abcdefg1'));
    }

    public function testValidatePasswordRejectsAPasswordMissingAnUppercaseLetter(): void
    {
        $this->assertFalse(AuthController::validatePassword('abcdefg1'));
    }

    public function testValidatePasswordRejectsAPasswordMissingADigit(): void
    {
        $this->assertFalse(AuthController::validatePassword('Abcdefgh'));
    }

    public function testValidatePasswordRejectsATooShortPassword(): void
    {
        $this->assertFalse(AuthController::validatePassword('Ab1'));
    }

    public function testValidatePasswordQueuesAnAlertOnFailure(): void
    {
        AuthController::validatePassword('short');

        $this->assertNotNull(FormController::formAlerts());
    }

    public function testGetPasswordRequirementsListsTheConfiguredRules(): void
    {
        $message = AuthController::getPasswordRequirements();

        $this->assertStringContainsString('at least ' . MIN_PASSWORD_LENGTH . ' characters', $message);
        $this->assertStringContainsString('lowercase letter', $message);
        $this->assertStringContainsString('uppercase letter', $message);
        $this->assertStringContainsString('number', $message);
    }

    public function testGetPasswordPatternSourceIsUsableAsARawRegexBody(): void
    {
        $source = AuthController::getPasswordPatternSource();

        $this->assertMatchesRegularExpression("/$source/", 'Abcdefg1');
        $this->assertDoesNotMatchRegularExpression("/$source/", 'abcdefgh');
    }

    public function testGenerateTokenReturnsTheRequestedLengthUppercasedByDefault(): void
    {
        $token = AuthController::generateToken(10);

        $this->assertSame(10, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{10}$/', $token);
    }

    public function testGenerateTokenCanReturnLowercase(): void
    {
        $token = AuthController::generateToken(10, false);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{10}$/', $token);
    }

    public function testGeneratePasswordUsesTheGeneratedPasswordLengthConstant(): void
    {
        $password = AuthController::generatePassword();

        $this->assertSame(GENERATED_PASSWORD_LENGTH, strlen($password));
    }

    protected function setUp(): void
    {
        FormController::$alerts = [];
    }
}
