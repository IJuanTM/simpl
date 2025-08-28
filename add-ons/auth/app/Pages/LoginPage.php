<?php

namespace app\Pages;

use app\Controllers\AlertController;
use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Database\Database;
use app\Enums\AlertType;

/**
 * Handles user authentication and login process.
 */
class LoginPage
{
    public function __construct()
    {
        // Check if the login form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->post();
    }

    private function post(): void
    {
        $valid = true;

        // Validate the form fields
        if (!FormController::validate('email', ['required', 'maxLength' => 100, 'type' => 'email'])) $valid = false;
        if (!FormController::validate('password', ['required', 'maxLength' => 50])) $valid = false;

        if (!$valid) return;

        // Sanitize the form data
        $_POST['email'] = FormController::sanitize($_POST['email']);

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Lockout check
        if ($this->isLockedOut($_POST['email'], $ip)) {
            FormController::addAlert('Too many failed login attempts. Please wait a few minutes before trying again.', AlertType::ERROR);
            return;
        }

        // Check if email exists AND password is correct
        if (!AuthController::checkEmail($_POST['email']) || !AuthController::checkPassword($_POST['email'], $_POST['password'])) {
            // Record failed login attempt
            $this->recordLoginAttempt($_POST['email'], false, 'incorrect');

            $_POST['email'] = '';
            $_POST['password'] = '';

            FormController::addAlert('Invalid email or password. Please try again.', AlertType::WARNING);
            return;
        }

        // Check if the user is inactive
        if (!AuthController::isActive($_POST['email'])) {
            // Record failed login attempt
            $this->recordLoginAttempt($_POST['email'], false, 'inactive');

            $_POST['email'] = '';
            $_POST['password'] = '';

            FormController::addAlert('Your account is inactive! Contant an administrator for more information!', AlertType::ERROR);
            return;
        }

        // Check if the user has not yet verified their account
        if (!AuthController::isVerified(null, $_POST['email'])) {
            // Record failed login attempt
            $this->recordLoginAttempt($_POST['email'], false, 'unverified');

            $_POST['email'] = '';
            $_POST['password'] = '';

            FormController::addAlert('Your account has not been verified! Check your email for the verification link!', AlertType::ERROR);
            return;
        }

        // Login the user
        $this->login($_POST['email']);
    }

    /**
     * Checks if a user account is locked due to too many failed login attempts.
     *
     * @param string $email User's email address
     * @param string $ip User's IP address
     *
     * @return bool True if the account is locked, false otherwise
     */
    private function isLockedOut(string $email, string $ip): bool
    {
        $db = new Database();

        $userId = AuthController::getUserIdByEmail($email);

        $userAttempts = 0;

        // Count failed login attempts for the user in the last 5 minutes
        if ($userId !== null) {
            $db->query('SELECT COUNT(*) AS attempts FROM login_attempts WHERE user_id = :id AND success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 5 MINUTE)');
            $db->bind(':id', $userId);
            $userAttempts = $db->single()['attempts'] ?? 0;
        }

        // Count failed login attempts for the IP address in the last 15 minutes
        $db->query('SELECT COUNT(*) AS attempts FROM login_attempts WHERE ip_address = :ip AND success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $db->bind(':ip', $ip);
        $ipAttempts = $db->single()['attempts'] ?? 0;

        return ($userAttempts >= 5) || ($ipAttempts >= 20);
    }

    /**
     * Records a user's login attempt in the database.
     *
     * @param string $email User's email address
     * @param bool $success Whether the login attempt was successful
     * @param string|null $failedReason Reason the login failed (e.g., 'incorrect', 'inactive' or 'unverified')
     */
    private function recordLoginAttempt(string $email, bool $success, string $failedReason = null): void
    {
        $db = new Database();

        // Record the login attempt
        $db->query('INSERT INTO login_attempts (user_id, ip_address, user_agent, success, failed_reason) VALUES (:id, :ip_address, :user_agent, :success, :failed_reason)');
        $db->bind(':id', AuthController::getUserIdByEmail($email));
        $db->bind(':ip_address', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $db->bind(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        $db->bind(':success', $success ? 1 : 0);
        $db->bind(':failed_reason', $failedReason);
        $db->execute();
    }

    /**
     * Authenticates user and creates session.
     *
     * @param string $email User's email address
     */
    private function login(string $email): void
    {
        // Record successful login attempt
        $this->recordLoginAttempt($email, true);

        $db = new Database();

        // Get the user from the database
        $db->query('SELECT * FROM users WHERE email = :email');
        $db->bind(':email', $email);
        $user = $db->single();

        // Remove the password from the user array
        unset($user['password']);

        // Set the user in the session
        if (!AuthController::setUserSession($user)) {
            FormController::addAlert('An error occurred while trying to log you in! Please try again!', AlertType::ERROR);
            return;
        }

        // Check if the user has the remember me checkbox checked
        if (isset($_POST['remember'])) {
            // Generate a remember token
            $token = AuthController::generateToken(16);

            // Timestamp for the cookie (30 days)
            $timestamp = time() + (86400 * 30);

            // Set the cookie
            setcookie('remember', $token, $timestamp, '/');

            // Set the token in the database
            $this->setRememberToken($user['id'], $token, $timestamp);
        }

        // Redirect the user to the profile page with a success message
        PageController::redirect('profile');
        AlertController::alert('Login successful! Welcome!', AlertType::SUCCESS, 4);
    }

    /**
     * Stores remember-me token in database.
     *
     * @param int $id User ID
     * @param string $token Remember token
     * @param int $timestamp Expiration timestamp
     */
    private function setRememberToken(int $id, string $token, int $timestamp): void
    {
        $db = new Database();

        // Delete the old token(s) from the database
        $db->query('DELETE FROM tokens WHERE user_id = :id AND type = :type');
        $db->bind(':id', $id);
        $db->bind(':type', 'remember');
        $db->execute();

        // Set the token in the database
        $db->query('INSERT INTO tokens (user_id, token, type, expires) VALUES(:id, :token, :type, :expires)');
        $db->bind(':id', $id);
        $db->bind(':token', $token);
        $db->bind(':type', 'remember');
        $db->bind(':expires', date('Y-m-d H:i:s', $timestamp));
        $db->execute();
    }
}
