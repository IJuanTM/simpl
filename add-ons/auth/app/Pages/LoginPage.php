<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Database\DB;
use app\Enums\AlertType;

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
     *
     * @param string|null $identifier Username/email for fresh lockout check
     *
     * @return bool True if locked out
     */
    private function checkLockedOut(string|null $identifier = null): bool
    {
        // When no identifier provided, check existing session lockout
        if ($identifier === null) {
            $timeout = SessionController::get('lockout-timeout');

            // Check if there is an active lockout
            if ($timeout && $timeout > time()) {
                $seconds = $timeout - time();
                $minutes = (int)ceil($seconds / 60);
                FormController::addAlert("You are still locked out due to too many failed login attempts. Please wait $minutes minute(s) before trying again.", AlertType::ERROR, $seconds * 1000);
                return true;
            }

            // Lockout has expired, remove from session
            SessionController::remove('lockout-timeout');
            return false;
        }

        // Check lockout status for provided identifier and IP
        $lockOut = $this->lockOutTime($identifier, $_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if ($lockOut['seconds'] > 0) {
            // Set lockout timeout in session
            SessionController::set('lockout-timeout', time() + $lockOut['seconds']);

            // Show appropriate lockout message
            $message = $lockOut['type'] === 'user'
                ? "Your account is locked due to too many failed login attempts. Please wait {$lockOut['minutes']} minute(s) before trying again."
                : "Access from your IP address is temporarily blocked due to too many failed login attempts. Please wait {$lockOut['minutes']} minute(s) before trying again.";

            FormController::addAlert($message, AlertType::ERROR, $lockOut['seconds'] * 1000);
            return true;
        }

        return false;
    }

    /**
     * Calculates lockout duration for user and IP address.
     *
     * @param string $identifier Username or email
     * @param string $ip IP address
     *
     * @return array [seconds, minutes, type] lockout information
     */
    private function lockOutTime(string $identifier, string $ip): array
    {
        $userId = AuthController::getUserIdByIdentifier($identifier);

        // Check user-based lockout first
        if ($userId !== null) {
            $seconds = max(0, ($this->calculateLockout('user_id', $userId, USER_LOGIN_ATTEMPTS, MIN_USER_LOCKOUT_DURATION, MAX_USER_LOCKOUT_DURATION, USER_LOCKOUT_WINDOW) ?? 0) - time());
            $minutes = (int)ceil($seconds / 60);
            if ($seconds > 0) return ['seconds' => $seconds, 'minutes' => $minutes, 'type' => 'user'];
        }

        // Check IP-based lockout
        $seconds = max(0, ($this->calculateLockout('ip_address', $ip, IP_LOGIN_ATTEMPTS, MIN_IP_LOCKOUT_DURATION, MAX_IP_LOCKOUT_DURATION, IP_LOCKOUT_WINDOW) ?? 0) - time());
        $minutes = (int)ceil($seconds / 60);
        if ($seconds > 0) return ['seconds' => $seconds, 'minutes' => $minutes, 'type' => 'ip'];

        return ['seconds' => 0, 'minutes' => 0, 'type' => 'none'];
    }

    /**
     * Calculates lockout end timestamp based on failed attempts.
     *
     * @param string $column Database column to query (user_id or ip_address)
     * @param mixed $value Value to match
     * @param int $threshold Number of attempts before lockout
     * @param int $base Base lockout duration in minutes
     * @param int $max Maximum lockout duration in minutes
     * @param int $window Time window in minutes to count attempts
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

        // Verify credentials
        if (!AuthController::checkIdentifier($_POST['identifier']) || !AuthController::checkPasswordByIdentifier($_POST['identifier'], $_POST['password'])) {
            // Record failed attempt
            AuthController::recordLoginAttempt($_POST['identifier'], false, 'incorrect');

            // Check if now locked out
            if ($this->checkLockedOut($_POST['identifier'])) return;

            $_POST['identifier'] = '';
            $_POST['password'] = '';
            FormController::addAlert('Invalid username/email or password. Please try again.', AlertType::WARNING);
            return;
        }

        // Check if account is active
        if (!AuthController::isActiveByIdentifier($_POST['identifier'])) {
            // Record failed attempt
            AuthController::recordLoginAttempt($_POST['identifier'], false, 'inactive');

            // Check if now locked out
            if ($this->checkLockedOut($_POST['identifier'])) return;

            $_POST['identifier'] = '';
            $_POST['password'] = '';
            FormController::addAlert('Your account is inactive! Contact an administrator for more information!', AlertType::ERROR);
            return;
        }

        // Get email from identifier
        $email = AuthController::getEmailByIdentifier($_POST['identifier']);

        // Check if account is verified
        if (EMAIL_VERIFICATION_REQUIRED && !AuthController::isVerified(null, $email)) {
            // Record failed attempt
            AuthController::recordLoginAttempt($_POST['identifier'], false, 'unverified');

            // Check if now locked out
            if ($this->checkLockedOut($_POST['identifier'])) return;

            $_POST['identifier'] = '';
            $_POST['password'] = '';
            FormController::addAlert('Your account has not been verified! Check your email for the verification link!', AlertType::ERROR);
            return;
        }

        // Proceed with login
        $this->login($email);
    }

    /**
     * Creates user session and handles remember-me cookie.
     *
     * @param string $email User email
     *
     * @return void
     */
    private function login(string $email): void
    {
        // Record successful login attempt
        AuthController::recordLoginAttempt($email, true);

        // Update last login timestamp
        AuthController::updateLastLogin($email);

        // Get user data
        $user = AuthController::getUserByEmail($email);

        // Set user session
        if (!AuthController::setUserSession($user)) {
            FormController::addAlert('An error occurred while trying to log you in! Please try again!', AlertType::ERROR);
            return;
        }

        // Check if user must change password
        if ($user['must_change_password']) {
            PageController::redirect('change-password');
            AlertController::globalAlert('Before you can continue, you must change your password!', AlertType::WARNING, 4);
            return;
        }

        // Handle remember-me checkbox
        if (isset($_POST['remember'])) {
            $token = AuthController::generateToken();
            $timestamp = time() + (86400 * REMEMBER_ME_DURATION);

            // Set cookie
            setcookie('remember', $token, [
                'expires' => $timestamp,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Strict',
            ]);

            // Store token in database
            AuthController::createToken($user['id'], $token, 'remember', date('Y-m-d H:i:s', $timestamp));
        }

        // Redirect to profile
        PageController::redirect('profile');
        AlertController::globalAlert('Login successful! Welcome!', AlertType::SUCCESS, 4);
    }
}
