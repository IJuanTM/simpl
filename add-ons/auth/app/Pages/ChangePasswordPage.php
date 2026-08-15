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

        // Process password change form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Processes password change form submission.
     *
     * @return void
     */
    private function post(): void
    {
        // Validate form fields
        if (
            !FormController::validate('old-password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH]) ||
            !FormController::validate('new-password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH]) ||
            !FormController::validate('new-password-check', ['required', 'maxLength' => MAX_PASSWORD_LENGTH])
        ) return;

        $user = SessionController::get('user');

        // Verify old password is correct
        if (!AuthController::checkPassword($user['email'], $_POST['old-password'])) {
            $_POST['old-password'] = '';
            FormController::addAlert('The old password is incorrect!', AlertType::WARNING);
            return;
        }

        // Check if new password is same as old
        if ($_POST['old-password'] === $_POST['new-password']) {
            FormController::addAlert('The new password is the same as the old password!', AlertType::WARNING);
            return;
        }

        // Validate new password against policy and confirmation match
        if (!FormController::validatePasswords('new-password', 'new-password-check')) return;

        // Update password
        AuthController::updatePassword($user['id'], $_POST['new-password']);

        // Update session to clear must_change_password flag
        $user['must_change_password'] = 0;
        SessionController::set('user', $user);

        // Redirect the user
        AuthController::intendedRedirect('profile', 'Success! Your password has been changed!', AlertType::SUCCESS, 4);
    }
}
