<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Database\DB;
use app\Enums\AlertType;
use app\Enums\ErrorCode;
use app\Enums\Role;
use app\Enums\TokenType;
use app\Enums\UserStatus;
use app\Models\Url;
use app\Utils\Log;
use app\Utils\Timebox;
use Exception;
use JsonException;

/**
 * Provides user authentication helpers: session management, token creation and validation, password handling, and email notifications.
 */
class AuthController
{
    // Deliberately vague so a login attempt can't be used to confirm account status.
    public const string ACCOUNT_ISSUE_MESSAGE = 'There is an issue with your account. Please contact support for assistance.';

    public function __construct()
    {
        if (isset($_COOKIE['remember']) && !SessionController::has('user')) self::rememberLogin($_COOKIE['remember']);
    }

    /**
     * Try to automatically log a user in using a persistent "remember" token.
     * If the token is missing, expired or invalid, the cookie and database token are cleaned up.
     *
     * @param string $rememberToken Token value read from the user's cookie
     *
     * @return void
     */
    public static function rememberLogin(string $rememberToken): void
    {
        $tokenHash = hash('sha256', $rememberToken);

        $token = DB::single(
            SELECT: '*',
            FROM: 'tokens',
            WHERE: [
                'token' => $tokenHash,
                'type' => TokenType::REMEMBER->value
            ]
        );

        if (!$token) {
            self::clearRememberCookie();
            return;
        }

        if (strtotime((string)$token['expires']) < time()) {
            self::invalidateRememberToken($tokenHash);
            return;
        }

        $user = self::getUserWithRole($token['user_id']);

        // Auto-login must clear the same bars the login form does.
        // A stale cookie must not revive a since-deactivated or re-unverified account.
        if (
            !$user
            || $user['status'] !== UserStatus::ACTIVE->value
            || (VERIFICATION_CONFIG['required'] && !self::isVerified((int)$user['id']))
            || !self::setUserSession($user)
        ) {
            self::invalidateRememberToken($tokenHash);
            return;
        }

        // Rotate the token, not just extend its expiry, so a stolen cookie stops working next use.
        $newToken = self::generateToken(REMEMBER_ME_TOKEN_LENGTH);

        // Can't rotate - fail closed like the branches above, rather than leaving the old token valid.
        if ($newToken === null) {
            self::invalidateRememberToken($tokenHash);
            return;
        }

        $timestamp = self::rememberCookieExpiry();

        // A concurrent request may have already rotated this same token.
        // Only issue the new cookie when this call actually won the rotation, so we never hand out an unpersisted one.
        if (!DB::update(
            UPDATE: 'tokens',
            SET: [
                'token' => hash('sha256', $newToken),
                'expires' => date('Y-m-d H:i:s', $timestamp)
            ],
            WHERE: [
                'token' => $tokenHash
            ]
        )) return;

        self::setRememberCookie($newToken, $timestamp);
    }

    /**
     * Remove the remember cookie from the client (sets past expiry).
     *
     * @return void
     */
    public static function clearRememberCookie(): void
    {
        setcookie('remember', '', ['expires' => time() - 3600] + AppController::secureCookieFlags());
    }

    /**
     * Delete a remember token row and clear the client's cookie.
     * Used by every rememberLogin() failure branch past the initial "token not found" check.
     *
     * @param string $tokenHash Hashed token value, as stored in the 'tokens' table
     *
     * @return void
     */
    private static function invalidateRememberToken(string $tokenHash): void
    {
        DB::delete(
            FROM: 'tokens',
            WHERE: [
                'token' => $tokenHash
            ]
        );

        self::clearRememberCookie();
    }

    /**
     * Fetch a user row joined with their role name in a single query.
     * Returns the user array with an extra 'role' key (the role name string, or null).
     *
     * @param int $id User primary key
     *
     * @return array|null
     */
    public static function getUserWithRole(int $id): ?array
    {
        return DB::single(
            SELECT: ['users.*', 'roles.name AS role'],
            FROM: 'users',
            JOIN: [
                ['id', ['user_roles', 'user_id']],
                [['user_roles', 'role_id'], ['roles', 'id']],
            ],
            WHERE: ['users.id' => $id]
        );
    }

    /**
     * Return whether the account is verified (no pending verification token).
     *
     * @param int $id
     *
     * @return bool True when verified, false otherwise
     */
    public static function isVerified(int $id): bool
    {
        // Account considered verified when there is no verification token row.
        return !DB::exists(
            FROM: 'tokens',
            WHERE: [
                'user_id' => $id,
                'type' => TokenType::VERIFICATION->value
            ]
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
            FROM: 'users',
            WHERE: compact('id')
        );
    }

