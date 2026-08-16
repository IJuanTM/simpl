<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\TokenType;

/**
 * LogoutPage
 *
 * API endpoint to log the current user out by clearing session and remember cookie.
 * Only responds to CSRF-protected POST requests to prevent forced logout via GET.
 */
class LogoutPage
{
    /**
     * Logs out user by clearing session, remember cookie and the stored remember token.
     *
     * @return void
     *
     * @api
     */
    final public function api(): void
    {
        // Only allow logout via POST; the CSRF check is enforced centrally in PageController.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            PageController::redirect(REDIRECT);
            return;
        }

        // Invalidate the persistent remember-me token in the database so a captured token cannot be reused.
        if (isset($_COOKIE['remember'])) DB::delete(
            FROM: 'tokens',
            WHERE: [
                'token' => hash('sha256', $_COOKIE['remember']),
                'type' => TokenType::REMEMBER->value
            ]
        );

        SessionController::remove('user');

        // Clear the remember cookie using the same flags it was set with
        AuthController::clearRememberCookie();

        // Rotate the session id so the now-anonymous session cannot reuse the authenticated one
        session_regenerate_id(true);

        PageController::redirectWithAlert(REDIRECT, 'You have been logged out.', AlertType::SUCCESS, 4);
    }
}
