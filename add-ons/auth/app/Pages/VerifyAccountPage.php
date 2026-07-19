<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AppController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\RequestController;
use app\Enums\AlertType;
use app\Models\Page;
use app\Utils\RateLimiter;

/**
 * VerifyAccountPage
 *
 * Handles account verification using a token supplied in the URL or via a
 * manual form. Validates input and provides user-facing feedback via alerts.
 */
class VerifyAccountPage
{
    public int $resendCooldown = 0;

    public function __construct(Page $page)
    {
        // Get and sanitize user ID from URL
        $id = AppController::sanitize($page->subpage() ?? '');

        // Validate user ID
        if (empty($id) || !is_numeric($id)) {
            FormController::addAlert('Undefined user id! Please check your mail.', AlertType::ERROR);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        $id = (int)$id;

        // Check if user exists
        if (!AuthController::exists($id)) {
            FormController::addAlert('We could not find your account! Please check your mail.', AlertType::ERROR);
            PageController::redirect(REDIRECT, 2);
            return;
        }

        // Get resend cooldown in milliseconds
        $this->resendCooldown = RateLimiter::retryAfterMs('resend-verification-' . $id);

        // Check if already verified
        if (AuthController::isVerified($id)) {
            FormController::addAlert('Your account has already been verified!', AlertType::INFO);
            PageController::redirect('login', 2);
            return;
        }

        // Get verification code from URL
        $code = AppController::sanitize($page->subpage(1) ?? '');

        // If code provided in URL, verify immediately
        if (!empty($code)) {
            // Validate code length
            if (strlen($code) > VERIFICATION_TOKEN_LENGTH) {
                FormController::addAlert('The verification code given in the url is too long!', AlertType::WARNING);
                return;
            }

            // Throttle brute-force attempts on the verification code
            if ($this->throttle($id)) return;

            // Verify code is correct
            if (!AuthController::checkToken($id, $code, 'verification')) {
                FormController::addAlert('The verification code given in the url is incorrect! Please check your mail.', AlertType::ERROR);
                return;
            }

            // Verify account
            $this->verify($id);
        }

        // Process manual verification form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post($id);
    }

    /**
     * Records a verification attempt, checking per-account then per-IP limits.
     *
     * @param int $id User ID being verified
     *
     * @return bool True when either attempt limit has been exceeded
     */
    private function throttle(int $id): bool
    {
        if (!RateLimiter::attempt("verify-attempt-account-$id", VERIFICATION_ACCOUNT_MAX_ATTEMPTS, VERIFICATION_ACCOUNT_ATTEMPT_WINDOW)) {
            FormController::addAlert('Too many verification attempts for this account. Please wait a while before trying again.', AlertType::ERROR);
            return true;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!RateLimiter::attempt("verify-attempt-ip-$ip", VERIFICATION_IP_MAX_ATTEMPTS, VERIFICATION_IP_ATTEMPT_WINDOW)) {
            FormController::addAlert('Too many verification attempts. Please wait a while before trying again.', AlertType::ERROR);
            return true;
        }

        return false;
    }

    /**
     * Completes account verification by deleting verification token.
     *
     * @param int $id User ID
     *
     * @return void
     */
    private function verify(int $id): void
    {
        // Remove verification token from database to mark account as verified
        AuthController::deleteToken($id, 'verification');

        // Redirect to login with success message
        PageController::redirect('login');
        AlertController::globalAlert('Success! Your account has been verified!', AlertType::SUCCESS, 4);
    }

    /**
     * Handles manual verification code submission from a form.
     *
     * @param int $userId User ID
     *
     * @return void
     */
    private function post(int $userId): void
    {
        $code = RequestController::rawPost('code');

        // Ensure a code was entered
        if (empty($code)) {
            FormController::addAlert('Please enter the verification code received in your mail!', AlertType::WARNING);
            return;
        }

        // Validate code length
        if (strlen($code) > VERIFICATION_TOKEN_LENGTH) {
            FormController::addAlert('The verification code is too long!', AlertType::WARNING);
            return;
        }

        // Throttle brute-force attempts on the verification code
        if ($this->throttle($userId)) return;

        // Verify code is correct
        if (!AuthController::checkToken($userId, $code, 'verification')) {
            FormController::addAlert('The verification code is incorrect!', AlertType::ERROR);
            return;
        }

        // Complete verification
        $this->verify($userId);
    }
}