    /**
     * Create the session entry for an authenticated user, ensuring a role is attached.
     * Removes the password field before storing the user data in the session.
     *
     * @param array $user Associative user row from DB (expects 'id' present)
     *
     * @return bool True on success, false on failure (no role found)
     */
    public static function setUserSession(array $user): bool
    {
        // Accept a role already embedded in the user array (e.g. from getUserWithRole) to avoid an extra query.
        $role = $user['role'] ?? self::getUserRole($user['id']);

        if (!$role) {
            Log::error("No user role is set for user with id \"{$user['id']}\"");
            SessionController::remove('user');
            return false;
        }

        unset($user['password']);

        // Regenerate session ID to prevent session fixation attacks.
        session_regenerate_id(true);

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
        $roleId = DB::single(
            SELECT: 'role_id',
            FROM: 'user_roles',
            WHERE: ['user_id' => $userId]
        )['role_id'] ?? null;

        return $roleId ? DB::single(
            SELECT: 'name',
            FROM: 'roles',
            WHERE: ['id' => $roleId]
        )['name'] ?? null : null;
    }

    /**
     * Generate a cryptographically secure random token (hex characters), trimmed to $length.
     * When $uppercase is true the returned string is uppercased, which reads more clearly in some places.
     *
     * @param int  $length Number of characters to return
     * @param bool $uppercase Uppercase the resulting token
     *
     * @return string|null Token string or null when secure random generation fails
     */
    public static function generateToken(int $length = PASSWORD_RESET_CONFIG['token_length'], bool $uppercase = true): ?string
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
     * Expiry timestamp that a freshly-issued remember-me cookie (and its matching token row) should use.
     *
     * @return int
     */
    public static function rememberCookieExpiry(): int
    {
        return time() + (86400 * REMEMBER_ME_DURATION);
    }

    /**
     * Sets the remember-me cookie. Shared by every call site that issues one, so the cookie flags can't independently drift between them.
     *
     * @param string $token Raw (unhashed) remember-me token
     * @param int    $expiresAt Unix timestamp to expire the cookie at
     *
     * @return void
     */
    public static function setRememberCookie(string $token, int $expiresAt): void
    {
        setcookie('remember', $token, ['expires' => $expiresAt] + AppController::secureCookieFlags());
    }

    /**
     * Retrieve a user row by id.
     *
     * @param int $id User primary key
     *
     * @return array|null Database row as associative array or null if missing
     */
    public static function getUserById(int $id): ?array
    {
        return DB::single(
            SELECT: '*',
            FROM: 'users',
            WHERE: compact('id')
        ) ?: null;
    }

    /**
     * Redirect to the originally requested URL saved by requireAuth(), or to $fallback when none was stored.
     * Clears the stored URL after use so a second call can't replay it.
     * Queues a flash alert shown after the redirect when $message is given.
     *
     * @param string      $fallback Route to use when no intended URL is in session
     * @param string|null $message Optional flash alert message to show after redirecting
     * @param AlertType   $type Alert type, used only when $message is given
     * @param int         $timeout Alert timeout in seconds, used only when $message is given
     *
     * @return void
     */
    public static function intendedRedirect(string $fallback = REDIRECT, ?string $message = null, AlertType $type = AlertType::SUCCESS, int $timeout = 0): void
    {
        $url = SessionController::get('intended_url') ?? $fallback;
        SessionController::remove('intended_url');

        if ($message !== null) PageController::redirectWithAlert($url, $message, $type, $timeout);
        else PageController::redirect($url);
    }

    /**
     * Ensure the current request is performed by an authenticated user, optionally enforcing role-based access.
     * An unauthenticated user has the intended URL stored in session and is redirected to the login page.
     * A user who must change their password, on a route that doesn't explicitly allow it, is redirected to the change-password flow.
     *
     * @param Role[]|null $allowedRoles Roles that are allowed, or null to allow any authenticated user
     * @param bool        $allowPasswordChange If true, allow access even when the user must change password
     *
     * @return void (will redirect/exit on access denial)
     *
     * @throws JsonException
     */
    public static function requireAuth(?array $allowedRoles = null, bool $allowPasswordChange = false): void
    {
        $user = SessionController::get('user');

        if (!$user) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';

            // Skip auth routes themselves, so a login redirect doesn't loop back into login.
            self::setIntendedUrl($uri, '#^/(login|logout|register)(/|$)#i');

            PageController::redirect('login');
            exit;
        }

