<?php

namespace app\Controllers;

use app\Database\DB;
use app\Enums\AlertType;
use app\Models\Url;
use app\Utils\Log;
use Exception;

/**
 * Base class for authentication-related functionality.
 */
class AuthController
{
    public function __construct()
    {
        // Log in the user if the remember cookie is set
        if (isset($_COOKIE['remember']) && !SessionController::has('user')) self::rememberLogin($_COOKIE['remember']);
    }

    /**
     * Automatically logs in a user using a remember token.
     *
     * @param string $rememberToken Token from cookie
     */
    public static function rememberLogin(string $rememberToken): void
    {
        // Get the remember token from the database
        $token = DB::single(
            '*',
            'tokens',
            [
                'token' => $rememberToken,
                'type' => 'remember'
            ]
        );

        // Check if the token exists
        if (!$token) {
            // Delete the cookie
            setcookie('remember', '', time() - 3600, '/');
            return;
        }

        // Check if the token is expired
        if ($token['expires'] < time()) {
            // Delete the token from the database
            DB::delete(
                'tokens',
                [
                    'token' => $token['token']
                ]
            );

            // Delete the cookie
            setcookie('remember', '', time() - 3600, '/');
            return;
        }

        // Get the user from the database
        $user = DB::single(
            '*',
            'users',
            [
                'id' => $token['user_id']
            ]
        );

        // Set the user in the session
        self::setUserSession($user);

        // Calculate new expiration timestamp
        $timestamp = time() + (86400 * REMEMBER_ME_DURATION);

        // Refresh the token's expiration date
        DB::update(
            'tokens',
            [
                'expires' => $timestamp
            ],
            [
                'token' => $rememberToken
            ]
        );

        // Refresh the remember cookie
        setcookie('remember', $rememberToken, $timestamp, '/');
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
        // Get the user role from the database
        $role = DB::single(
            'role_id',
            'user_roles',
            [
                'user_id' => $user['id']
            ]
        )['role_id'];

        // Check if the user role is set
        if (!$role) {
            // Log an error message
            Log::error("No user role is set for user with id \"" . $user['id'] . "\"");

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
        [$pattern, $message] = self::getPasswordRules();

        // Validate the password against the pattern
        if (!preg_match($pattern, $password)) {
            FormController::addAlert($message, AlertType::WARNING);
            return false;
        }

        return true;
    }

    /**
     * Returns a message detailing the password requirements based on current configuration.
     *
     * @return array An array containing the regex pattern and the error message
     */
    private static function getPasswordRules(): array
    {
        static $cache = null;

        // Return cached result if available
        if ($cache !== null) return $cache;

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
        $message = "Your password must contain " . (count($messages) > 1 ? implode(', ', array_slice($messages, 0, -1)) . ' and ' : '') . end($messages) . ".";

        // Cache the result
        $cache = [$pattern, $message];

        return $cache;
    }

    /**
     * Returns a user-friendly string detailing the password requirements.
     *
     * @return string Password requirements message
     */
    public static function getPasswordRequirements(): string
    {
        return self::getPasswordRules()[1];
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
        // Get the profile image from the database
        $profile_img = DB::single(
            'profile_img',
            'users',
            compact('id')
        )['profile_img'];

        // Return the path to the profile image
        if ($profile_img) return 'img/profile/' . $profile_img;
        else return null;
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
            Log::error($e->getMessage());

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
        // Check if the email exists in the database
        return DB::exists(
            'users',
            compact('email')
        );
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
        // Check if the user exists in the database
        return DB::exists(
            'users',
            compact('id')
        );
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
        if ($email !== null) {
            // Get the user id from the database
            $id = DB::single(
                'id',
                'users',
                compact('email')
            )['id'];
        }

        // Check if there are any verification tokens for the user
        return DB::count(
                'tokens',
                [
                    'user_id' => $id,
                    'type' => 'verification'
                ]
            ) === 0;
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
        // Get the token from the database and compare it
        return strcasecmp(DB::single(
                'token',
                'tokens',
                [
                    'user_id' => $id,
                    'type' => $type
                ]
            )['token'], $token) === 0;
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
        // Verify the password against the hash in the database
        return password_verify($password, DB::single(
            'password',
            'users',
            compact('email')
        )['password']);
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
        // Check if the user is active in the database
        return DB::single(
                'is_active',
                'users',
                compact('email')
            )['is_active'] === 1;
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
        // Get the user from the database
        $user = DB::single(
            'id',
            'users',
            compact('email')
        );

        // Return the user id or null if not found
        return $user ? (int)$user['id'] : null;
    }

    /**
     * Sends a verification email to the user and redirects them to the verification page.
     *
     * @param int $id User ID
     * @param string $to User's email address
     * @param string $code Verification code
     * @param bool $isResend Whether this is a resend of the verification email
     */
    public static function sendVerificationMail(int $id, string $to, string $code, bool $isResend = false): void
    {
        // Get the template from the views/parts/mails folder
        $contents = MailController::template('verification', [
            'title' => 'Account Verification - ' . APP_NAME,
            'link' => Url::to("verify-account/$id/$code"),
            'code' => $code
        ]);

        // Check if template was loaded successfully
        if ($contents === false) {
            FormController::addAlert('An error occurred while sending your verification email! Please contact support.', AlertType::ERROR);
            return;
        }

        // Send the email and handle the result
        $result = MailController::send(APP_NAME, $to, NO_REPLY_MAIL, 'Verify account', $contents);

        // Redirect the user to the verification page
        PageController::redirect("verify-account/$id");

        // Show appropriate alert based on email sending result
        if ($result) {
            $message = $isResend
                ? 'Success! A new verification email has been sent!'
                : 'Success! Your account has been created! A verification email has been sent!';
            AlertController::alert($message, AlertType::SUCCESS, 4);
        } else {
            $message = $isResend
                ? 'An error occurred while sending your verification email! Please contact support.'
                : 'Your account has been created! However, there was an issue sending the verification email. Please contact support.';
            AlertController::alert($message, AlertType::ERROR, 8);
        }
    }
}
