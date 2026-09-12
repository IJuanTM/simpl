<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Controllers\TwoFactorController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\TwoFactorMethod;
use app\Enums\UserStatus;
use app\Utils\RateLimiter;

/**
 * Handles the login form flow: lockout checks, credential verification, and either a two-factor challenge or the completed login.
 */
class LoginPage
{
    public function __construct()
    {
        if ($this->checkLockedOut()) return;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Checks if user or IP address is locked out due to failed login attempts.
     * The message deliberately doesn't say whether the lockout is account-based or IP-based, so it can't be used to confirm an identifier maps to a real account.
     *
     * @param string|null $identifier Username/email for fresh lockout check
     * @param int|null    $userId     Pre-resolved user id, to skip a redundant lookup
     *
     * @return bool True if locked out
     */
    private function checkLockedOut(?string $identifier = null, ?int $userId = null): bool
    {
        if ($identifier === null) {
            $timeout = SessionController::get('lockout-timeout');

            if ($timeout && $timeout > time()) {
                $this->showLockoutAlert($timeout - time());
                return true;
            }

            SessionController::remove('lockout-timeout');
            return false;
        }

        $seconds = $this->lockOutSeconds($identifier, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $userId);

        if ($seconds > 0) {
            SessionController::set('lockout-timeout', time() + $seconds);
            $this->showLockoutAlert($seconds);
            return true;
        }

        return false;
    }

    /**
     * Queues the lockout alert with a countdown timeout matching the lockout duration.
     *
     * @param int $seconds Seconds remaining on the lockout
     *
     * @return void
     */
    private function showLockoutAlert(int $seconds): void
    {
        $minutes = (int)ceil($seconds / 60);
        FormController::addAlert("Too many failed attempts. Please wait $minutes minute(s) before trying again.", AlertType::ERROR, $seconds * 1000);
    }

    /**
     * Calculates remaining lockout duration for user and IP address, whichever is longer.
     *
     * @param string   $identifier Username or email
     * @param string   $ip         IP address
     * @param int|null $userId     Pre-resolved user id, to skip a redundant lookup
     *
     * @return int Seconds remaining on the lockout, or 0 when not locked out
     */
    private function lockOutSeconds(string $identifier, string $ip, ?int $userId = null): int
    {
        $userId ??= AuthController::getUserIdByIdentifier($identifier);

        $userSeconds = $userId !== null
            ? max(0, ($this->calculateLockout('user_id', $userId, LOCKOUT_CONFIG['user']) ?? 0) - time())
            : 0;

        $ipSeconds = max(0, ($this->calculateLockout('ip_address', $ip, LOCKOUT_CONFIG['ip']) ?? 0) - time());

        return max($userSeconds, $ipSeconds);
    }

    /**
     * Calculates lockout end timestamp based on failed attempts.
     *
     * @param string                                                                                              $column Database column to query (user_id or ip_address)
     * @param mixed                                                                                               $value  Value to match
     * @param array{max_attempts: int, min_duration_minutes: int, max_duration_minutes: int, window_minutes: int} $config
     *
     * @return int|null Lockout end timestamp or null if not locked
     */
    private function calculateLockout(string $column, mixed $value, array $config): ?int
    {
        // Rows are newest-first, and only those within one window of the newest are ever counted below.
        // A generous multiple of max_attempts is more than enough to cover that window, regardless of total attempt history.
        $rows = DB::select(
            SELECT: "UNIX_TIMESTAMP(CONVERT_TZ(attempt_time, @@session.time_zone, '+00:00')) AS ts",
            FROM: 'login_attempts',
            WHERE: [
                $column => $value,
                'success' => 0
            ],
            ORDER_BY: 'attempt_time DESC',
            LIMIT: $config['max_attempts'] * 10
        );

        if (!$rows) return null;

        $timestamps = array_map(static fn($r) => (int)$r['ts'], $rows);
        $newest = $timestamps[0];

        // Rows are sorted newest-first, so the first attempt outside the window means every remaining one is too, so it's safe to stop counting there.
        $count = 0;
        foreach ($timestamps as $ts) {
            if (($newest - $ts) <= $config['window_minutes'] * 60) $count++;
            else break;
        }

        $blocks = (int)floor($count / $config['max_attempts']);
        if ($blocks === 0) return null;

        // Exponential backoff: duration doubles per completed threshold, capped at max_duration_minutes.
        return $newest + (min($config['min_duration_minutes'] * (2 ** ($blocks - 1)), $config['max_duration_minutes']) * 60);
    }

    /**
     * Processes login form submission.
     *
     * @return void
     */
    private function post(): void
    {
        if (
            !FormController::validate('identifier', ['required', 'maxLength' => MAX_EMAIL_LENGTH]) ||
            !FormController::validate('password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH])
        ) return;

        // Resolved once and threaded through below, instead of each check re-resolving it.
        $userId = AuthController::getUserIdByIdentifier($_POST['identifier']);

        if ($this->checkLockedOut($_POST['identifier'], $userId)) return;

        // Timing-safe regardless of whether the identifier resolves (see AuthController::verifyCredentials).
        $user = AuthController::verifyCredentials($_POST['identifier'], $_POST['password']);

        if ($user === null) {
            $this->fail('incorrect', 'Invalid username/email or password. Please try again.', AlertType::WARNING, $userId);
            return;
        }

        if ($user['status'] !== UserStatus::ACTIVE->value) {
            $this->fail('inactive', AuthController::ACCOUNT_ISSUE_MESSAGE, AlertType::ERROR, (int)$user['id']);
            return;
        }

        if (VERIFICATION_CONFIG['required'] && !AuthController::isVerified((int)$user['id'])) {
            $this->fail('unverified', AuthController::ACCOUNT_ISSUE_MESSAGE, AlertType::ERROR, (int)$user['id']);
            return;
        }

        if (
            TWO_FACTOR_CONFIG['enabled']
            && TwoFactorController::isEnabledFor((int)$user['id'])
            && !TwoFactorController::deviceIsTrusted($user)
        ) {
            $this->beginTwoFactorChallenge($user);
            return;
        }

        PageController::redirect(AuthController::completeLogin($user, isset($_POST['remember'])));
    }

    /**
     * Records a failed login attempt, checks whether it just triggered a lockout
     * (which shows its own alert), and otherwise clears the submitted credentials
     * and shows the given alert.
     *
     * @param string    $reason  Failure reason recorded in the login_attempts log
     * @param string    $message Alert message to show when this didn't trigger a lockout
     * @param AlertType $type    Alert type for $message
     * @param int|null  $userId  Pre-resolved user id, to skip redundant lookups
     *
     * @return void
     */
    private function fail(string $reason, string $message, AlertType $type, ?int $userId = null): void
    {
        AuthController::recordLoginAttempt($_POST['identifier'], false, $reason, $userId);

        // A fresh lockout alert takes priority over the generic failure message
        if ($this->checkLockedOut($_POST['identifier'], $userId)) return;

        $_POST['identifier'] = '';
        $_POST['password'] = '';
        FormController::addAlert($message, $type);
    }

    /**
     * Stashes a short-lived pending-login marker and sends the user to the two-factor challenge.
     * The credential login attempt is only recorded once the challenge passes (in completeLogin).
     *
     * @param array $user Verified user row from verifyCredentials()
     *
     * @return void
     */
    private function beginTwoFactorChallenge(array $user): void
    {
        $userId = (int)$user['id'];

        SessionController::set('2fa_pending', [
            'user_id' => $userId,
            'remember' => isset($_POST['remember']),
            'at' => time(),
        ]);

        // Shares the resend cooldown so re-submitting the login form can't trigger a burst of codes.
        if (
            TwoFactorController::primaryMethod($userId) === TwoFactorMethod::EMAIL
            && RateLimiter::attempt('2fa-resend-' . $userId, 1, TWO_FACTOR_CONFIG['resend_cooldown'])
        ) {
            TwoFactorController::issueEmailChallenge($userId, $user['email']);
        }

        PageController::redirect('two-factor');
    }
}
