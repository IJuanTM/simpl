<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Enums\AlertType;

/**
 * LogoutPage
 *
 * API endpoint to log the current user out by clearing session and remember cookie.
 */
class LogoutPage
{
    /**
     * Logs out user by clearing session and remember cookie.
     *
     * @return void
     *
     * @api
     */
    final public function api(): void
    {
        // Remove user from session
        SessionController::remove('user');

        // Clear remember cookie
        setcookie('remember', '', time() - 3600, '/');

        // Redirect to home with logout message
        PageController::redirect(REDIRECT);
        AlertController::globalAlert('You have been logged out.', AlertType::SUCCESS, 4);
    }
}
