<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\FormController;
use app\Enums\AlertType;
use PHPUnit\Framework\TestCase;

class FormControllerTest extends TestCase
{
    public function testRequiredRejectsAMissingField(): void
    {
        $this->assertFalse(FormController::validate('email', ['required']));
        $this->assertNotNull(FormController::formAlerts());
    }

    public function testRequiredAcceptsAPresentField(): void
    {
        $_POST['email'] = 'a@b.com';

        $this->assertTrue(FormController::validate('email', ['required']));
        $this->assertNull(FormController::formAlerts());
    }

    public function testRejectsAnArrayValueEvenWithoutRules(): void
    {
        $_POST['tags'] = ['a', 'b'];

        $this->assertFalse(FormController::validate('tags', []));
        $this->assertSame('', $_POST['tags']);
    }

    public function testMinLengthRejectsATooShortValueAndClearsIt(): void
    {
        $_POST['username'] = 'ab';

        $this->assertFalse(FormController::validate('username', ['minLength' => 3]));
        $this->assertSame('', $_POST['username']);
    }

    public function testMaxLengthRejectsATooLongValueAndClearsIt(): void
    {
        $_POST['username'] = 'abcdef';

        $this->assertFalse(FormController::validate('username', ['maxLength' => 3]));
        $this->assertSame('', $_POST['username']);
    }

    public function testMinValueRejectsATooLowNumber(): void
    {
        $_POST['age'] = '5';

        $this->assertFalse(FormController::validate('age', ['minValue' => 18]));
    }

    public function testMaxValueRejectsATooHighNumber(): void
    {
        $_POST['age'] = '150';

        $this->assertFalse(FormController::validate('age', ['maxValue' => 120]));
    }

    public function testTypeNumberRejectsANonNumericValue(): void
    {
        $_POST['age'] = 'abc';

        $this->assertFalse(FormController::validate('age', ['type' => 'number']));
    }

    public function testTypeNumberAcceptsANumericValue(): void
    {
        $_POST['age'] = '42';

        $this->assertTrue(FormController::validate('age', ['type' => 'number']));
    }

    public function testTypeEmailRejectsAnInvalidAddress(): void
    {
        $_POST['email'] = 'not-an-email';

        $this->assertFalse(FormController::validate('email', ['type' => 'email']));
    }

    public function testTypeEmailAcceptsAValidAddress(): void
    {
        $_POST['email'] = 'a@b.com';

        $this->assertTrue(FormController::validate('email', ['type' => 'email']));
    }

    public function testOptionalFieldWithNoRulesPasses(): void
    {
        $this->assertTrue(FormController::validate('nickname', []));
    }

    public function testAddAlertEscapesTheMessage(): void
    {
        FormController::addAlert('<script>alert(1)</script>', AlertType::ERROR);

        $this->assertStringNotContainsString('<script>', FormController::formAlerts());
    }

    public function testFormAlertsReturnsNullAndClearsTheQueueAfterReading(): void
    {
        FormController::addAlert('hello', AlertType::SUCCESS);

        $first = FormController::formAlerts();
        $second = FormController::formAlerts();

        $this->assertStringContainsString('hello', $first);
        $this->assertNull($second);
    }

    protected function setUp(): void
    {
        $_POST = [];
        FormController::$alerts = [];
    }
}
