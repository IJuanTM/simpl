<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Enums\AlertType;
use app\Utils\RateLimiter;

/**
 * ForgotPasswordPage
 *
 * Handles requests for password reset tokens and enforces resend timeouts.
 */
class ForgotPasswordPage
{
    public int $resendCooldown = 0;
    private string $rlKey;

    public function __construct()
    {
        // Scope the rate limit to the client IP so it cannot be reset by clearing cookies.
        $this->rlKey = 'forgot-password-' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $this->resendCooldown = RateLimiter::retryAfterMs($this->rlKey);

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
        // Validate form fields
        if (!FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email'])) return;

        // Rate limit after validation, before email-existence check to prevent enumeration
        if (!RateLimiter::attempt($this->rlKey, 1, PASSWORD_RESET_RESEND_TIMEOUT)) {
            FormController::addAlert('Please wait a moment before trying again!', AlertType::WARNING);
            return;
        }

        // Check if email exists
        if (!AuthController::checkEmail($_POST['email'])) {
            $_POST['email'] = '';
            FormController::addAlert('An account with this email does not exist!', AlertType::WARNING);
            return;
        }

        $this->sendPasswordReset($_POST['email']);
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
