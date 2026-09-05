<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\FormController;
use app\Enums\AlertType;
use PHPUnit\Framework\TestCase;

final class FormControllerTest extends TestCase
{
    public function testRequiredRejectsAMissingField(): void
    {
        // Act
        $passed = FormController::validate('email', ['required']);

        // Assert
        $this->assertFalse($passed);
        $this->assertNotNull(FormController::formAlerts());
    }

    public function testRequiredAcceptsAPresentField(): void
    {
        // Arrange
        $_POST['email'] = 'a@b.com';

        // Act
        $passed = FormController::validate('email', ['required']);

        // Assert
        $this->assertTrue($passed);
        $this->assertNull(FormController::formAlerts());
    }

    public function testRejectsAnArrayValueEvenWithoutRules(): void
    {
        // Arrange
        $_POST['tags'] = ['a', 'b'];

        // Act
        $passed = FormController::validate('tags', []);

        // Assert
        $this->assertFalse($passed);
        $this->assertSame('', $_POST['tags']);
    }

    public function testMinLengthRejectsATooShortValueAndClearsIt(): void
    {
        // Arrange
        $_POST['username'] = 'ab';

        // Act
        $passed = FormController::validate('username', ['minLength' => 3]);

        // Assert
        $this->assertFalse($passed);
        $this->assertSame('', $_POST['username']);
    }

    public function testMaxLengthRejectsATooLongValueAndClearsIt(): void
    {
        // Arrange
        $_POST['username'] = 'abcdef';

        // Act
        $passed = FormController::validate('username', ['maxLength' => 3]);

        // Assert
        $this->assertFalse($passed);
        $this->assertSame('', $_POST['username']);
    }

    public function testMaxLengthCountsMultibyteCharactersNotBytes(): void
    {
        // Arrange
        // Each character is 2 bytes in UTF-8 but must count as 1 character against the limit.
        $_POST['username'] = 'абвг';

        // Act + Assert
        $this->assertTrue(FormController::validate('username', ['maxLength' => 4]));
    }

    public function testMinLengthCountsMultibyteCharactersNotBytes(): void
    {
        // Arrange
        $_POST['username'] = 'а';

        // Act + Assert
        $this->assertFalse(FormController::validate('username', ['minLength' => 3]));
    }

    public function testMinValueRejectsATooLowNumber(): void
    {
        // Arrange
        $_POST['age'] = '5';

        // Act + Assert
        $this->assertFalse(FormController::validate('age', ['minValue' => 18]));
    }

    public function testMinValueAcceptsANumberAboveTheBound(): void
    {
        // Arrange
        $_POST['age'] = '25';

        // Act + Assert
        $this->assertTrue(FormController::validate('age', ['minValue' => 18]));
    }

    public function testMaxValueRejectsATooHighNumber(): void
    {
        // Arrange
        $_POST['age'] = '150';

        // Act + Assert
        $this->assertFalse(FormController::validate('age', ['maxValue' => 120]));
    }

    public function testMaxValueAcceptsANumberBelowTheBound(): void
    {
        // Arrange
        $_POST['age'] = '100';

        // Act + Assert
        $this->assertTrue(FormController::validate('age', ['maxValue' => 120]));
    }

    public function testTypeNumberRejectsANonNumericValue(): void
    {
        // Arrange
        $_POST['age'] = 'abc';

        // Act + Assert
        $this->assertFalse(FormController::validate('age', ['type' => 'number']));
    }

    public function testTypeNumberAcceptsANumericValue(): void
    {
        // Arrange
        $_POST['age'] = '42';

        // Act + Assert
        $this->assertTrue(FormController::validate('age', ['type' => 'number']));
    }

    public function testTypeEmailRejectsAnInvalidAddress(): void
    {
        // Arrange
        $_POST['email'] = 'not-an-email';

        // Act + Assert
        $this->assertFalse(FormController::validate('email', ['type' => 'email']));
    }

    public function testTypeEmailAcceptsAValidAddress(): void
    {
        // Arrange
        $_POST['email'] = 'a@b.com';

        // Act + Assert
        $this->assertTrue(FormController::validate('email', ['type' => 'email']));
    }

    public function testOptionalFieldWithNoRulesPasses(): void
    {
        // Act + Assert
        $this->assertTrue(FormController::validate('nickname', []));
    }

    public function testAddAlertEscapesTheMessage(): void
    {
        // Act
        FormController::addAlert('<script>alert(1)</script>', AlertType::ERROR);

        // Assert
        $this->assertStringNotContainsString('<script>', FormController::formAlerts());
    }

    public function testFormAlertsReturnsNullAndClearsTheQueueAfterReading(): void
    {
        // Arrange
        FormController::addAlert('hello', AlertType::SUCCESS);

        // Act
        $first = FormController::formAlerts();
        $second = FormController::formAlerts();

        // Assert
        $this->assertStringContainsString('hello', $first);
        $this->assertNull($second);
    }

    protected function setUp(): void
    {
        $_POST = [];
        FormController::$alerts = [];
    }
}
