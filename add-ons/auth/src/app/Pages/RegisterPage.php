<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\Role;
use app\Pages\Traits\RateLimitedForm;
use app\Utils\Log;

/**
 * RegisterPage
 *
 * Handles new account creation and optional email verification flow.
 */
class RegisterPage
{
    use RateLimitedForm;

    public function __construct()
    {
        if ($this->initRateLimitedForm('register')) $this->post();
    }

    /**
     * Processes registration form submission.
     *
     * @return void
     */
    private function post(): void
    {
        if (
            !FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email']) ||
            !FormController::validate('password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH]) ||
            !FormController::validate('password-check', ['required', 'maxLength' => MAX_PASSWORD_LENGTH])
        ) return;

        // Rate limit after validation, before email-existence check to prevent enumeration
        if (!$this->attemptRateLimit(REGISTER_RESEND_TIMEOUT)) return;

        if (AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = '';
            FormController::addAlert('An account with this email already exists! Try logging in!', AlertType::WARNING);
            return;
        }

        if (!FormController::validatePasswords('password', 'password-check')) return;

        $this->register($_POST['email'], $_POST['password']);
    }

    /**
     * Creates new user account and sends verification email if required.
     *
     * @param string $email    User email
     * @param string $password User password (will be hashed)
     *
     * @return void
     */
    private function register(string $email, string $password): void
    {
        $roleId = DB::single(
            SELECT: 'id',
            FROM: 'roles',
            WHERE: ['name' => Role::USER->value]
        )['id'] ?? null;

        if ($roleId === null) {
            Log::error('Default user role "' . Role::USER->value . '" is missing. Did you run the database seeder?');
            FormController::addAlert('An error occurred while creating your account. Please contact support.', AlertType::ERROR);
            return;
        }

        DB::insert(
            'users',
            [
                'email' => $email,
                'password' => password_hash($password, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS)
            ]
        );

        $id = AuthController::getUserIdByEmail($email);

        DB::insert(
            'user_roles',
            [
                'user_id' => $id,
                'role_id' => $roleId
            ]
        );

        if (EMAIL_VERIFICATION_REQUIRED) {
            $result = AuthController::issueVerificationToken((int)$id, $email);
            if ($result) PageController::redirectWithAlert("verify-account/$id", 'Success! Your account has been created! A verification email has been sent!', AlertType::SUCCESS, 4);
            else PageController::redirectWithAlert("verify-account/$id", 'Your account has been created! However, there was an issue sending the verification email. Please contact support.', AlertType::ERROR, 8);
        } else {
            PageController::redirectWithAlert('login', 'Success! Your account has been created!', AlertType::SUCCESS, 4);
        }
    }
}
