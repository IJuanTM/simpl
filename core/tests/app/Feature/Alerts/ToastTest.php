<?php

declare(strict_types=1);

namespace tests\Feature\Alerts;

use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Controllers\ToastController;
use app\Enums\AlertType;
use PHPUnit\Framework\TestCase;

final class ToastTest extends TestCase
{
    public function testAddStoresTheMessageTypeAndTimeout(): void
    {
        // Act
        ToastController::add('<b>hi</b>', AlertType::ERROR, 4);

        // Assert
        $this->assertSame([['message' => '<b>hi</b>', 'type' => 'error', 'timeout' => 4]], SessionController::get('toasts'));
    }

    public function testAddWithNoTimeoutStoresZero(): void
    {
        // Act
        ToastController::add('hi', AlertType::INFO);

        // Assert
        $this->assertSame(0, SessionController::get('toasts')[0]['timeout']);
    }

    public function testAddClampsANegativeTimeoutToZero(): void
    {
        // Act
        ToastController::add('hi', AlertType::INFO, -5);

        // Assert
        $this->assertSame(0, SessionController::get('toasts')[0]['timeout']);
    }

    public function testAddKeepsEarlierToastsInOrder(): void
    {
        // Act
        ToastController::add('first', AlertType::SUCCESS);
        ToastController::add('second', AlertType::WARNING);

        // Assert
        $this->assertSame(['first', 'second'], array_column(SessionController::get('toasts'), 'message'));
    }

    public function testPullReturnsAndConsumesEveryToastOnANormalResponse(): void
    {
        // Arrange
        ToastController::add('first', AlertType::SUCCESS, 4);
        ToastController::add('second', AlertType::INFO);

        // Act
        $toasts = ToastController::pull();

        // Assert
        $this->assertSame(['first', 'second'], array_column($toasts, 'message'));
        $this->assertFalse(SessionController::has('toasts'));
    }

    public function testPullKeepsTheToastsWhileTheResponseIsARedirect(): void
    {
        // Arrange
        ToastController::add('hi', AlertType::INFO, 4);
        http_response_code(302);

        // Act
        $toasts = ToastController::pull();

        // Assert
        $this->assertCount(1, $toasts);
        $this->assertTrue(SessionController::has('toasts'));
    }

    public function testPullReturnsAnEmptyListWhenNoToastIsQueued(): void
    {
        // Act + Assert
        $this->assertSame([], ToastController::pull());
    }

    public function testPageControllerRedirectWithToastWiresThroughToAdd(): void
    {
        // Act
        PageController::redirectWithToast('/somewhere', 'hi', AlertType::SUCCESS);

        // Assert
        $this->assertSame('hi', SessionController::get('toasts')[0]['message']);
        $this->assertSame(302, http_response_code());
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        http_response_code(200);
    }
}
