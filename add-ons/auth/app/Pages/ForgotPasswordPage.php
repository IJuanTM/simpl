<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\SessionController;
use app\Enums\AlertType;

/**
 * ForgotPasswordPage
 *
 * Handles requests for password reset tokens and enforces resend timeouts.
 */
class ForgotPasswordPage
{
    public function __construct()
    {
        // Process forgot password form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Processes password reset request form submission.
     *
     * @return void
     */
    private function post(): void
    {
        // Check if timeout has not been exceeded
        if (SessionController::get('resend-timeout') !== null && SessionController::get('resend-timeout') > time()) {
            FormController::addAlert('Please wait a moment before trying again!', AlertType::WARNING);
            return;
        }

        // Validate form fields
        if (!FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email'])) return;

        // Check if email exists
        if (!AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = '';
            FormController::addAlert('An account with this email does not exist!', AlertType::WARNING);
            return;
        }

        // Send reset email
        $this->sendPasswordReset($_POST['email']);

        // Set timeout to prevent spam
        SessionController::set('resend-timeout', time() + PASSWORD_RESET_RESEND_TIMEOUT);
    }

    /**
     * Generates reset token and sends reset email.
     *
     * @param string $email User email
     *
     * @return void
     */
    private function sendPasswordReset(string $email): void
    {
        // Get user ID
        $id = AuthController::getUserIdByEmail($email);

        // Generate reset token
        $token = AuthController::generateToken();

        // Store token in database (removes any existing reset token)
        AuthController::createToken($id, $token, 'reset');

        // Send reset email
        AuthController::sendPasswordResetMail($id, $email, $token);
    }
}
