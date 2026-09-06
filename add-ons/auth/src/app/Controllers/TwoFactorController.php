<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Database\DB;
use app\Enums\TokenType;
use app\Enums\TwoFactorMethod;
use app\Utils\Log;
use Exception;
use Random\RandomException;

/**
 * Two-factor authentication helpers: enrolment state, the login-time email challenge, single-use recovery codes, and trusted-device cookies.
 */
class TwoFactorController
{
    private const string TRUSTED_COOKIE = 'trusted_device';
    private const int RECOVERY_CODE_LENGTH = 10;

    /**
     * Whether the user has 2FA switched on.
     *
     * @param int $userId
     *
     * @return bool
     */
    public static function isEnabledFor(int $userId): bool
    {
        return (bool)(DB::single(SELECT: 'enabled', FROM: 'user_two_factor', WHERE: ['user_id' => $userId])['enabled'] ?? false);
    }

    /**
     * Whether 2FA is mandatory for this user's role.
     *
     * @param array $user User row carrying a 'role' name
     *
     * @return bool
     */
    public static function isRequiredFor(array $user): bool
    {
        return self::roleInList($user['role'] ?? null, TWO_FACTOR_CONFIG['force_for_roles']);
    }

    /**
     * Whether a role name appears in a configured list of role names.
     *
     * @param mixed    $role
     * @param string[] $list
     *
     * @return bool
     */
    private static function roleInList(mixed $role, array $list): bool
    {
        return in_array($role, $list, true);
    }

    /**
     * The method offered first at the login challenge.
     *
     * @param int $userId
     *
     * @return TwoFactorMethod
     */
    public static function primaryMethod(int $userId): TwoFactorMethod
    {
        return TwoFactorMethod::tryFrom(self::settingsFor($userId)['primary_method'] ?? '') ?? TwoFactorMethod::EMAIL;
    }

    /**
     * The user_two_factor row, or null when the user has never touched 2FA.
     *
     * @param int $userId
     *
     * @return array|null
     */
    public static function settingsFor(int $userId): ?array
    {
        return DB::single(SELECT: '*', FROM: 'user_two_factor', WHERE: ['user_id' => $userId]);
    }

    /**
     * Every method the user has enrolled in, primary first.
     *
     * @param int $userId
     *
     * @return TwoFactorMethod[]
     */
    public static function enabledMethods(int $userId): array
    {
        $row = self::settingsFor($userId);
        if (!$row) return [];

        $methods = array_values(array_filter(
            TwoFactorMethod::cases(),
            static fn(TwoFactorMethod $m) => !empty($row[$m->value . '_enabled'])
        ));

        usort($methods, static fn(TwoFactorMethod $a, TwoFactorMethod $b) => ($b->value === $row['primary_method']) <=> ($a->value === $row['primary_method']));

        return $methods;
    }

    /**
     * Turn 2FA on with email as the initial method, returning a fresh batch of recovery codes to show once.
     *
     * @param int $userId
     *
     * @return string[] Plaintext recovery codes
     */
    public static function enable(int $userId): array
    {
        $settings = ['enabled' => 1, 'email_enabled' => 1, 'primary_method' => TwoFactorMethod::EMAIL->value];

        if (DB::exists(FROM: 'user_two_factor', WHERE: ['user_id' => $userId])) {
            DB::update(UPDATE: 'user_two_factor', SET: $settings, WHERE: ['user_id' => $userId]);
        } else {
            DB::insert(INTO: 'user_two_factor', VALUES: ['user_id' => $userId] + $settings);
        }

        return self::regenerateRecoveryCodes($userId);
    }

    /**
     * Replace the user's recovery codes with a fresh batch, returning the plaintext to show once.
     *
     * @param int $userId
     *
     * @return string[]
     */
    public static function regenerateRecoveryCodes(int $userId): array
    {
        DB::delete(FROM: 'two_factor_recovery_codes', WHERE: ['user_id' => $userId]);

        $codes = [];
        for ($i = 0; $i < TWO_FACTOR_CONFIG['recovery_code_count']; $i++) {
            $code = AuthController::generateToken(self::RECOVERY_CODE_LENGTH);
            if ($code === null) break;

            $codes[] = $code;
            DB::insert(INTO: 'two_factor_recovery_codes', VALUES: [
                'user_id' => $userId,
                'code_hash' => hash('sha256', $code),
            ]);
        }

        return $codes;
    }

    /**
     * Remove every trace of 2FA for a user: settings, passkeys, recovery codes, trusted devices, and any pending email code.
     *
     * @param int $userId
     *
     * @return void
     */
    public static function disableAll(int $userId): void
    {
        foreach (['user_two_factor', 'webauthn_credentials', 'two_factor_recovery_codes', 'trusted_devices'] as $table) {
            DB::delete(FROM: $table, WHERE: ['user_id' => $userId]);
        }

        AuthController::deleteToken($userId, TokenType::TWO_FACTOR_EMAIL);
    }