        // Re-validated from the database on every protected request, so deactivations
        // and role changes take effect immediately rather than at next login.
        $fresh = self::getUserWithRole((int)$user['id']);
        if (!$fresh || $fresh['status'] !== UserStatus::ACTIVE->value) {
            SessionController::remove('user');
            PageController::redirectWithAlert(REDIRECT, 'Your session has been invalidated. Please log in again.', AlertType::ERROR, 4);
            exit;
        }

        if (empty($fresh['role'])) {
            SessionController::remove('user');
            PageController::redirectWithAlert(REDIRECT, self::ACCOUNT_ISSUE_MESSAGE, AlertType::ERROR, 4);
            exit;
        }

        // A password change invalidates every session issued before it, on any device.
        // Not just the remember-me token updatePassword() already revokes.
        if (($user['password_changed_at'] ?? null) !== ($fresh['password_changed_at'] ?? null)) {
            SessionController::remove('user');
            PageController::redirectWithAlert('login', 'Your password was changed. Please log in again.', AlertType::INFO, 4);
            exit;
        }

        // Sync session with fresh DB data every request, not just on role change.
        unset($fresh['password']);
        SessionController::set('user', $fresh);
        $user = $fresh;

        // If the account requires a password change and this route doesn't
        // explicitly allow that, force a redirect to the change-password page.
        if (!$allowPasswordChange && $user['must_change_password']) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';

            // Skip the change-password route itself, so the redirect back doesn't loop into it.
            self::setIntendedUrl($uri, '#^/(change-password|login|logout)(/|$)#i');

