<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Enums\AlertType;
use app\Pages\Traits\RateLimitedForm;
use app\Utils\RateLimiter;
use app\Utils\Timebox;

/**
 * ForgotPasswordPage
 *
 * Handles requests for password reset tokens and enforces resend timeouts.
 */
class ForgotPasswordPage
{
    use RateLimitedForm;

    public function __construct()
    {
        // Process forgot password form submission
        if ($this->initRateLimitedForm('forgot-password')) $this->post();
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

        if (!$this->attemptRateLimit(PASSWORD_RESET_RESEND_TIMEOUT)) return;

        // Throttled per account too, so this form can't be used to email-bomb one victim from many IPs.
        $withinAccountLimit = RateLimiter::attempt('password-reset-account-' . hash('sha256', strtolower($_POST['email'])), PASSWORD_RESET_ACCOUNT_MAX_ATTEMPTS, PASSWORD_RESET_ACCOUNT_ATTEMPT_WINDOW);

        // Timeboxed so the response takes the same time whether or not the email is registered.
        (new Timebox())->call(function () use ($withinAccountLimit) {
            if ($withinAccountLimit && AuthController::checkEmail($_POST['email'])) $this->sendPasswordReset($_POST['email']);
        }, PASSWORD_RESET_TIMING_FLOOR_MS * 1000);

        FormController::addAlert("If that email is registered, we've sent a link to reset your password!", AlertType::SUCCESS);
    }

    /**
     * Generates reset token and sends reset email. Silently does nothing if token
     * generation fails - the caller always shows the same generic response either way.
     *
     * @param string $email User email
     *
     * @return void
     */
    private function sendPasswordReset(string $email): void
    {
        $id = AuthController::getUserIdByEmail($email);
        $token = AuthController::generateToken();

        if ($token === null) return;

        // Store token in database (removes any existing reset token)
        AuthController::createToken($id, $token, 'reset', date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRY));

        // Send reset email
        AuthController::sendPasswordResetMail($id, $email, $token);
    }
}
