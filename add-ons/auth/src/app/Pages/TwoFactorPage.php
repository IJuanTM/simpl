<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Controllers\TwoFactorController;
use app\Enums\AlertType;
use app\Models\Page;
use app\Utils\RateLimiter;
use JsonException;

/**
 * The login-time two-factor challenge (/two-factor). Reached only with a pending-login marker set by LoginPage::beginTwoFactorChallenge().
 * Shows the email code prompt, or the recovery-code prompt at /two-factor/recovery.
 */
class TwoFactorPage
{
    // Seconds a pending login may sit before the challenge is abandoned.
    private const int PENDING_TTL = 900;

    public int $userId;
    public string $mode = 'code';
    public int $resendCooldown = 0;

    public function __construct(Page $page)
    {
        $pending = SessionController::get('2fa_pending');

        if (!is_array($pending) || time() - ($pending['at'] ?? 0) > self::PENDING_TTL) {
            SessionController::remove('2fa_pending');
            PageController::redirectWithAlert('login', 'Your login session expired. Please sign in again.', AlertType::INFO, 4);
            exit;
        }

        $this->userId = (int)$pending['user_id'];
        $this->mode = $page->subpage() === 'recovery' ? 'recovery' : 'code';
        $this->resendCooldown = RateLimiter::retryAfterMs('2fa-resend-' . $this->userId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post($pending);
    }

    /**
     * Validates the submitted code and, on success, completes the login.
     *
     * @param array $pending The 2fa_pending session marker
     *
     * @return void
     */
    private function post(array $pending): void
    {
        $code = trim((string)($_POST['code'] ?? ''));

        if ($code === '') {
            FormController::addAlert('Please enter your code.', AlertType::WARNING);
            return;
        }

        if ($this->throttle()) return;

        $ok = $this->mode === 'recovery'
            ? TwoFactorController::consumeRecoveryCode($this->userId, $code)
            : TwoFactorController::verifyEmailChallenge($this->userId, $code);

        if (!$ok) {
            $_POST['code'] = '';
            FormController::addAlert($this->mode === 'recovery' ? 'That recovery code is invalid or has already been used.' : 'That code is incorrect or has expired.', AlertType::ERROR);
            return;
        }

        $this->complete($pending);
    }

    /**
     * Two-tier wrong-code throttle: per-account backoff, then a per-IP cap. Mirrors VerifyAccountPage::throttle().
     *
     * @return bool True when a limit was hit and an alert has been queued
     */
    private function throttle(): bool
    {
        if (!RateLimiter::attemptWithBackoff("2fa-account-{$this->userId}", TWO_FACTOR_CONFIG['challenge_max_attempts'], TWO_FACTOR_CONFIG['challenge_attempt_window'], TWO_FACTOR_CONFIG['challenge_min_lockout'], TWO_FACTOR_CONFIG['challenge_max_lockout'])) {
            FormController::addAlert('Too many attempts. Please wait a while before trying again.', AlertType::ERROR);
            return true;
        }

        if (!RateLimiter::attempt(RateLimiter::ipKey('2fa-ip'), TWO_FACTOR_CONFIG['challenge_ip_max_attempts'], TWO_FACTOR_CONFIG['challenge_ip_window'])) {
            FormController::addAlert('Too many attempts. Please wait a while before trying again.', AlertType::ERROR);
            return true;
        }

        return false;
    }

    /**
     * Clears the pending marker and the account throttle, then finishes the login.
     *
     * @param array $pending
     *
     * @return void
     *
     * @throws JsonException
     */
    private function complete(array $pending): void
    {
        SessionController::remove('2fa_pending');
        RateLimiter::clear("2fa-account-{$this->userId}");

        $user = AuthController::getUserWithRole($this->userId);

        if (!$user) {
            PageController::redirectWithAlert('login', AuthController::ACCOUNT_ISSUE_MESSAGE, AlertType::ERROR, 4);
            exit;
        }

        AuthController::completeLogin($user, (bool)($pending['remember'] ?? false));
    }

    /**
     * Re-sends the email code, rate-limited. Only reached via /api/two-factor/resend.
     *
     * @param Page $page
     *
     * @return void
     */
    final public function api(Page $page): void
    {
        $pending = SessionController::get('2fa_pending');

        if (!is_array($pending) || $page->subpage() !== 'resend') {
            PageController::redirect('login');
            return;
        }

        $userId = (int)$pending['user_id'];

        if (!RateLimiter::attempt('2fa-resend-' . $userId, 1, TWO_FACTOR_CONFIG['resend_cooldown'])) {
            PageController::redirectWithAlert('two-factor', 'Please wait a moment before requesting another code.', AlertType::WARNING, 4);
            return;
        }

        $user = AuthController::getUserById($userId);
        if ($user !== null) TwoFactorController::issueEmailChallenge($userId, $user['email']);

        PageController::redirectWithAlert('two-factor', 'A new code has been sent to your email address.', AlertType::INFO, 4);
    }
}
