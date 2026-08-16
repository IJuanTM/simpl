<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Enums\AlertType;
use app\Enums\TokenType;
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
        if ($this->initRateLimitedForm('forgot-password')) $this->post();
    }

    /**
     * Processes password reset request form submission.
     *
     * @return void
     */
    private function post(): void
    {
        if (!FormController::validate('email', ['required', 'maxLength' => MAX_EMAIL_LENGTH, 'type' => 'email'])) return;

        if (!$this->attemptRateLimit(RESEND_TIMEOUTS['password_reset'])) return;

        // Throttled per account too, so this form can't be used to email-bomb one victim from many IPs.
        $withinAccountLimit = RateLimiter::attempt('password-reset-account-' . hash('sha256', strtolower($_POST['email'])), PASSWORD_RESET_CONFIG['account_max_attempts'], PASSWORD_RESET_CONFIG['account_attempt_window']);

        // Timeboxed so the response takes the same time whether or not the email is registered.
        (new Timebox())->call(function () use ($withinAccountLimit) {
            if ($withinAccountLimit && AuthController::checkEmail($_POST['email'])) $this->sendPasswordReset($_POST['email']);
        }, TIMING_FLOOR_MS['password_reset'] * 1000);

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

        // createToken() replaces any existing token of the same type for this user.
        AuthController::createToken($id, hash('sha256', $token), TokenType::RESET, date('Y-m-d H:i:s', time() + PASSWORD_RESET_CONFIG['token_expiry']));

        AuthController::sendPasswordResetMail($id, $email, $token);
    }
}
