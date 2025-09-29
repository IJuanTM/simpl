<?php

namespace app\Controllers;

use app\Database\Database;
use app\Enums\AlertType;
use app\Enums\LogType;
use Exception;

/**
 * Base class for authentication-related functionality.
 */
class AuthController
{
    public function __construct()
    {
        // Log in the user if the remember cookie is set
        if (isset($_COOKIE['remember']) && SessionController::has('user')) self::rememberLogin($_COOKIE['remember']);
    }

    /**
     * Automatically logs in a user using a remember token.
     *
     * @param string $rememberToken Token from cookie
     */
    public static function rememberLogin(string $rememberToken): void
    {
        $db = new Database();

        // Get the remember token from the database
        $db->query('SELECT * FROM tokens WHERE token = :token AND type = :type');
        $db->bind(':token', $rememberToken);
        $db->bind(':type', 'remember');
        $token = $db->single();

        // Check if the token exists
        if (!$token) {
            // Delete the cookie
            setcookie('remember', '', time() - 3600);
            return;
        }

        // Check if the token is expired
        if ($token['expires'] < time()) {
            // Delete the token from the database
            $db->query('DELETE FROM tokens WHERE token = :token');
            $db->bind(':token', $token['token']);
            $db->execute();

            // Delete the cookie
            setcookie('remember', '', time() - 3600);
            return;
        }

        // Get the user from the database
        $db->query('SELECT * FROM users WHERE id = :id');
        $db->bind(':id', $token['user_id']);

        // Set the user in the session
        self::setUserSession($db->single());
    }

    /**
     * Sets user data in the session after successful authentication.
     *
     * @param array $user User data from database
     *
     * @return bool Success status
     */
    public static function setUserSession(array $user): bool
    {
        $db = new Database();

        // Get the user role from the database
        $db->query('SELECT role_id FROM user_roles WHERE user_id = :id');
        $db->bind(':id', $user['id']);
        $role = $db->single()['role_id'];

        // Check if the user role is set
        if (!$role) {
            // Log an error message
            LogController::log("No user role is set for user with id \"" . $user['id'] . "\"", LogType::SESSION);

            // Unset the session user
            SessionController::remove('user');

            // Redirect the user to the redirect page
            FormController::addAlert('Error! No user role is set for this account! Please contact an admin!', AlertType::ERROR);
            PageController::redirect(REDIRECT);
            return false;
        }

        // Add the role to the user array
        $user += compact('role');

        // Set the session user
        SessionController::set('user', $user);
        return true;
    }

    /**
     * Validates the password against defined security rules, see the auth config to change them.
     *
     * @param string $password The password to validate
     *
     * @return bool Whether the password is valid
     */
    public static function validatePassword(string $password): bool
    {
        $rules = array_filter([
            REQUIRE_LOWERCASE ? ['(?=.*[a-z])', '1 lowercase letter'] : null,
            REQUIRE_UPPERCASE ? ['(?=.*[A-Z])', '1 uppercase letter'] : null,
            REQUIRE_NUMBER ? ['(?=.*\d)', '1 number'] : null,
            REQUIRE_SPECIAL_CHARACTER ? ['(?=.*[^a-zA-Z\d])', '1 special character'] : null,
        ]);

        // Build the regex pattern
        $pattern = '/^' . implode('', array_column($rules, 0)) . '.{' . MIN_PASSWORD_LENGTH . ',}$/';

        // Create an error message based on the rules
        $messages = array_column($rules, 1);
        array_unshift($messages, "at least " . MIN_PASSWORD_LENGTH . " characters");
        $errorMessage = "Your password must contain " . (count($messages) > 1 ? implode(', ', array_slice($messages, 0, -1)) . ' and ' : '') . end($messages) . "!";

        // Validate the password against the pattern
        if (!preg_match($pattern, $password)) {
            FormController::addAlert($errorMessage, AlertType::WARNING);
            return false;
        }

        return true;
    }

    /**
     * Gets the path to a user's profile image.
     *
     * @param string $id User ID
     *
     * @return string|null Path to image or null if none exists
     */
    public static function getProfileImage(string $id): string|null
    {
        $db = new Database();

        // Get the profile image from the database
        $db->query('SELECT profile_img FROM users WHERE id = :id');
        $db->bind(':id', $id);
        $profile_img = $db->single()['profile_img'];

        // Return the path to the profile image
        if ($profile_img) return 'img/profile/' . $profile_img;
        else return null;
    }

