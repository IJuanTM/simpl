<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\SessionController;
use app\Enums\AlertType;

/**
 * ChangePasswordPage
 *
 * Allows authenticated users to change their password and clears the
 * must_change_password flag on success.
 */
class ChangePasswordPage
{
    public function __construct()
    {
        // Require authentication, allow access even if password change is required
        AuthController::requireAuth(null, true);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Processes password change form submission.
     *
     * @return void
     */
    private function post(): void
    {
        if (
            !FormController::validate('old-password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH]) ||
            !FormController::validate('new-password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH]) ||
            !FormController::validate('new-password-check', ['required', 'maxLength' => MAX_PASSWORD_LENGTH])
        ) return;

        $user = SessionController::get('user');

        if (!AuthController::checkPassword($user['email'], $_POST['old-password'])) {
            $_POST['old-password'] = '';
            FormController::addAlert('The old password is incorrect!', AlertType::WARNING);
            return;
        }

        if ($_POST['old-password'] === $_POST['new-password']) {
            FormController::addAlert('The new password is the same as the old password!', AlertType::WARNING);
            return;
        }

        if (!FormController::validatePasswords('new-password', 'new-password-check')) return;

        AuthController::updatePassword($user['id'], $_POST['new-password']);

        $user['must_change_password'] = 0;
        SessionController::set('user', $user);

        AuthController::intendedRedirect('profile', 'Success! Your password has been changed!', AlertType::SUCCESS, 4);
    }
}
