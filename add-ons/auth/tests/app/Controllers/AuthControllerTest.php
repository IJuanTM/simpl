<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use PHPUnit\Framework\TestCase;

/**
 * Only covers AuthController's config-driven, DB-free surface: password policy,
 * generated-password shape and token generation. Everything else on AuthController
 * touches DB, session or redirect.
 */
final class AuthControllerTest extends TestCase
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

    public function testValidatePasswordAcceptsAPasswordMeetingThePolicy(): void
    {
        // Act + Assert
        $this->assertTrue(AuthController::validatePassword('Abcdefg1'));
    }

    public function testValidatePasswordRejectsAPasswordMissingAnUppercaseLetter(): void
    {
        // Act + Assert
        $this->assertFalse(AuthController::validatePassword('abcdefg1'));
    }

    public function testValidatePasswordRejectsAPasswordMissingADigit(): void
    {
        // Act + Assert
        $this->assertFalse(AuthController::validatePassword('Abcdefgh'));
    }

    public function testValidatePasswordRejectsAPasswordMissingALowercaseLetter(): void
    {
        // Act + Assert
        $this->assertFalse(AuthController::validatePassword('ABCDEFG1'));
    }

    public function testValidatePasswordRejectsATooShortPassword(): void
    {
        // Act + Assert
        $this->assertFalse(AuthController::validatePassword('Ab1'));
    }

    public function testValidatePasswordQueuesAnAlertOnFailure(): void
    {
        // Act
        (void)AuthController::validatePassword('short');

        // Assert
        $this->assertNotNull(FormController::formAlerts());
    }

    public function testGetPasswordRequirementsListsTheConfiguredRules(): void
    {
        // Act
        $message = AuthController::getPasswordRequirements();

        // Assert
        $this->assertStringContainsString('at least ' . PASSWORD_CONFIG['min_length'] . ' characters', $message);
        $this->assertStringContainsString('lowercase letter', $message);
        $this->assertStringContainsString('uppercase letter', $message);
        $this->assertStringContainsString('number', $message);
    }

    public function testGetPasswordPatternSourceIsUsableAsARawRegexBody(): void
    {
        // Act
        $source = AuthController::getPasswordPatternSource();

        // Assert
        $this->assertMatchesRegularExpression("/$source/", 'Abcdefg1');
        $this->assertDoesNotMatchRegularExpression("/$source/", 'abcdefgh');
    }

    public function testGeneratePasswordUsesTheGeneratedPasswordLengthConstant(): void
    {
        // Act
        $password = AuthController::generatePassword();

        // Assert
        $this->assertSame(PASSWORD_CONFIG['generated_length'], strlen($password));
    }

    public function testIsGeneratedPasswordShapeAcceptsAFreshlyGeneratedPassword(): void
    {
        // Arrange
        $password = AuthController::generatePassword();

        // Act + Assert
        $this->assertTrue(AuthController::isGeneratedPasswordShape($password));
    }

    public function testIsGeneratedPasswordShapeRejectsAWrongLength(): void
    {
        // Act + Assert
        $this->assertFalse(AuthController::isGeneratedPasswordShape(str_repeat('A', PASSWORD_CONFIG['generated_length'] - 1)));
    }

    public function testIsGeneratedPasswordShapeRejectsTheAmbiguousCharactersItExcludes(): void
    {
        // Arrange
        $ambiguousOnly = str_repeat('0', PASSWORD_CONFIG['generated_length']);

        // Act + Assert
        $this->assertFalse(AuthController::isGeneratedPasswordShape($ambiguousOnly));
    }

    protected function setUp(): void
    {
        FormController::$alerts = [];
    }
}