            PageController::redirectWithAlert('change-password', 'Before you can continue, you must change your password!', AlertType::WARNING, 4);
            exit;
        }

        if ($allowedRoles !== null && !in_array($user['role'], array_map(static fn(Role $r) => $r->value, $allowedRoles), true)) {
            PageController::error(ErrorCode::FORBIDDEN);
            exit;
        }
    }

    /**
     * Store $uri as the post-login/post-password-change redirect target.
     * Skips storing when $uri fails isSafeRedirectPath() or matches $excludePattern (a route that would redirect back into itself).
     * The only path that writes 'intended_url' to the session, so every call site gets the safety check.
     *
     * @param string $uri Request URI to store
     * @param string $excludePattern preg_match() pattern for routes to skip storing
     *
     * @return void
     */
    private static function setIntendedUrl(string $uri, string $excludePattern): void
    {
        if ($uri && self::isSafeRedirectPath($uri) && !preg_match($excludePattern, $uri)) SessionController::set('intended_url', $uri);
    }

    /**
     * Whether $uri is safe to store and later redirect to.
     * Only a plain root-relative path passes - anything else risks a protocol-relative offsite redirect.
     *
     * @param string $uri
     *
     * @return bool
     */
    private static function isSafeRedirectPath(string $uri): bool
    {
        return (bool)preg_match('#^/(?![/\\\\])#', $uri);
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
            FormController::addAlert($message, AlertType::WARNING);
            return false;
        }

        return true;
    }

    /**
     * Build and cache the regular expression used to validate passwords against PASSWORD_CONFIG.
     *
     * @return array{0:string,1:string} [regex pattern, error message]
     */
    private static function getPasswordRules(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $rules = array_filter([
            PASSWORD_CONFIG['require_lowercase'] ? ['(?=.*[a-z])', '1 lowercase letter'] : null,
            PASSWORD_CONFIG['require_uppercase'] ? ['(?=.*[A-Z])', '1 uppercase letter'] : null,
            PASSWORD_CONFIG['require_number'] ? ['(?=.*\d)', '1 number'] : null,
            PASSWORD_CONFIG['require_special_character'] ? ['(?=.*[^a-zA-Z\d])', '1 special character'] : null,
        ]);

        $pattern = '/^' . implode('', array_column($rules, 0)) . '.{' . PASSWORD_CONFIG['min_length'] . ',}$/';

        $messages = array_column($rules, 1);
        array_unshift($messages, "at least " . PASSWORD_CONFIG['min_length'] . " characters");
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
     * Return the password regex pattern's source (anchors included, no delimiters), for client-side use with the JS RegExp constructor.
     * Stays in sync with validatePassword() since both read the same cached getPasswordRules() pattern.
     *
     * @return string
     */
    public static function getPasswordPatternSource(): string
    {
        return substr(self::getPasswordRules()[0], 1, -1);
    }

    /**
     * Get the relative path to a user's profile image if set.
     * Returns null when no profile image is configured.
     *
     * @param int $id User id
     *
     * @return string|null Path relative to public root (e.g. 'img/profile/...') or null
     */
    public static function getProfileImage(int $id): ?string
    {
        $profile_img = DB::single(
            SELECT: 'profile_img',
            FROM: 'users',
            WHERE: compact('id')
        )['profile_img'] ?? null;

        return $profile_img ? PROFILE_IMAGE_CONFIG['path'] . $profile_img : null;
    }

    /**
     * Generate a random password that always satisfies PASSWORD_CONFIG's policy.
     * Uses an unambiguous charset (no 0/O/1/l/I) since it's meant to be typed by a human.
     *
     * @param int $length Desired password length
     *
     * @return string|null Generated password or null on failure
     */
    public static function generatePassword(int $length = PASSWORD_CONFIG['generated_length']): ?string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $all = $upper . $lower . $digits;

        try {
            $password = [
                $upper[random_int(0, strlen($upper) - 1)],
                $lower[random_int(0, strlen($lower) - 1)],
                $digits[random_int(0, strlen($digits) - 1)],
            ];

            for ($i = count($password); $i < $length; $i++) $password[] = $all[random_int(0, strlen($all) - 1)];

            // Fisher-Yates shuffle so the guaranteed characters aren't always in the same position.
            for ($i = count($password) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$password[$i], $password[$j]] = [$password[$j], $password[$i]];
            }

            return implode('', $password);
        } catch (Exception $e) {
            Log::error("Could not generate password: {$e->getMessage()}");
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
            FROM: 'users',
            WHERE: compact('email')
        );
    }

    /**
     * Whether an account exists and still has a pending verification.
     * Shared by every page that must respond identically to a missing account and an already-verified one, so neither can be used to enumerate account existence or verification status.
     *
     * @param int $id
     *
     * @return bool
     */
    public static function needsVerification(int $id): bool
    {
        return self::exists($id) && !self::isVerified($id);
    }

    /**
     * Whether $email belongs to a user other than $excludeId - the check a profile/edit form needs before saving a changed email address.
     *
     * @param string $email
     * @param int    $excludeId User id allowed to already have this email
     *
     * @return bool
     */
    public static function emailTakenByOtherUser(string $email, int $excludeId): bool
    {
        $id = self::getUserIdByEmail($email);
        return $id !== null && $id !== $excludeId;
    }

    /**
     * Resolve a user's id by their email address.
     *
     * @param string $email
     *
     * @return int|null Id or null when not found
     */
    public static function getUserIdByEmail(string $email): ?int
    {
        $user = DB::single(
            SELECT: 'id',
            FROM: 'users',
            WHERE: compact('email')
        );

        return $user ? (int)$user['id'] : null;
    }

    /**
     * Whether $username belongs to a user other than $excludeId - the check a profile/edit form needs before saving a changed username.
     *
     * @param string $username
     * @param int    $excludeId User id allowed to already have this username
     *
     * @return bool
     */
    public static function usernameTakenByOtherUser(string $username, int $excludeId): bool
    {
        $id = self::getUserIdByUsername($username);
        return $id !== null && $id !== $excludeId;
    }

    /**
     * Resolve a user's id by their username.
     *
     * @param string $username
     *
     * @return int|null Id or null when not found
     */
    public static function getUserIdByUsername(string $username): ?int
    {
        $user = DB::single(
            SELECT: 'id',
            FROM: 'users',
            WHERE: compact('username')
        );

        return $user ? (int)$user['id'] : null;
    }

    /**
     * Verify that a provided token matches the stored token for the given user id and token type, and hasn't expired.
     * Comparison is case-insensitive.
     *
     * @param int       $id User id
     * @param string    $token Token to check
     * @param TokenType $type Token type
     *
     * @return bool True if tokens match and the token hasn't expired
     */
    public static function checkToken(int $id, string $token, TokenType $type): bool
    {
        $row = DB::single(
            SELECT: ['token', 'expires'],
            FROM: 'tokens',
            WHERE: [
                'user_id' => $id,
                'type' => $type->value
            ]
        );

        if (!$row) return false;
        if ($row['expires'] !== null && strtotime((string)$row['expires']) < time()) return false;

        // Constant-time comparison to prevent timing attacks; case-insensitive for human-readable tokens.
        return $token
                |> strtoupper(...)
                |> (static fn($x) => hash('sha256', $x))
                |> (static fn($x) => hash_equals($row['token'], $x));
    }

    /**
     * Check a provided plaintext password against the stored hash for the user identified by email.
     *
     * @param string $email
     * @param string $password Plaintext password to verify
     *
     * @return bool True when the password matches
     */
    public static function checkPassword(string $email, string $password): bool
    {
        return new Timebox()->call(function (Timebox $timebox) use ($email, $password) {
            $hash = DB::single(
                SELECT: 'password',
                FROM: 'users',
                WHERE: compact('email')
            )['password'] ?? null;

            if ($hash && password_verify($password, $hash)) {
                $timebox->returnEarly();
                return true;
            }

            if (!$hash) password_hash($password, PASSWORD_CONFIG['hash_algo'], PASSWORD_CONFIG['hash_options']);
            return false;
        }, TIMING_FLOOR_MS['password_confirm'] * 1000);
    }

    /**
     * Verify credentials for an identifier that may be an email or username.
     * Every failure path takes at least TIMING_FLOOR_MS['login'] (hashing a dummy password when the identifier doesn't resolve), so timing can't reveal why it failed.
     *
     * @param string $identifier Email or username
     * @param string $password Plaintext password to verify
     *
     * @return array|null Matched user row on success, null on any failure
     */
    public static function verifyCredentials(string $identifier, string $password): ?array
    {
        return new Timebox()->call(function (Timebox $timebox) use ($identifier, $password) {
            $user = self::getUserByIdentifier($identifier);

            if ($user) {
                if (!password_verify($password, $user['password'])) return null;
                $timebox->returnEarly();
                return $user;
            }

            password_hash($password, PASSWORD_CONFIG['hash_algo'], PASSWORD_CONFIG['hash_options']);
            return null;
        }, TIMING_FLOOR_MS['login'] * 1000);
    }

    /**
     * Find a user by either email or username. Email is tried first.
     *
     * @param string $identifier
     *
     * @return array|null DB row or null
     */
    public static function getUserByIdentifier(string $identifier): ?array
    {
        $user = self::getUserByEmail($identifier);
        if ($user) return $user;

        return DB::single(
            SELECT: '*',
            FROM: 'users',
            WHERE: [
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
    public static function getUserByEmail(string $email): ?array
    {
        return DB::single(
            SELECT: '*',
            FROM: 'users',
            WHERE: compact('email')
        ) ?: null;
    }

    /**
     * Update a user's password and clear the "must_change_password" flag.
     * Password is hashed with configured algorithm/options constants.
     *
     * @param int    $id
     * @param string $password Plaintext new password (will be hashed)
     *
     * @return string The new password_changed_at value.
     *                A caller with a live session for this user (e.g. ChangePasswordPage) must copy it into that session's own cached user data, or requireAuth() will treat that same session as stale too.
     */
    public static function updatePassword(int $id, string $password): string
    {
        $passwordChangedAt = date('Y-m-d H:i:s');

        DB::update(
            UPDATE: 'users',
            SET: [
                'password' => password_hash($password, PASSWORD_CONFIG['hash_algo'], PASSWORD_CONFIG['hash_options']),
                'must_change_password' => 0,
                'password_changed_at' => $passwordChangedAt
            ],
            WHERE: compact('id')
        );

        // A password change must not leave any persistent auto-login alive on other devices.
        self::deleteToken($id, TokenType::REMEMBER);

        return $passwordChangedAt;
    }

    /**
     * Remove a token record for a user by type.
     *
     * @param int       $userId
     * @param TokenType $type
     *
     * @return void
     */
    public static function deleteToken(int $userId, TokenType $type): void
    {
        DB::delete(
            FROM: 'tokens',
            WHERE: [
                'user_id' => $userId,
                'type' => $type->value
            ]
        );
    }

    /**
     * Persist a login attempt record with IP, user agent and success flag.
     * The user_id is resolved from the provided identifier (email or username) when not already known.
     *
     * @param string      $identifier
     * @param bool        $success
     * @param string|null $failedReason
     * @param int|null    $userId Pre-resolved user id, to skip a redundant lookup
     *
     * @return void
     */
    public static function recordLoginAttempt(string $identifier, bool $success, ?string $failedReason = null, ?int $userId = null): void
    {
        DB::insert(
            INTO: 'login_attempts',
            VALUES: [
                'user_id' => $userId ?? self::getUserIdByIdentifier($identifier),
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
    public static function getUserIdByIdentifier(string $identifier): ?int
    {
        return self::getUserIdByEmail($identifier) ?? self::getUserIdByUsername($identifier);
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
            UPDATE: 'users',
            SET: [
                'last_login' => date('Y-m-d H:i:s')
            ],
            WHERE: compact('email')
        );
    }

    /**
     * Generates a verification token, stores it, and emails it to the user.
     * Used by both the initial registration flow and the resend flow, which only differ in the alert message shown for each outcome, so the caller still picks that based on the result.
     *
     * @param int    $id
     * @param string $email
     *
     * @return bool True if token generation and email queueing succeeded, false otherwise.
     *              Under FastCGI the actual delivery is deferred past this call, so true only guarantees generation and queueing, not that the email reached the recipient.
     */
    public static function issueVerificationToken(int $id, string $email): bool
    {
        $token = self::generateToken(VERIFICATION_CONFIG['token_length']);

        if ($token === null) {
            Log::error("Could not generate a verification token for user id \"$id\"");
            return false;
        }

        self::createToken($id, hash('sha256', $token), TokenType::VERIFICATION, date('Y-m-d H:i:s', time() + VERIFICATION_CONFIG['token_expiry']));

        return self::sendVerificationMail($id, $email, $token);
    }

    /**
     * Create or replace a token of the given type for a user.
     * Any existing token of the same type for that user is removed before insertion.
     *
     * @param int         $userId
     * @param string      $token
     * @param TokenType   $type
     * @param string|null $expires Optional expiry timestamp value
     *
     * @return void
     */
    public static function createToken(int $userId, string $token, TokenType $type, ?string $expires = null): void
    {
        DB::delete(
            FROM: 'tokens',
            WHERE: [
                'user_id' => $userId,
                'type' => $type->value
            ]
        );

        $data = [
            'user_id' => $userId,
            'token' => $token,
            'type' => $type->value
        ];
        if ($expires) $data['expires'] = $expires;

        DB::insert(
            INTO: 'tokens',
            VALUES: $data
        );
    }

    /**
     * Send a verification email containing a one-time code/link.
     * Does not redirect; the caller owns the post-send redirect.
     *
     * @param int    $id
     * @param string $to
     * @param string $code
     *
     * @return bool True if the email was sent (or queued) successfully
     */
    public static function sendVerificationMail(int $id, string $to, string $code): bool
    {
        $contents = MailController::template('verification', [
            'title' => 'Account Verification - ' . APP_NAME,
            'link' => Url::absolute("verify-account/$id/$code"),
            'code' => $code
        ]);

        if ($contents === false) {
            Log::error("Verification email template failed to render for user id \"$id\"");
            return false;
        }

        return MailController::send(APP_NAME, $to, MAIL_CONFIG['no_reply_address'], 'Verify account', $contents);
    }

    /**
     * Send a password reset email with a tokenized link to the reset form.
     * Failures are logged, not shown to the user: ForgotPasswordPage always shows the same generic response regardless of outcome, so this endpoint can't be used to check which emails are registered.
     *
     * @param int    $id
     * @param string $to
     * @param string $token
     *
     * @return void
     */
    public static function sendPasswordResetMail(int $id, string $to, string $token): void
    {
        $contents = MailController::template('reset-password', [
            'title' => 'Password Reset Request - ' . APP_NAME,
            'link' => Url::absolute("reset-password/$id/$token")
        ]);

        if ($contents === false) {
            Log::error("Password reset email template failed to render for user id \"$id\"");
            return;
        }

        MailController::send(APP_NAME, $to, MAIL_CONFIG['no_reply_address'], 'Reset password', $contents);
    }

    /**
     * Notify a newly-created user by email with a temporary password.
     * Does not redirect; the caller owns the single post-creation redirect and picks its message from the returned result (see Admin\Users::createUser()).
     *
     * @param string $to
     * @param string $password Temporary plaintext password
     *
     * @return bool True when the email was sent (or queued) successfully
     */
    public static function sendCreatedUserMail(string $to, string $password): bool
    {
        $contents = MailController::template('account-created', [
            'title' => 'Account Created - ' . APP_NAME,
            'link' => Url::absolute('login'),
            'password' => $password
        ]);

        if ($contents === false) {
            Log::error('Account-created email template failed to render for "{to}"', ['to' => $to]);
            return false;
        }

        return MailController::send(APP_NAME, $to, MAIL_CONFIG['no_reply_address'], 'An account has been created', $contents);
    }
}
