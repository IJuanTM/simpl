<?php

declare(strict_types=1);

namespace tests\Feature\Alerts;

use app\Controllers\AlertController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Enums\AlertType;
use PHPUnit\Framework\TestCase;

final class GlobalAlertTest extends TestCase
{
    public function testConstructorLeavesANeverExpiringAlertInPlace(): void
    {
        // Arrange
        SessionController::set('alert', ['message' => 'hi', 'type' => 'info', 'timeout' => 0]);

        // Act
        new AlertController();

        // Assert
        $this->assertTrue(SessionController::has('alert'));
    }

    public function testConstructorRemovesAnExpiredAlert(): void
    {
        // Arrange
        SessionController::set('alert', ['message' => 'hi', 'type' => 'info', 'timeout' => time() - 10]);

        // Act
        new AlertController();

        // Assert
        $this->assertFalse(SessionController::has('alert'));
    }

    public function testConstructorKeepsAnAlertThatHasNotExpiredYet(): void
    {
        // Arrange
        SessionController::set('alert', ['message' => 'hi', 'type' => 'info', 'timeout' => time() + 10]);

        // Act
        new AlertController();

        // Assert
        $this->assertTrue(SessionController::has('alert'));
    }

    public function testConstructorDoesNothingWhenNoAlertIsSet(): void
    {
        // Act
        new AlertController();

        // Assert
        $this->assertFalse(SessionController::has('alert'));
    }

    public function testGlobalAlertStoresTheSanitizedMessageAndType(): void
    {
        // Act
        AlertController::globalAlert('<b>hi</b>', AlertType::ERROR);

        // Assert
        $alert = SessionController::get('alert');
        $this->assertSame('&lt;b&gt;hi&lt;/b&gt;', $alert['message']);
        $this->assertSame('error', $alert['type']);
    }

    public function testGlobalAlertWithNoTimeoutStoresZero(): void
    {
        // Act
        AlertController::globalAlert('hi', AlertType::INFO);

        // Assert
        $this->assertSame(0, SessionController::get('alert')['timeout']);
    }

    public function testGlobalAlertWithATimeoutStoresAFutureExpiry(): void
    {
        // Act
        AlertController::globalAlert('hi', AlertType::INFO, 30);

        // Assert
        $timeout = SessionController::get('alert')['timeout'];
        $this->assertGreaterThan(time(), $timeout);
        $this->assertLessThanOrEqual(time() + 30, $timeout);
    }

    public function testPullReturnsAndConsumesANeverExpiringAlertOnANormalResponse(): void
    {
        // Arrange
        SessionController::set('alert', ['message' => 'hi', 'type' => 'info', 'timeout' => 0]);

        // Act
        $alert = AlertController::pull();

        // Assert
        $this->assertSame('hi', $alert['message']);
        $this->assertFalse(SessionController::has('alert'));
    }

    public function testPullKeepsANeverExpiringAlertWhileTheResponseIsARedirect(): void
    {
        // Arrange
        SessionController::set('alert', ['message' => 'hi', 'type' => 'info', 'timeout' => 0]);
        http_response_code(302);

        // Act
        $alert = AlertController::pull();

        // Assert
        $this->assertSame('hi', $alert['message']);
        $this->assertTrue(SessionController::has('alert'));
    }

    public function testPullNeverConsumesATimedAlert(): void
    {
        // Arrange
        SessionController::set('alert', ['message' => 'hi', 'type' => 'info', 'timeout' => time() + 30]);

        // Act
        AlertController::pull();

        // Assert
        $this->assertTrue(SessionController::has('alert'));
    }

    public function testPullReturnsNullWhenNoAlertIsSet(): void
    {
        // Act + Assert
        $this->assertNull(AlertController::pull());
    }

    public function testPageControllerRedirectWithAlertWiresThroughToGlobalAlert(): void
    {
        // Act
        PageController::redirectWithAlert('/somewhere', 'hi', AlertType::SUCCESS);

        // Assert
        $this->assertSame('hi', SessionController::get('alert')['message']);
        $this->assertSame(302, http_response_code());
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        http_response_code(200);
    }
}