    /**
     * Checks if a user exists in the database.
     *
     * @param int $id User ID
     *
     * @return bool Whether the user exists
     */
    public static function exists(int $id): bool
    {
        $db = new Database();

        // Check if the user exists in the database
        $db->query('SELECT id FROM users WHERE id = :id');
        $db->bind(':id', $id);
        $db->execute();

        // Return true if the user exists in the database
        return $db->rowCount() > 0;
    }

    /**
     * Generates a random token for verification or password reset.
     *
     * @param int $bytes Number of random bytes (token length = bytes*2)
     *
     * @return string|null Generated token or null on failure
     */
    public static function generateToken(int $bytes): string|null
    {
        // Limit the number of bytes to a maximum of 16 (32 characters)
        $bytes = min($bytes, 16);

        try {
            // Generate a random string
            return strtoupper(bin2hex(random_bytes($bytes)));
        } catch (Exception $e) {
            // Log the error
            LogController::log($e->getMessage(), LogType::ERROR);

            // Return an error message
            FormController::addAlert('Error! Something went wrong! Please try again or contact an admin.', AlertType::ERROR);
            PageController::redirect(REDIRECT, 2);
            return null;
        }
    }

    /**
     * Checks if an email exists in the database.
     *
     * @param string $email Email to check
     *
     * @return bool Whether the email exists
     */
    public static function checkEmail(string $email): bool
    {
        $db = new Database();

        // Check if the email exists in the database
        $db->query('SELECT * FROM users WHERE email = :email');
        $db->bind(':email', $email);

        // Return true if the email exists in the database
        return $db->rowCount() > 0;
    }

    /**
     * Restricts page access to users with specific roles.
     *
     * @param array $roles Allowed role IDs
     */
    public static function access(array $roles): void
    {
        // If the current user role is not in the array, redirect to error page
        if (SessionController::get('user')['role'] !== null && in_array(SessionController::get('user')['role'], $roles, true)) return;
        else {
            // Redirect to error page
            PageController::redirect('error/403');
            exit;
        }
    }

    /**
     * Checks if a user's email is verified.
     *
     * @param int|null $id User ID (optional if email provided)
     * @param string|null $email User email (optional if ID provided)
     *
     * @return bool Whether the user is verified
     */
    public static function isVerified(int|null $id = null, string|null $email = null): bool
    {
        $db = new Database();

        if ($email !== null) {
            // Get the user id from the database
            $db->query('SELECT id FROM users WHERE email = :email');
            $db->bind(':email', $email);
            $id = $db->single()['id'];
        }

        // Check if the user is verified
        $db->query('SELECT * FROM tokens WHERE user_id = :id AND type = :type');
        $db->bind(':id', $id);
        $db->bind(':type', 'verification');

        // Return true if the user is verified
        return $db->rowCount() === 0;
    }

    /**
     * Validates a token against the database.
     *
     * @param int $id User ID
     * @param string $token Token to check
     * @param string $type Token type (verification, reset, etc.)
     *
     * @return bool Whether the token is valid
     */
    public static function checkToken(int $id, string $token, string $type): bool
    {
        $db = new Database();

        // Get the token from the database
        $db->query('SELECT token FROM tokens WHERE user_id = :user_id AND type = :type');
        $db->bind(':user_id', $id);
        $db->bind(':type', $type);

        // Check if the token is valid
        return strcasecmp($db->single()['token'], $token) === 0;
    }

    /**
     * Verifies a password against the stored hash.
     *
     * @param string $email User email
     * @param string $password Password to check
     *
     * @return bool Whether the password is correct
     */
    public static function checkPassword(string $email, string $password): bool
    {
        $db = new Database();

        // Get the password from the database
        $db->query('SELECT password FROM users WHERE email = :email');
        $db->bind(':email', $email);

        // Return true if the password is correct
        return password_verify($password, $db->single()['password']);
    }

    /**
     * Checks if a user account is active (not deleted).
     *
     * @param string $email User email
     *
     * @return bool Whether the account is active
     */
    public static function isActive(string $email): bool
    {
        $db = new Database();

        // Check if the user is deleted
        $db->query('SELECT is_active FROM users WHERE email = :email');
        $db->bind(':email', $email);

        // Return true if the user is active
        return $db->single()['is_active'] === 1;
    }

    /**
     * Retrieves a user's ID based on their email address.
     *
     * @param string $email User email
     *
     * @return int|null User ID or null if not found
     */
    public static function getUserIdByEmail(string $email): int|null
    {
        $db = new Database();

        // Get the user id from the database
        $db->query('SELECT id FROM users WHERE email = :email');
        $db->bind(':email', $email);
        $user = $db->single();

        // Return the user id or null if not found
        return $user ? (int)$user['id'] : null;
    }
}
