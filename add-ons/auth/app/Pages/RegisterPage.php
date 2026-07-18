<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Database\DB;
use app\Enums\AlertType;

/**
 * RegisterPage
 *
 * Handles new account creation and optional email verification flow.
 */
class RegisterPage
{
    public function __construct()
    {
        // Process registration form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Processes registration form submission.
     *
     * @return void
     */
    private function post(): void
    {
        // Validate form fields
        if (
            !FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email']) ||
            !FormController::validate('password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH]) ||
            !FormController::validate('password-check', ['required', 'maxLength' => MAX_PASSWORD_LENGTH])
        ) return;

        // Check if email already exists
        if (AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = '';
            FormController::addAlert('An account with this email already exists! Try logging in!', AlertType::WARNING);
            return;
        }

        // Validate password against policy
        if (!FormController::validatePasswords('password', 'password-check')) return;

        // Create account
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
        // Insert new user into database
        DB::insert(
            'users',
            [
                'email' => $email,
                'password' => password_hash($password, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS)
            ]
        );

        // Get new user ID
        $id = AuthController::getUserIdByEmail($email);

        // Assign default user role
        DB::insert(
            'user_roles',
            [
                'user_id' => $id
            ]
        );

        // Handle email verification if required
        if (EMAIL_VERIFICATION_REQUIRED) {
            // Generate verification token
            $token = AuthController::generateToken(VERIFICATION_TOKEN_LENGTH);

            // Store token in database
            AuthController::createToken($id, $token, 'verification');

            // Send verification email
            AuthController::sendVerificationMail($id, $email, $token);
        } else {
            // No verification required, redirect to login
            PageController::redirect('login');
            AlertController::globalAlert('Success! Your account has been created!', AlertType::SUCCESS, 4);
        }
    }
}