    /**
     * Generate a numeric login code, store its hash, and email it. Replaces any code already outstanding for the user.
     *
     * @param int    $userId
     * @param string $email
     *
     * @return bool True when the mail was sent or queued
     */
    public static function issueEmailChallenge(int $userId, string $email): bool
    {
        try {
            $code = self::numericCode();
        } catch (RandomException $e) {
            Log::error("Could not generate a two-factor code for user id \"$userId\": {$e->getMessage()}");
            return false;
        }

        AuthController::createToken(
            $userId,
            hash('sha256', $code),
            TokenType::TWO_FACTOR_EMAIL,
            date('Y-m-d H:i:s', time() + TWO_FACTOR_CONFIG['email_code_expiry'])
        );

        $contents = MailController::template('two-factor-code', [
            'title' => 'Your login code - ' . APP_NAME,
            'code' => $code,
        ]);

        if ($contents === false) {
            Log::error("Two-factor email template failed to render for user id \"$userId\"");
            return false;
        }

        return MailController::send(APP_NAME, $email, MAIL_CONFIG['no_reply_address'], 'Your login code', $contents);
    }

    /**
     * A zero-padded random numeric code of TWO_FACTOR_CONFIG['code_length'] digits.
     *
     * @return string
     *
     * @throws RandomException
     */
    private static function numericCode(): string
    {
        $length = TWO_FACTOR_CONFIG['code_length'];
        return str_pad((string)random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a submitted email code, consuming it on success.
     *
     * @param int    $userId
     * @param string $code
     *
     * @return bool
     */
    public static function verifyEmailChallenge(int $userId, string $code): bool
    {
        if (!AuthController::checkToken($userId, $code, TokenType::TWO_FACTOR_EMAIL)) return false;

        AuthController::deleteToken($userId, TokenType::TWO_FACTOR_EMAIL);
        return true;
    }

    /**
     * Redeem one unused recovery code, marking it used. Case-insensitive.
     *
     * @param int    $userId
     * @param string $code
     *
     * @return bool
     */
    public static function consumeRecoveryCode(int $userId, string $code): bool
    {
        $row = DB::single(SELECT: 'id', FROM: 'two_factor_recovery_codes', WHERE: [
            'user_id' => $userId,
            'code_hash' => hash('sha256', strtoupper(trim($code))),
            'used_at' => null,
        ]);

        return $row !== null && DB::update(
                UPDATE: 'two_factor_recovery_codes',
                SET: ['used_at' => date('Y-m-d H:i:s')],
                WHERE: ['id' => $row['id']]
            );
    }

    /**
     * How many of the user's recovery codes are still unused.
     *
     * @param int $userId
     *
     * @return int
     */
    public static function recoveryCodesRemaining(int $userId): int
    {
        return DB::count(FROM: 'two_factor_recovery_codes', WHERE: ['user_id' => $userId, 'used_at' => null]);
    }

    /**
     * Whether ticking "remember me" should also skip 2FA on this device for $user.
     * False when the user has 2FA off, opted out, is in a force-off role, or device-remembering is disabled globally.
     *
     * @param int   $userId
     * @param array $user User row carrying a 'role' name
     *
     * @return bool
     */
    public static function shouldRememberDevice(int $userId, array $user): bool
    {
        if (!TWO_FACTOR_CONFIG['trusted_device']['allow']) return false;
        if (self::roleInList($user['role'] ?? null, TWO_FACTOR_CONFIG['trusted_device']['force_off_for_roles'])) return false;

        $row = self::settingsFor($userId);
        return $row !== null && !empty($row['enabled']) && !empty($row['remember_device']);
    }

    /**
     * Issue a trusted-device cookie for $userId and record it, so future logins from this browser skip 2FA until it expires.
     *
     * @param int $userId
     *
     * @return void
     */
    public static function trustDevice(int $userId): void
    {
        try {
            $selector = bin2hex(random_bytes(16));
            $validator = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            Log::error("Could not issue a trusted-device token for user id \"$userId\": {$e->getMessage()}");
            return;
        }

        $expires = AuthController::rememberCookieExpiry();

        DB::insert(INTO: 'trusted_devices', VALUES: [
            'user_id' => $userId,
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'label' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255) ?: null,
            'expires' => date('Y-m-d H:i:s', $expires),
        ]);

        setcookie(self::TRUSTED_COOKIE, "$selector:$validator", ['expires' => $expires] + AppController::secureCookieFlags());
    }

    /**
     * Whether the current request carries a valid, unexpired trusted-device cookie for $userId.
     *
     * @param int $userId
     *
     * @return bool
     */
    public static function deviceIsTrusted(int $userId): bool
    {
        $cookie = $_COOKIE[self::TRUSTED_COOKIE] ?? '';
        if (!str_contains($cookie, ':')) return false;

        [$selector, $validator] = explode(':', $cookie, 2);

        $row = DB::single(SELECT: ['validator_hash', 'expires'], FROM: 'trusted_devices', WHERE: [
            'user_id' => $userId,
            'selector' => $selector,
        ]);

        if (!$row || strtotime((string)$row['expires']) < time()) return false;

        return hash_equals($row['validator_hash'], hash('sha256', $validator));
    }

    /**
     * Delete the trusted-device row for the current cookie and clear the cookie.
     *
     * @return void
     */
    public static function forgetThisDevice(): void
    {
        $cookie = $_COOKIE[self::TRUSTED_COOKIE] ?? '';
        if (str_contains($cookie, ':')) DB::delete(FROM: 'trusted_devices', WHERE: ['selector' => explode(':', $cookie, 2)[0]]);

        setcookie(self::TRUSTED_COOKIE, '', ['expires' => time() - 3600] + AppController::secureCookieFlags());
    }

    /**
     * Revoke every trusted device for a user.
     *
     * @param int $userId
     *
     * @return void
     */
    public static function forgetAllDevices(int $userId): void
    {
        DB::delete(FROM: 'trusted_devices', WHERE: ['user_id' => $userId]);
    }
}
