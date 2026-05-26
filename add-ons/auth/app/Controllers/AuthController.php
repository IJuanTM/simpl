<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\ErrorCode;
use app\Enums\Role;
use app\Models\Url;
use app\Utils\Log;
use Exception;

/**
 * Authentication controller (add-on)
 *
 * Provides user authentication helpers used by the auth add-on: session
 * management, token creation/validation, password handling, and email
 * notifications. The class uses direct DB access and several PHP superglobals
 * (e.g. $_COOKIE, $_SERVER) which makes unit testing harder; consider
 * refactoring into smaller services (UserRepository, TokenService, Mailer)
 * and injecting them for improved testability in the future.
 */
class AuthController
{
    public function __construct()
    {
        // If a "remember" cookie is present and no user session exists,
        // attempt to rehydrate the session from the persistent token.
        if (isset($_COOKIE['remember']) && !SessionController::has('user')) self::rememberLogin($_COOKIE['remember']);
    }

    /**
     * Try to automatically log a user in using a persistent "remember" token.
     * If token is missing, expired or invalid the cookie and database token
     * are cleaned up.
     *
     * @param string $rememberToken Token value read from the user's cookie
     *
     * @return void
     */
    public static function rememberLogin(string $rememberToken): void
    {
        // Fetch token record for the supplied value and type "remember".
        $token = DB::single(
            '*',
            'tokens',
            [
                'token' => $rememberToken,
                'type' => 'remember'
            ]
        );

        // No matching token — clear cookie and stop.
        if (!$token) {
            self::clearRememberCookie();
            return;
        }

        // If token expired: remove DB record and clear cookie.
        if ($token['expires'] < time()) {
            DB::delete(
                'tokens',
                [
                    'token' => $token['token']
                ]
            );

            self::clearRememberCookie();
            return;
        }

        // Load the associated user and set session; abort on failure.
        $user = self::getUserById($token['user_id']);
        if (!$user || !self::setUserSession($user)) return;

        // Refresh expiration for token and cookie (uses REMEMBER_ME_DURATION days).
        $timestamp = time() + (86400 * REMEMBER_ME_DURATION);

        DB::update(
            'tokens',
            [
                'expires' => $timestamp
            ],
            [
                'token' => $rememberToken
            ]
        );

        // Update the cookie to match the refreshed expiration.
        setcookie('remember', $rememberToken, [
            'expires' => $timestamp,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Remove the remember cookie from the client (sets past expiry).
     *
     * @return void
     */
    private static function clearRememberCookie(): void
    {
        setcookie('remember', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Retrieve a user row by id.
     *
     * @param int $id User primary key
     *
     * @return array|null Database row as associative array or null if missing
     */
    public static function getUserById(int $id): array|null
    {
        return DB::single(
            '*',
            'users',
            compact('id')
        ) ?: null;
    }

    /**
     * Create the session entry for an authenticated user and ensure a role
     * is attached. Removes the password field before storing user data in
     * the session.
     *
     * @param array $user Associative user row from DB (expects 'id' present)
     *
     * @return bool True on success, false on failure (no role found)
     */
    public static function setUserSession(array $user): bool
    {
        $role = self::getUserRole($user['id']);

        // If the account has no role assigned, log an error, clear session,
        // notify user and redirect.
        if (!$role) {
            Log::error("No user role is set for user with id \"{$user['id']}\"");
            SessionController::remove('user');
            FormController::addAlert('Error! No user role is set for this account! Please contact an admin!', AlertType::ERROR);
            PageController::redirect(REDIRECT);
            return false;
        }

        // Remove sensitive password hash before storing user in session.
        unset($user['password']);

        // Regenerate session ID to prevent session fixation attacks.
        session_regenerate_id(true);

        // Store user row plus role in session.
        SessionController::set('user', $user + compact('role'));
        return true;
    }

    /**
     * Resolve the role name for a user by looking up user_roles then roles.
     *
     * @param int $userId
     *
     * @return string|null Role name or null when no role is assigned
     */
    private static function getUserRole(int $userId): ?string
    {
        $roleId = DB::single('role_id', 'user_roles', ['user_id' => $userId])['role_id'] ?? null;
        return $roleId ? DB::single('name', 'roles', ['id' => $roleId])['name'] ?? null : null;
    }

    /**
     * Ensure the current request is performed by an authenticated user and
     * optionally enforce role-based access.
     *
     * If the user is unauthenticated, an unauthorized error is rendered.
     * If password change is required and not explicitly allowed this will
     * redirect the user to the change-password flow.
     *
     * @param Role[]|null $allowedRoles Roles that are allowed, or null to allow any authenticated user
     * @param bool $allowPasswordChange If true, allow access even when the user must change password
     *
     * @return void (will redirect/exit on access denial)
     */
    public static function requireAuth(array|null $allowedRoles = null, bool $allowPasswordChange = false): void
    {
        $user = SessionController::get('user');

        if (!$user) {
            // Not authenticated — render 401 with requested URI for context.
            PageController::error(ErrorCode::UNAUTHORIZED, $_SERVER['REQUEST_URI']);
            exit;
        }

        // Re-validate user status and role from the database on every protected request so that deactivations and role changes take effect immediately.
        $fresh = self::getUserById((int)$user['id']);
        if (!$fresh || !$fresh['is_active']) {
            SessionController::remove('user');
            AlertController::globalAlert('Your session has been invalidated. Please log in again.', AlertType::ERROR, 5);
            PageController::redirect(REDIRECT);
            exit;
        }

        $freshRole = self::getUserRole($fresh['id']);

        if (!$freshRole) {
            SessionController::remove('user');
            PageController::redirect(REDIRECT);
            exit;
        }

        // Sync session when role has changed in the database.
        if ($freshRole !== $user['role']) {
            unset($fresh['password']);
            SessionController::set('user', $fresh + ['role' => $freshRole]);
            $user = SessionController::get('user');
        }

        // If the account requires a password change and this route doesn't
        // explicitly allow that, force a redirect to the change-password page.
        if (!$allowPasswordChange && $user['must_change_password']) {
            PageController::redirect('change-password');
            AlertController::globalAlert('Before you can continue, you must change your password!', AlertType::WARNING, 4);
            exit;
        }

        // If allowedRoles is provided, ensure the user's role is in the list.
        if ($allowedRoles !== null && !in_array($user['role'], array_map(static fn(Role $r) => $r->value, $allowedRoles), true)) {
            PageController::error(ErrorCode::FORBIDDEN);
            exit;
        }
    }

    /**
     * Validate a plaintext password against configured password rules.
     * On failure an appropriate user-visible alert is added.
     *
     * @param string $password Plain password to validate
     *
     * @return bool True if valid, false otherwise
     */
    public static function validatePassword(string $password): bool
    {
        [$pattern, $message] = self::getPasswordRules();

        if (!preg_match($pattern, $password)) {
            // Provide a human friendly explanation of the requirements.
            FormController::addAlert($message, AlertType::WARNING);
            return false;
        }

        return true;
    }

    /**
     * Build and cache the regular expression used to validate passwords
     * according to the app configuration constants (e.g. REQUIRE_UPPERCASE,
     * MIN_PASSWORD_LENGTH). Returns an array with [pattern, humanMessage].
     *
     * @return array{0:string,1:string} [regex pattern, error message]
     */
    private static function getPasswordRules(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        // Compose required pattern fragments based on configuration.
        $rules = array_filter([
            REQUIRE_LOWERCASE ? ['(?=.*[a-z])', '1 lowercase letter'] : null,
            REQUIRE_UPPERCASE ? ['(?=.*[A-Z])', '1 uppercase letter'] : null,
            REQUIRE_NUMBER ? ['(?=.*\d)', '1 number'] : null,
            REQUIRE_SPECIAL_CHARACTER ? ['(?=.*[^a-zA-Z\d])', '1 special character'] : null,
        ]);

        // Anchored regex that enforces all required character classes and
        // the minimum length.
        $pattern = '/^' . implode('', array_column($rules, 0)) . '.{' . MIN_PASSWORD_LENGTH . ',}$/';

        // Build a human readable message listing the requirements.
        $messages = array_column($rules, 1);
        array_unshift($messages, "at least " . MIN_PASSWORD_LENGTH . " characters");
        $message = "Your password must contain " . (count($messages) > 1 ? implode(', ', array_slice($messages, 0, -1)) . ' and ' : '') . end($messages) . ".";

        return $cache = [$pattern, $message];
    }

    /**
     * Return the user-facing password requirements message.
     *
     * @return string
     */
    public static function getPasswordRequirements(): string
    {
        return self::getPasswordRules()[1];
    }

    /**
     * Get the relative path to a user's profile image if set.
     * Returns null when no profile image is configured.
     *
     * @param int $id User id
     *
     * @return string|null Path relative to public root (e.g. 'img/profile/...') or null
     */
    public static function getProfileImage(int $id): string|null
    {
        $profile_img = DB::single(
            'profile_img',
            'users',
            compact('id')
        )['profile_img'] ?? null;

        return $profile_img ? PROFILE_IMAGE_PATH . $profile_img : null;
    }

    /**
     * Convenience: generate a random password using the token generator.
     *
     * @param int $length Desired password length
     *
     * @return string|null Generated password or null on failure
     */
    public static function generatePassword(int $length = GENERATED_PASSWORD_LENGTH): string|null
    {
        return self::generateToken($length, false);
    }

    /**
     * Generate a cryptographically secure random token (hex characters).
     * The token length is trimmed to $length. If $uppercase is true the
     * returned string is uppercased to be more human-readable in some cases.
     *
     * @param int $length Number of characters to return
     * @param bool $uppercase Uppercase the resulting token
     *
     * @return string|null Token string or null when secure random generation fails
     */
    public static function generateToken(int $length = RESET_TOKEN_LENGTH, bool $uppercase = true): string|null
    {
        try {
            $bytes = random_bytes((int)ceil($length / 2));
            $token = substr(bin2hex($bytes), 0, $length);
            return $uppercase ? strtoupper($token) : $token;
        } catch (Exception $e) {
            Log::error("Could not generate token: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Check whether an email address is already registered.
     *
     * @param string $email
     *
     * @return bool
     */
    public static function checkEmail(string $email): bool
    {
        return DB::exists(
            'users',
            compact('email')
        );
    }

    /**
     * Determine whether a user exists by id.
     *
     * @param int $id
     *
     * @return bool
     */
    public static function exists(int $id): bool
    {
        return DB::exists(
            'users',
            compact('id')
        );
    }

    /**
     * Return whether the account is verified (no pending verification token).
     * Either provide $id or $email (email will be resolved to id).
     *
     * @param int|null $id
     * @param string|null $email
     *
     * @return bool True when verified, false otherwise
     */
    public static function isVerified(int|null $id = null, string|null $email = null): bool
    {
        if ($id === null && $email !== null) {
            $id = self::getUserIdByEmail($email);
            if (!$id) return false;
        }

        if ($id === null) return false;

        // Account considered verified when there is no verification token row.
        return !DB::exists(
            'tokens',
            [
                'user_id' => $id,
                'type' => 'verification'
            ]
        );
    }

    /**
     * Resolve a user's id by their email address.
     *
     * @param string $email
     *
     * @return int|null Id or null when not found
     */
    public static function getUserIdByEmail(string $email): int|null
    {
        $user = DB::single(
            'id',
            'users',
            compact('email')
        );

        return $user ? (int)$user['id'] : null;
    }

    /**
     * Verify that a provided token matches the stored token for the given
     * user id and token type. Comparison is case-insensitive.
     *
     * @param int $id User id
     * @param string $token Token to check
     * @param string $type Token type (e.g. 'verification', 'reset', 'remember')
     *
     * @return bool True if tokens match
     */
    public static function checkToken(int $id, string $token, string $type): bool
    {
        $dbToken = DB::single(
            'token',
            'tokens',
            [
                'user_id' => $id,
                'type' => $type
            ]
        )['token'] ?? null;

        // Use case-insensitive comparison so tokens are not bound to casing.
        return $dbToken && strcasecmp($dbToken, $token) === 0;
    }

    /**
     * Given a username or email (identifier) return the associated email.
     *
     * @param string $identifier Username or email
     *
     * @return string|null Email or null when not found
     */
    public static function getEmailByIdentifier(string $identifier): string|null
    {
        return self::getUserByIdentifier($identifier)['email'] ?? null;
    }

    /**
     * Find a user by either email or username. Email is tried first.
     *
     * @param string $identifier
     *
     * @return array|null DB row or null
     */
    public static function getUserByIdentifier(string $identifier): array|null
    {
        // Prefer resolving by email first (identifier may already be an email).
        $user = self::getUserByEmail($identifier);
        if ($user) return $user;

        // Fall back to username lookup.
        return DB::single(
            '*',
            'users',
            [
                'username' => $identifier
            ]
        ) ?: null;
    }

    /**
     * Fetch a user record by email.
     *
     * @param string $email
     *
     * @return array|null
     */
    public static function getUserByEmail(string $email): array|null
    {
        return DB::single(
            '*',
            'users',
            compact('email')
        ) ?: null;
    }

    /**
     * Check provided plaintext password against the stored hash for the
     * user identified by email.
     *
     * @param string $email
     * @param string $password Plaintext password to verify
     *
     * @return bool True when the password matches
     */
    public static function checkPassword(string $email, string $password): bool
    {
        $hash = DB::single(
            'password',
            'users',
            compact('email')
        )['password'] ?? null;

        return $hash && password_verify($password, $hash);
    }

    /**
     * Check password for an identifier that may be an email or username.
     *
     * @param string $identifier
     * @param string $password
     *
     * @return bool
     */
    public static function checkPasswordByIdentifier(string $identifier, string $password): bool
    {
        return ($user = self::getUserByIdentifier($identifier)) && password_verify($password, $user['password']);
    }

    /**
     * Determine whether an email or username exists in the database.
     *
     * @param string $identifier Email or username
     *
     * @return bool
     */
    public static function checkIdentifier(string $identifier): bool
    {
        return DB::exists(
                'users',
                [
                    'email' => $identifier
                ]
            ) || DB::exists(
                'users',
                [
                    'username' => $identifier
                ]
            );
    }

    /**
     * Is the account active (is_active === 1) for the user with this email?
     *
     * @param string $email
     *
     * @return bool
     */
    public static function isActive(string $email): bool
    {
        return (bool)DB::single(
            'is_active',
            'users', compact('email')
        )['is_active'];
    }

    /**
     * Is the account active for a user located by username or email?
     *
     * @param string $identifier
     *
     * @return bool
     */
    public static function isActiveByIdentifier(string $identifier): bool
    {
        return ($user = self::getUserByIdentifier($identifier)) && $user['is_active'];
    }

    /**
     * Update a user's password and clear the "must_change_password" flag.
     * Password is hashed with configured algorithm/options constants.
     *
     * @param int $id
     * @param string $password Plaintext new password (will be hashed)
     *
     * @return void
     */
    public static function updatePassword(int $id, string $password): void
    {
        DB::update(
            'users',
            [
                'password' => password_hash($password, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS),
                'must_change_password' => 0
            ],
            compact('id')
        );
    }

    /**
     * Create or replace a token of the given type for a user. Existing tokens
     * of the same type for that user are removed before insertion.
     *
     * @param int $userId
     * @param string $token
     * @param string $type
     * @param string|null $expires Optional expiry timestamp value
     *
     * @return void
     */
    public static function createToken(int $userId, string $token, string $type, string|null $expires = null): void
    {
        DB::delete(
            'tokens',
            [
                'user_id' => $userId,
                'type' => $type
            ]
        );

        $data = [
            'user_id' => $userId,
            'token' => $token,
            'type' => $type
        ];
        if ($expires) $data['expires'] = $expires;

        DB::insert(
            'tokens',
            $data
        );
    }

    /**
     * Remove a token record for a user by type.
     *
     * @param int $userId
     * @param string $type
     *
     * @return void
     */
    public static function deleteToken(int $userId, string $type): void
    {
        DB::delete(
            'tokens',
            [
                'user_id' => $userId,
                'type' => $type
            ]
        );
    }

    /**
     * Persist a login attempt record including IP, user agent and whether it
     * succeeded. The user_id will be resolved from the provided identifier
     * (email or username) when possible.
     *
     * @param string $identifier
     * @param bool $success
     * @param string|null $failedReason
     *
     * @return void
     */
    public static function recordLoginAttempt(string $identifier, bool $success, string|null $failedReason = null): void
    {
        DB::insert(
            'login_attempts',
            [
                'user_id' => self::getUserIdByIdentifier($identifier),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'success' => $success ? 1 : 0,
                'failed_reason' => $failedReason
            ]
        );
    }

    /**
     * Resolve a user id from a username or email identifier.
     *
     * @param string $identifier
     *
     * @return int|null
     */
    public static function getUserIdByIdentifier(string $identifier): int|null
    {
        $user = self::getUserByIdentifier($identifier);
        return $user ? (int)$user['id'] : null;
    }

    /**
     * Update the last_login timestamp for the user identified by email.
     *
     * @param string $email
     *
     * @return void
     */
    public static function updateLastLogin(string $email): void
    {
        DB::update(
            'users',
            [
                'last_login' => date('Y-m-d H:i:s')
            ],
            compact('email')
        );
    }

    /**
     * Send a verification email containing a one-time code/link and redirect
     * the user to the verification page. Displays user feedback alerts
     * depending on the mailer result.
     *
     * @param int $id
     * @param string $to
     * @param string $code
     * @param bool $isResend
     *
     * @return void
     */
    public static function sendVerificationMail(int $id, string $to, string $code, bool $isResend = false): void
    {
        $contents = MailController::template('verification', [
            'title' => 'Account Verification - ' . APP_NAME,
            'link' => Url::to("verify-account/$id/$code"),
            'code' => $code
        ]);

        if ($contents === false) {
            FormController::addAlert('An error occurred while sending your verification email! Please contact support.', AlertType::ERROR);
            return;
        }

        $result = MailController::send(APP_NAME, $to, NO_REPLY_MAIL, 'Verify account', $contents);

        // Redirect to the verification page regardless of email sending result.
        PageController::redirect("verify-account/$id");

        if ($result) {
            $message = $isResend ? 'Success! A new verification email has been sent!' : 'Success! Your account has been created! A verification email has been sent!';
            AlertController::globalAlert($message, AlertType::SUCCESS, 4);
        } else {
            $message = $isResend ? 'An error occurred while sending your verification email! Please contact support.' : 'Your account has been created! However, there was an issue sending the verification email. Please contact support.';
            AlertController::globalAlert($message, AlertType::ERROR, 8);
        }
    }

    /**
     * Send a password reset email with a tokenized link to the reset form.
     *
     * @param int $id
     * @param string $to
     * @param string $token
     *
     * @return void
     */
    public static function sendPasswordResetMail(int $id, string $to, string $token): void
    {
        $contents = MailController::template('reset-password', [
            'title' => 'Password Reset Request - ' . APP_NAME,
            'link' => Url::to("reset-password/$id/$token")
        ]);

        if ($contents === false) {
            FormController::addAlert('An error occurred while sending your verification email! Please contact support.', AlertType::ERROR);
            return;
        }

        $result = MailController::send(APP_NAME, $to, NO_REPLY_MAIL, 'Reset password', $contents);

        if ($result) FormController::addAlert('Success! A reset link has been sent to your email!', AlertType::SUCCESS);
        else FormController::addAlert('An error occurred while sending the reset link. Please contact support.', AlertType::ERROR);
    }

    /**
     * Notify a newly-created user by email with a temporary password and
     * redirect to the users listing. Shows feedback about mail delivery.
     *
     * @param string $to
     * @param string $password Temporary plaintext password
     *
     * @return void
     */
    public static function sendCreatedUserMail(string $to, string $password): void
    {
        $contents = MailController::template('account-created', [
            'title' => 'Account Created - ' . APP_NAME,
            'link' => Url::to('login'),
            'password' => $password
        ]);

        if ($contents === false) {
            FormController::addAlert('An error occurred while sending the account creation email! Please contact support.', AlertType::ERROR);
            return;
        }

        $result = MailController::send(APP_NAME, $to, NO_REPLY_MAIL, 'An account has been created', $contents);

        PageController::redirect('users');

        if ($result) AlertController::globalAlert('Success! The user has been created and notified via email!', AlertType::SUCCESS, 4);
        else AlertController::globalAlert('The user has been created! However, there was an issue sending the notification email.', AlertType::ERROR, 8);
    }
}
