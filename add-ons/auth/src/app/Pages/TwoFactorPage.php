<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Controllers\TwoFactorController;
use app\Enums\AlertType;
use app\Enums\ErrorCode;
use app\Enums\TwoFactorMethod;
use app\Models\Page;
use app\Models\Url;
use app\Utils\RateLimiter;
use JsonException;

/**
 * The login-time two-factor challenge (/two-factor). Reached only with a pending-login marker set by LoginPage::beginTwoFactorChallenge().
 * The prompt shown is the user's primary method; /two-factor/{email,totp,recovery} pick a specific one.
 */
class TwoFactorPage
{
    // Seconds a pending login may sit before the challenge is abandoned.
    private const int PENDING_TTL = 900;

    public int $userId;
    public string $mode = 'email';
    public int $resendCooldown = 0;

    /** @var string[] the user's enrolled method values, so the view can offer "try another way" */
    public array $enrolledMethods = [];

    public function __construct(Page $page)
    {
        $pending = SessionController::get('2fa_pending');

        if (!is_array($pending) || time() - ($pending['at'] ?? 0) > self::PENDING_TTL) {
            SessionController::remove('2fa_pending');
            PageController::redirectWithAlert('login', 'Your login session expired. Please sign in again.', AlertType::INFO, 4);
            exit;
        }

        $this->userId = (int)$pending['user_id'];
        $this->mode = $this->resolveMode($page->subpage());
        $this->enrolledMethods = array_map(static fn(TwoFactorMethod $m) => $m->value, TwoFactorController::enabledMethods($this->userId));
        $this->resendCooldown = RateLimiter::retryAfterMs('2fa-resend-' . $this->userId);

        if ($this->mode === 'email' && $_SERVER['REQUEST_METHOD'] === 'GET') $this->ensureEmailCode();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post($pending);
    }

    /**
     * Resolves the requested challenge mode, defaulting to the user's primary method.
     *
     * @param string|null $requested
     *
     * @return 'email'|'totp'|'passkey'|'recovery'
     */
    private function resolveMode(?string $requested): string
    {
        if (in_array($requested, ['email', 'totp', 'passkey', 'recovery'], true)) return $requested;

        return match (TwoFactorController::primaryMethod($this->userId)) {
            TwoFactorMethod::TOTP => 'totp',
            TwoFactorMethod::PASSKEY => 'passkey',
            default => 'email',
        };
    }

    /**
     * Sends an email code when the user is on the email prompt and none is already outstanding.
     *
     * @return void
     */
    private function ensureEmailCode(): void
    {
        if (TwoFactorController::hasPendingEmailChallenge($this->userId)) return;
        if (!RateLimiter::attempt('2fa-resend-' . $this->userId, 1, TWO_FACTOR_CONFIG['resend_cooldown'])) return;

        $user = AuthController::getUserById($this->userId);
        if ($user !== null) TwoFactorController::issueEmailChallenge($this->userId, $user['email']);
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

        $ok = match ($this->mode) {
            'recovery' => TwoFactorController::consumeRecoveryCode($this->userId, $code),
            'totp' => TwoFactorController::verifyTotp($this->userId, $code),
            default => TwoFactorController::verifyEmailChallenge($this->userId, $code),
        };

        if (!$ok) {
            $_POST['code'] = '';
            FormController::addAlert(match ($this->mode) {
                'recovery' => 'That recovery code is invalid or has already been used.',
                'totp' => 'That code is incorrect. Check your authenticator app and try again.',
                default => 'That code is incorrect or has expired.',
            }, AlertType::ERROR);
            return;
        }

        PageController::redirect($this->complete($pending));
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
     * @return string The route to send the user to
     *
     * @throws JsonException
     */
    private function complete(array $pending): string
    {
        SessionController::remove('2fa_pending');
        RateLimiter::clear("2fa-account-{$this->userId}");

        $user = AuthController::getUserWithRole($this->userId);

        if (!$user) {
            PageController::redirectWithAlert('login', AuthController::ACCOUNT_ISSUE_MESSAGE, AlertType::ERROR, 4);
            exit;
        }

        return AuthController::completeLogin($user, (bool)($pending['remember'] ?? false));
    }

    /**
     * Challenge endpoints under /api/two-factor/*: resend the email code, or run the passkey ceremony.
     * The constructor already validated the pending-login marker.
     *
     * @param Page $page
     *
     * @return void
     *
     * @throws JsonException
     */
    final public function api(Page $page): void
    {
        $pending = SessionController::get('2fa_pending');

        if (!is_array($pending)) {
            PageController::error(ErrorCode::FORBIDDEN);
            return;
        }

        match ($page->subpage()) {
            'resend' => $this->resendEmailCode(),
            'passkey' => match ($page->subpage(1)) {
                'options' => $this->passkeyChallengeOptions(),
                'verify' => $this->passkeyVerify($pending),
                default => PageController::error(ErrorCode::NOT_FOUND),
            },
            default => PageController::error(ErrorCode::NOT_FOUND),
        };
    }

    /**
     * Re-sends the login email code, rate-limited. Reached via /api/two-factor/resend.
     *
     * @return void
     */
    private function resendEmailCode(): void
    {
        if (!RateLimiter::attempt('2fa-resend-' . $this->userId, 1, TWO_FACTOR_CONFIG['resend_cooldown'])) {
            PageController::redirectWithAlert('two-factor', 'Please wait a moment before requesting another code.', AlertType::WARNING, 4);
            return;
        }

        $user = AuthController::getUserById($this->userId);
        if ($user !== null) TwoFactorController::issueEmailChallenge($this->userId, $user['email']);

        PageController::redirectWithAlert('two-factor', 'A new code has been sent to your email address.', AlertType::INFO, 4);
    }

    /**
     * Returns the navigator.credentials.get() options for a passkey challenge.
     *
     * @return void
     *
     * @throws JsonException
     */
    private function passkeyChallengeOptions(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'publicKey' => json_decode(TwoFactorController::passkeyLoginOptions($this->userId), true, flags: JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Validates a passkey assertion and, on success, completes the login. Responds with the URL for the browser to navigate to.
     *
     * @param array $pending
     *
     * @return void
     *
     * @throws JsonException
     */
    private function passkeyVerify(array $pending): void
    {
        if ($this->throttle()) {
            PageController::error(ErrorCode::TOO_MANY_REQUESTS);
            return;
        }

        $body = json_decode((string)file_get_contents('php://input'), true);

        if (!is_array($body) || !TwoFactorController::verifyPasskey($this->userId, json_encode($body, JSON_THROW_ON_ERROR))) {
            PageController::error(ErrorCode::BAD_REQUEST);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'redirect' => Url::to($this->complete($pending))], JSON_THROW_ON_ERROR);
    }
}
