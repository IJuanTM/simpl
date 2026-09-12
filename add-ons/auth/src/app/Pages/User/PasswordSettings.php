<?php

declare(strict_types=1);

namespace app\Pages\User;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Enums\AlertType;
use app\Enums\ErrorCode;
use app\Models\Page;
use app\Utils\RateLimiter;

/**
 * The /user/settings/change-password section: changes the signed-in user's password.
 */
class PasswordSettings
{
    public function __construct()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->changePassword();
    }

    /**
     * Processes the change-password form submission.
     *
     * @return void
     */
    private function changePassword(): void
    {
        if (
            !FormController::validate('old-password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH]) ||
            !FormController::validate('new-password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH]) ||
            !FormController::validate('new-password-check', ['required', 'maxLength' => MAX_PASSWORD_LENGTH])
        ) return;

        $user = SessionController::get('user');

        if (!AuthController::checkPassword($user['email'], $_POST['old-password'])) {
            // Only a wrong old password counts against the lockout.
            // A correct old password never burns a slot on some other field failing validation.
            if (!RateLimiter::attempt("change-password-{$user['id']}", LOCKOUT_CONFIG['change_password']['max_attempts'], LOCKOUT_CONFIG['change_password']['window_seconds'])) {
                FormController::addAlert('Too many incorrect attempts. Please wait a while before trying again.', AlertType::ERROR);
                return;
            }

            $_POST['old-password'] = '';
            FormController::addAlert('The old password is incorrect!', AlertType::WARNING);
            return;
        }

        if ($_POST['old-password'] === $_POST['new-password']) {
            FormController::addAlert('The new password is the same as the old password!', AlertType::WARNING);
            return;
        }

        if (!FormController::validatePasswords('new-password', 'new-password-check')) return;

        // Copied into this session's own cached user data too.
        // Otherwise requireAuth() would treat this very session as stale on its very next request.
        $user['password_changed_at'] = AuthController::updatePassword($user['id'], $_POST['new-password']);

        $user['must_change_password'] = 0;
        SessionController::set('user', $user);

        AuthController::intendedRedirect('profile', 'Success! Your password has been changed!', AlertType::SUCCESS, 4);
    }

    /**
     * This section serves no JSON endpoint.
     *
     * @param Page $page
     *
     * @return void
     */
    final public function api(Page $page): void
    {
        PageController::error(ErrorCode::NOT_FOUND);
    }
}
