<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\AlertController;
use app\Controllers\SessionController;
use app\Enums\AlertType;
use PHPUnit\Framework\TestCase;

class AlertControllerTest extends TestCase
{
    public function testGlobalAlertStoresTheSanitizedMessageAndType(): void
    {
        AlertController::globalAlert('<b>hi</b>', AlertType::ERROR);

        $alert = SessionController::get('alert');
        $this->assertSame('&lt;b&gt;hi&lt;/b&gt;', $alert['message']);
        $this->assertSame('error', $alert['type']);
    }

    public function testGlobalAlertWithNoTimeoutStoresZero(): void
    {
        AlertController::globalAlert('hi', AlertType::INFO);

        $this->assertSame(0, SessionController::get('alert')['timeout']);
    }

    public function testGlobalAlertWithATimeoutStoresAFutureExpiry(): void
    {
        AlertController::globalAlert('hi', AlertType::INFO, 30);

        $timeout = SessionController::get('alert')['timeout'];
        $this->assertGreaterThan(time(), $timeout);
        $this->assertLessThanOrEqual(time() + 30, $timeout);
    }

    public function testConstructorLeavesANeverExpiringAlertInPlace(): void
    {
        SessionController::set('alert', ['message' => 'hi', 'type' => 'info', 'timeout' => 0]);

        new AlertController();

        $this->assertTrue(SessionController::has('alert'));
    }

    public function testConstructorRemovesAnExpiredAlert(): void
    {
        SessionController::set('alert', ['message' => 'hi', 'type' => 'info', 'timeout' => time() - 10]);

        new AlertController();

        $this->assertFalse(SessionController::has('alert'));
    }

    public function testConstructorKeepsAnAlertThatHasNotExpiredYet(): void
    {
        SessionController::set('alert', ['message' => 'hi', 'type' => 'info', 'timeout' => time() + 10]);

        new AlertController();

        $this->assertTrue(SessionController::has('alert'));
    }

    public function testConstructorDoesNothingWhenNoAlertIsSet(): void
    {
        new AlertController();

        $this->assertFalse(SessionController::has('alert'));
    }

    public function testPullReturnsAndConsumesANeverExpiringAlertOnANormalResponse(): void
    {
        SessionController::set('alert', ['message' => 'hi', 'type' => 'info', 'timeout' => 0]);

        $alert = AlertController::pull();

        $this->assertSame('hi', $alert['message']);
        $this->assertFalse(SessionController::has('alert'));
    }

    public function testPullKeepsANeverExpiringAlertWhileTheResponseIsARedirect(): void
    {
        SessionController::set('alert', ['message' => 'hi', 'type' => 'info', 'timeout' => 0]);
        http_response_code(302);

        $alert = AlertController::pull();

        $this->assertSame('hi', $alert['message']);
        $this->assertTrue(SessionController::has('alert'));
    }

    public function testPullNeverConsumesATimedAlert(): void
    {
        SessionController::set('alert', ['message' => 'hi', 'type' => 'info', 'timeout' => time() + 30]);

        AlertController::pull();

        $this->assertTrue(SessionController::has('alert'));
    }

    public function testPullReturnsNullWhenNoAlertIsSet(): void
    {
        $this->assertNull(AlertController::pull());
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        http_response_code(200);
    }
}
