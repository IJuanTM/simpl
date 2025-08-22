<?php

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Enums\AlertType;

/**
 * Handles user logout functionality.
 */
class LogoutPage
{
    /**
     * Processes logout request, clears session and redirects user.
     */
    final public function api(): void
    {
        // Unset the user session
        SessionController::remove('user');

        // Unset the remember cookie
        setcookie('remember', '', time() - 3600, '/');

        // Redirect the user to the redirect page with a success message
        PageController::redirect(REDIRECT);
        AlertController::alert('You have been logged out.', AlertType::SUCCESS, 4);
    }
}
