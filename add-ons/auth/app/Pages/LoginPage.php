<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\UserStatus;

/**
 * LoginPage
 *
 * Handles the login form flow including lockout checks, credential
 * verification, and optional remember-me token creation.
 */
class LoginPage
{
    public function __construct()
    {
        // Check if user is currently locked out
        if ($this->checkLockedOut()) return;

        // Process login form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    /**
     * Checks if user or IP address is locked out due to failed login attempts.
     * The message deliberately doesn't say whether the lockout is account- or
     * IP-based, so it can't be used to confirm an identifier maps to a real account.
     *
     * @param string|null $identifier Username/email for fresh lockout check
     * @param int|null    $userId     Pre-resolved user id, to skip a redundant lookup
     *
     * @return bool True if locked out
     */
    private function checkLockedOut(string|null $identifier = null, ?int $userId = null): bool
    {
        // When no identifier provided, check existing session lockout
        if ($identifier === null) {
            $timeout = SessionController::get('lockout-timeout');

            // Check if there is an active lockout
            if ($timeout && $timeout > time()) {
                $this->showLockoutAlert($timeout - time());
                return true;
            }

            // Lockout has expired, remove from session
            SessionController::remove('lockout-timeout');
            return false;
        }

        // Check lockout status for provided identifier and IP
        $seconds = $this->lockOutSeconds($identifier, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $userId);

        if ($seconds > 0) {
            // Set lockout timeout in session
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

        // Check user-based lockout first
        if ($userId !== null) {
            $seconds = max(0, ($this->calculateLockout('user_id', $userId, USER_LOGIN_ATTEMPTS, MIN_USER_LOCKOUT_DURATION, MAX_USER_LOCKOUT_DURATION, USER_LOCKOUT_WINDOW) ?? 0) - time());
            if ($seconds > 0) return $seconds;
        }

        // Check IP-based lockout
        return max(0, ($this->calculateLockout('ip_address', $ip, IP_LOGIN_ATTEMPTS, MIN_IP_LOCKOUT_DURATION, MAX_IP_LOCKOUT_DURATION, IP_LOCKOUT_WINDOW) ?? 0) - time());
    }

    /**
     * Calculates lockout end timestamp based on failed attempts.
     *
     * @param string $column    Database column to query (user_id or ip_address)
     * @param mixed  $value     Value to match
     * @param int    $threshold Number of attempts before lockout
     * @param int    $base      Base lockout duration in minutes
     * @param int    $max       Maximum lockout duration in minutes
     * @param int    $window    Time window in minutes to count attempts
     *
     * @return int|null Lockout end timestamp or null if not locked
     */
    private function calculateLockout(string $column, mixed $value, int $threshold, int $base, int $max, int $window): ?int
    {
        // Fetch failed login attempts within time window
        $rows = DB::select(
            SELECT: "UNIX_TIMESTAMP(CONVERT_TZ(attempt_time, @@session.time_zone, '+00:00')) AS ts",
            FROM: 'login_attempts',
            WHERE: [
                $column => $value,
                'success' => 0
            ],
            ORDER_BY: 'attempt_time DESC'
        );

        if (!$rows) return null;

        // Extract timestamps
        $timestamps = array_map(static fn($r) => (int)$r['ts'], $rows);
        $newest = $timestamps[0];

        // Count attempts within time window
        $count = 0;
        foreach ($timestamps as $ts) {
            if (($newest - $ts) <= $window * 60) $count++;
            else break;
        }

        // Calculate number of lockout blocks
        $blocks = (int)floor($count / $threshold);
        if ($blocks === 0) return null;

        // Calculate lockout end time with exponential backoff
        return $newest + (min($base * (2 ** ($blocks - 1)), $max) * 60);
    }

    /**
     * Processes login form submission.
     *
     * @return void
     */
    private function post(): void
    {
        // Validate form fields
        if (
            !FormController::validate('identifier', ['required', 'maxLength' => MAX_EMAIL_LENGTH]) ||
            !FormController::validate('password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH])
        ) return;

        // Check lockout status for this identifier
        if ($this->checkLockedOut($_POST['identifier'])) return;

        // Timing-safe regardless of whether the identifier resolves (see AuthController::verifyCredentials).
        $user = AuthController::verifyCredentials($_POST['identifier'], $_POST['password']);

        if ($user === null) {
            $this->fail('incorrect', 'Invalid username/email or password. Please try again.', AlertType::WARNING);
            return;
        }

        // Check if account is active
        if ($user['status'] !== UserStatus::ACTIVE->value) {
            $this->fail('inactive', AuthController::ACCOUNT_ISSUE_MESSAGE, AlertType::ERROR, (int)$user['id']);
            return;
        }

        // Check if account is verified
        if (EMAIL_VERIFICATION_REQUIRED && !AuthController::isVerified((int)$user['id'])) {
            $this->fail('unverified', AuthController::ACCOUNT_ISSUE_MESSAGE, AlertType::ERROR, (int)$user['id']);
            return;
        }

        // Proceed with login
        $this->login($user);
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
     * Creates user session and handles remember-me cookie.
     *
     * @param array $user Verified user row from verifyCredentials()
     *
     * @return void
     */
    private function login(array $user): void
    {
        // Record successful login attempt
        AuthController::recordLoginAttempt($user['email'], true, null, (int)$user['id']);

        // Update last login timestamp
        AuthController::updateLastLogin($user['email']);

        // Set user session; setUserSession() already redirects with its own alert on failure
        if (!AuthController::setUserSession($user)) return;

        // Check if user must change password
        if ($user['must_change_password']) {
            PageController::redirectWithAlert('change-password', 'Before you can continue, you must change your password!', AlertType::WARNING, 4);
            return;
        }

        // Handle remember-me checkbox. Skips silently on token-generation failure;
        // the login itself already succeeded regardless.
        $token = isset($_POST['remember']) ? AuthController::generateToken() : null;

        if ($token !== null) {
            $timestamp = time() + (86400 * REMEMBER_ME_DURATION);

            // Set cookie
            setcookie('remember', $token, [
                'expires' => $timestamp,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Strict',
            ]);

            // Store SHA-256 hash of the token in the DB; the raw token stays only in the cookie.
            AuthController::createToken((int)$user['id'], hash('sha256', $token), 'remember', date('Y-m-d H:i:s', $timestamp));
        }

        // Redirect the user
        AuthController::intendedRedirect('profile', 'Login successful! Welcome!', AlertType::SUCCESS, 4);
    }
}
