<?php

declare(strict_types=1);

namespace tests\Feature\Alerts;

use app\Controllers\FormController;
use app\Enums\AlertType;
use PHPUnit\Framework\TestCase;

final class FormAlertsTest extends TestCase
{
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
        FormController::$alerts = [];
    }
}
