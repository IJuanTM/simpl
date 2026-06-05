<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AlertController;
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

        // Verify old password is correct
        if (!AuthController::checkPassword(SessionController::get('user')['email'], $_POST['old-password'])) {
            $_POST['old-password'] = '';
            FormController::addAlert('The old password is incorrect!', AlertType::WARNING);
            return;
        }

        // Validate new password against policy
        if (!AuthController::validatePassword($_POST['new-password'])) {
            $_POST['new-password'] = '';
            $_POST['new-password-check'] = '';
            return;
        }

        // Check if new password is same as old
        if ($_POST['old-password'] === $_POST['new-password']) {
            FormController::addAlert('The new password is the same as the old password!', AlertType::WARNING);
            return;
        }

        // Check if new passwords match
        if ($_POST['new-password'] !== $_POST['new-password-check']) {
            FormController::addAlert('The newly entered passwords do not match!', AlertType::WARNING);
            return;
        }

        // Update password
        $userId = SessionController::get('user')['id'];
        AuthController::updatePassword($userId, $_POST['new-password']);

        // Update session to clear must_change_password flag
        $user = SessionController::get('user');
        $user['must_change_password'] = 0;
        SessionController::set('user', $user);

        // Redirect the user
        AuthController::intendedRedirect('profile');
        AlertController::globalAlert('Success! Your password has been changed!', AlertType::SUCCESS, 4);
    }
}
