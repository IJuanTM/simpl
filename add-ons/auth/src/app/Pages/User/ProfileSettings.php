<?php

declare(strict_types=1);

namespace app\Pages\User;

use app\Controllers\AppController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\ErrorCode;
use app\Models\Page;
use app\Utils\RateLimiter;

/**
 * The /user/settings/profile section: edits the signed-in user's own name, email and avatar.
 * The avatar upload itself posts to UserPage::api(); this class owns only the text fields.
 */
class ProfileSettings
{
    public array $user = [];
    public ?string $profileImage = null;
    private int $userId;

    public function __construct()
    {
        $this->userId = (int)SessionController::get('user')['id'];
        $this->user = AuthController::getUserWithRole($this->userId) ?? [];
        $this->profileImage = AuthController::getProfileImage($this->userId);

        foreach (['username', 'first_name', 'last_name', 'email'] as $field) {
            if (($this->user[$field] ?? null) !== null) $this->user[$field] = AppController::sanitize($this->user[$field]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->updateProfile();
    }

    /**
     * Validates and saves the profile fields submitted by the owner.
     *
     * @return void
     */
    private function updateProfile(): void
    {
        if (
            !FormController::validate('username', ['maxLength' => MAX_USERNAME_LENGTH]) ||
            !FormController::validate('first_name', ['maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('last_name', ['maxLength' => MAX_NAME_LENGTH]) ||
            !FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email'])
        ) return;

        $id = $this->userId;

        $currentEmail = DB::single(SELECT: 'email', FROM: 'users', WHERE: compact('id'))['email'] ?? null;
        $emailChanged = $currentEmail !== null && $_POST['email'] !== $currentEmail;

        // Email changes are sensitive, so the current password stops a hijacked session from redirecting password resets.
        if ($emailChanged) {
            if (!FormController::validate('current-password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH])) return;

            if (!RateLimiter::attempt("change-email-{$id}", LOCKOUT_CONFIG['change_email']['max_attempts'], LOCKOUT_CONFIG['change_email']['window_seconds'])) {
                FormController::addAlert('Too many incorrect attempts. Please wait a while before trying again.', AlertType::ERROR);
                return;
            }

            if (!AuthController::checkPassword($currentEmail, $_POST['current-password'])) {
                $_POST['current-password'] = '';
                FormController::addAlert('Your current password is incorrect!', AlertType::WARNING);
                return;
            }
        }

        if ($_POST['username'] !== '' && AuthController::usernameTakenByOtherUser($_POST['username'], $id)) {
            $_POST['username'] = $this->user['username'] ?? '';
            FormController::addAlert('That username is already taken!', AlertType::WARNING);
            return;
        }

        if (AuthController::emailTakenByOtherUser($_POST['email'], $id)) {
            $_POST['email'] = $this->user['email'];
            FormController::addAlert('An account with this email already exists!', AlertType::WARNING);
            return;
        }

        DB::update(
            UPDATE: 'users',
            SET: [
                'username' => $_POST['username'] ?: null,
                'first_name' => $_POST['first_name'] ?: null,
                'last_name' => $_POST['last_name'] ?: null,
                'email' => $_POST['email'],
            ],
            WHERE: compact('id')
        );

        // requireAuth()'s session sync ran before this request's own update, so patch the edited fields in now or the nav bar stays stale until the next request.
        // Only these fields, never a full row refresh, since that would mask a stale status/password_changed_at from requireAuth().
        $sessionUser = SessionController::get('user');
        $sessionUser['username'] = $_POST['username'] ?: null;
        $sessionUser['first_name'] = $_POST['first_name'] ?: null;
        $sessionUser['last_name'] = $_POST['last_name'] ?: null;
        $sessionUser['email'] = $_POST['email'];
        SessionController::set('user', $sessionUser);

        if (VERIFICATION_CONFIG['required'] && $emailChanged) {
            AuthController::issueVerificationToken($id, $_POST['email']);
            PageController::redirectWithAlert('user/' . $id, 'Profile updated! Please check your new email address to verify it.', AlertType::SUCCESS, 6);
            return;
        }

        PageController::redirectWithAlert('user/' . $id, 'Profile updated successfully!', AlertType::SUCCESS, 4);
    }

    /**
     * This section serves no JSON endpoint; the avatar upload is handled by UserPage::api().
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
