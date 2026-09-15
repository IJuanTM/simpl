<?php

declare(strict_types=1);

namespace tests\Feature\PasswordPolicy;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use PHPUnit\Framework\TestCase;

/**
 * Covers the password policy itself: what's accepted, what's rejected, the generated
 * passwords that must always satisfy it, and the config-driven messaging around it.
 */
final class PasswordPolicyTest extends TestCase
{
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
