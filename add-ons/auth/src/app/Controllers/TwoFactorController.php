<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Database\DB;
use app\Enums\TokenType;
use app\Enums\TwoFactorMethod;
use app\Utils\Crypto;
use app\Utils\Log;
use app\Utils\UserAgentParser;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Exception;
use OTPHP\TOTP;
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
     * Restricts a submitted list of method slugs to known values, for storing as a role's or user's
     * required_2fa_methods override.
     *
     * @param mixed $submitted Raw $_POST value, expected to be a string[] but not trusted
     *
     * @return string|null Comma-separated known slugs, or null when none were selected
     */
    public static function sanitizeRequiredMethods(mixed $submitted): ?string
    {
        $valid = array_column(TwoFactorMethod::cases(), 'value');
        $methods = array_values(array_intersect(is_array($submitted) ? $submitted : [], $valid));

        return $methods ? implode(',', $methods) : null;
    }

    /**
     * Methods required for $user (per requiredMethodsFor()) that they have not yet enrolled in.
     *
     * @param array $user
     *
     * @return string[]
     */
    public static function missingRequiredMethods(array $user): array
    {
        $enrolled = array_map(static fn(TwoFactorMethod $m) => $m->value, self::enabledMethods((int)$user['id']));

        return array_diff(self::requiredMethodsFor($user), $enrolled);
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
     * Method slugs ('email'/'totp'/'passkey') $user's account policy requires enrolled: the user's own
     * override when set, else their role's, else none.
     *
     * @param array $user User row carrying an optional 'required_2fa_methods' override and a 'role' name
     *
     * @return string[]
     */
    public static function requiredMethodsFor(array $user): array
    {
        $raw = $user['required_2fa_methods'] ?? null;

        if ($raw === null && !empty($user['role'])) {
            $raw = DB::single(SELECT: 'required_2fa_methods', FROM: 'roles', WHERE: ['name' => $user['role']])['required_2fa_methods'] ?? null;
        }

        return $raw ? explode(',', $raw) : [];
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
     * Turn 2FA on with email as the initial method, returning a fresh batch of recovery codes to show once.
     *
     * @param int $userId
     *
     * @return string[] Plaintext recovery codes
     */
    public static function enable(int $userId): array
    {
        self::upsertSettings($userId, ['enabled' => 1, 'email_enabled' => 1, 'primary_method' => TwoFactorMethod::EMAIL->value]);

        return self::regenerateRecoveryCodes($userId);
    }

    /**
     * Insert or merge $data into the user's user_two_factor row.
     *
     * @param int                  $userId
     * @param array<string, mixed> $data
     *
     * @return void
     */
    private static function upsertSettings(int $userId, array $data): void
    {
        if (DB::exists(FROM: 'user_two_factor', WHERE: ['user_id' => $userId])) {
            DB::update(UPDATE: 'user_two_factor', SET: $data, WHERE: ['user_id' => $userId]);
        } else {
            DB::insert(INTO: 'user_two_factor', VALUES: ['user_id' => $userId] + $data);
        }
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
     * Set which enrolled method the login challenge offers first.
     *
     * @param int             $userId
     * @param TwoFactorMethod $method
     *
     * @return void
     */
    public static function setPrimaryMethod(int $userId, TwoFactorMethod $method): void
    {
        self::upsertSettings($userId, ['primary_method' => $method->value]);
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
     * The expiry check reads the row first so a NULL expires can be treated as "never expires", matching AuthController::checkToken().
     * Consumption itself is still one guarded DELETE keyed on the token match, the same way consumeRecoveryCode()'s guarded UPDATE is, so two concurrent submissions of the same code can't both win.
     *
     * @param int    $userId
     * @param string $code
     *
     * @return bool
     */
    public static function verifyEmailChallenge(int $userId, string $code): bool
    {
        $tokenHash = hash('sha256', strtoupper($code));

        $where = [
            'user_id' => $userId,
            'type' => TokenType::TWO_FACTOR_EMAIL->value,
            'token' => $tokenHash,
        ];

        $row = DB::single(SELECT: ['expires'], FROM: 'tokens', WHERE: $where);
        if (!$row || ($row['expires'] !== null && strtotime((string)$row['expires']) < time())) return false;

        return DB::delete(FROM: 'tokens', WHERE: $where);
    }

    /**
     * Whether an unexpired login email code is already outstanding for the user.
     * NULL expires counts as "never expires", matching AuthController::checkToken().
     *
     * @param int $userId
     *
     * @return bool
     */
    public static function hasPendingEmailChallenge(int $userId): bool
    {
        $row = DB::single(SELECT: ['expires'], FROM: 'tokens', WHERE: [
            'user_id' => $userId,
            'type' => TokenType::TWO_FACTOR_EMAIL->value,
        ]);

        return $row !== null && ($row['expires'] === null || strtotime((string)$row['expires']) >= time());
    }

    /**
     * Stage a fresh, unconfirmed TOTP secret for the user, replacing any earlier staged one.
     *
     * @param int $userId
     *
     * @return void
     */
    public static function beginTotpEnrolment(int $userId): void
    {
        // Never overwrite a confirmed secret: that would silently break a working authenticator.
        if (!empty(self::settingsFor($userId)['totp_enabled'])) return;

        self::upsertSettings($userId, [
            'totp_secret' => Crypto::encrypt(TOTP::generate(secretSize: 20)->getSecret()),
            'totp_confirmed_at' => null,
            'totp_enabled' => 0,
        ]);
    }

    /**
     * The QR code, otpauth URI and raw secret for a staged (unconfirmed) TOTP secret, or null when none is staged.
     *
     * @param int    $userId
     * @param string $accountLabel Shown next to the code in the authenticator app
     *
     * @return array{secret: string, uri: string, svg: string}|null
     */
    public static function totpSetupData(int $userId, string $accountLabel): ?array
    {
        $row = self::settingsFor($userId);
        if ($row === null || empty($row['totp_secret']) || !empty($row['totp_enabled'])) return null;

        $secret = Crypto::decrypt($row['totp_secret']);
        if ($secret === null) return null;

        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($accountLabel);
        $totp->setIssuer(APP_NAME);
        $uri = $totp->getProvisioningUri();

        return ['secret' => $secret, 'uri' => $uri, 'svg' => self::qrSvg($uri)];
    }

    /**
     * Render an otpauth:// URI as an inline SVG QR code (no XML prolog, drops straight into a page).
     *
     * @param string $uri
     *
     * @return string
     */
    private static function qrSvg(string $uri): string
    {
        $svg = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd()))->writeString($uri);

        return substr($svg, strpos($svg, '<svg') ?: 0);
    }

    /**
     * Turn the staged TOTP secret into an active method after the user proves they can generate a code.
     * Enables 2FA (with TOTP as the primary method) when this is the account's first factor.
     *
     * @param int    $userId
     * @param string $code
     *
     * @return bool
     */
    public static function confirmTotp(int $userId, string $code): bool
    {
        $row = self::settingsFor($userId);
        if ($row === null || empty($row['totp_secret']) || !empty($row['totp_enabled'])) return false;

        $secret = Crypto::decrypt($row['totp_secret']);
        if ($secret === null || !TOTP::createFromSecret($secret)->verify($code, null, self::totpLeeway())) return false;

        // email_enabled stays on so an emailed code is always available as a fallback.
        $data = ['totp_enabled' => 1, 'totp_confirmed_at' => date('Y-m-d H:i:s'), 'enabled' => 1, 'email_enabled' => 1];
        $firstFactor = empty($row['enabled']);
        if ($firstFactor) $data['primary_method'] = TwoFactorMethod::TOTP->value;

        self::upsertSettings($userId, $data);

        if ($firstFactor) self::issueFirstFactorRecoveryCodes($userId);

        return true;
    }

    /**
     * Clock-drift tolerance for a TOTP check, capped just under the 30s period as otphp requires.
     *
     * @return int
     */
    private static function totpLeeway(): int
    {
        return min(29, TWO_FACTOR_CONFIG['totp_leeway']);
    }

    /**
     * Issue recovery codes when 2FA is first turned on via TOTP or a passkey rather than email.
     * Stashes them for the settings page's one-time display.
     * Centralised here so "2FA enabled" always implies "recovery codes exist", whichever method turned it on.
     *
     * @param int $userId
     *
     * @return void
     */
    private static function issueFirstFactorRecoveryCodes(int $userId): void
    {
        SessionController::set('2fa_new_recovery_codes', self::regenerateRecoveryCodes($userId));
    }

    /**
     * Verify a TOTP code at the login challenge.
     *
     * @param int    $userId
     * @param string $code
     *
     * @return bool
     */
    public static function verifyTotp(int $userId, string $code): bool
    {
        $row = self::settingsFor($userId);
        if ($row === null || empty($row['totp_enabled']) || empty($row['totp_secret'])) return false;

        $secret = Crypto::decrypt($row['totp_secret']);
        return $secret !== null && TOTP::createFromSecret($secret)->verify($code, null, self::totpLeeway());
    }

    /**
     * Remove the authenticator-app method, falling back to email as the primary.
     *
     * @param int $userId
     *
     * @return void
     */
    public static function disableTotp(int $userId): void
    {
        $data = ['totp_secret' => null, 'totp_confirmed_at' => null, 'totp_enabled' => 0];

        // Only fall back to email when TOTP was actually the primary; a passkey/email primary is left alone.
        if ((self::settingsFor($userId)['primary_method'] ?? null) === TwoFactorMethod::TOTP->value) {
            $data['primary_method'] = TwoFactorMethod::EMAIL->value;
        }

        self::upsertSettings($userId, $data);
    }

    /**
     * The user's registered passkeys, newest first, for display in security settings.
     *
     * @param int $userId
     *
     * @return array<int, array<string, mixed>>
     */
    public static function passkeysFor(int $userId): array
    {
        return DB::select(
            SELECT: ['id', 'name', 'created_at', 'last_used_at'],
            FROM: 'webauthn_credentials',
            WHERE: ['user_id' => $userId],
            ORDER_BY: 'created_at DESC'
        );
    }

    /**
     * Options JSON for registering a new passkey, excluding the ones the user already has.
     *
     * @param int    $userId
     * @param string $userLabel
     *
     * @return string
     */
    public static function passkeyRegistrationOptions(int $userId, string $userLabel): string
    {
        $existing = array_column(DB::select(SELECT: 'credential_id', FROM: 'webauthn_credentials', WHERE: ['user_id' => $userId]), 'credential_id');

        return WebauthnController::registrationOptions($userId, $userLabel, $existing);
    }

    /**
     * Validate a passkey registration response and store it. Enables 2FA (passkey primary) when it is the first factor.
     *
     * @param int    $userId
     * @param string $clientJson
     * @param string $name User-facing label for the passkey
     *
     * @return bool
     */
    public static function savePasskey(int $userId, string $clientJson, string $name): bool
    {
        $data = WebauthnController::verifyRegistration($clientJson);
        if ($data === null) return false;

        DB::insert(INTO: 'webauthn_credentials', VALUES: [
            'user_id' => $userId,
            'credential_id' => $data['credential_id'],
            'public_key' => $data['record'],
            'sign_count' => $data['sign_count'],
            'transports' => $data['transports'] ?: null,
            'aaguid' => $data['aaguid'] ?: null,
            'name' => mb_substr(trim($name), 0, 100) ?: 'Passkey',
        ]);

        $row = self::settingsFor($userId);
        $settings = ['passkey_enabled' => 1, 'enabled' => 1, 'email_enabled' => 1];
        $firstFactor = empty($row['enabled']);
        if ($firstFactor) $settings['primary_method'] = TwoFactorMethod::PASSKEY->value;

        self::upsertSettings($userId, $settings);

        if ($firstFactor) self::issueFirstFactorRecoveryCodes($userId);

        return true;
    }

    /**
     * Options JSON for a passkey login challenge.
     *
     * @param int $userId
     *
     * @return string
     */
    public static function passkeyLoginOptions(int $userId): string
    {
        $ids = array_column(DB::select(SELECT: 'credential_id', FROM: 'webauthn_credentials', WHERE: ['user_id' => $userId]), 'credential_id');

        return WebauthnController::loginOptions($ids);
    }

    /**
     * Verify a passkey assertion at the login challenge, bumping the stored signature counter.
     *
     * @param int    $userId
     * @param string $clientJson
     *
     * @return bool
     */
    public static function verifyPasskey(int $userId, string $clientJson): bool
    {
        $credentialId = WebauthnController::credentialIdFromResponse($clientJson);
        if ($credentialId === null) return false;

        $row = DB::single(SELECT: ['id', 'public_key'], FROM: 'webauthn_credentials', WHERE: ['user_id' => $userId, 'credential_id' => $credentialId]);
        if ($row === null) return false;

        $updated = WebauthnController::verifyLogin($clientJson, $row['public_key'], (string)$userId);
        if ($updated === null) return false;

        DB::update(UPDATE: 'webauthn_credentials', SET: [
            'public_key' => $updated['record'],
            'sign_count' => $updated['sign_count'],
            'last_used_at' => date('Y-m-d H:i:s'),
        ], WHERE: ['id' => $row['id']]);

        return true;
    }

    /**
     * Delete one of the user's passkeys, turning the method off (and demoting it as primary) when it was the last.
     *
     * @param int $userId
     * @param int $passkeyId
     *
     * @return void
     */
    public static function deletePasskey(int $userId, int $passkeyId): void
    {
        DB::delete(FROM: 'webauthn_credentials', WHERE: ['id' => $passkeyId, 'user_id' => $userId]);

        if (DB::count(FROM: 'webauthn_credentials', WHERE: ['user_id' => $userId]) > 0) return;

        $data = ['passkey_enabled' => 0];
        if ((self::settingsFor($userId)['primary_method'] ?? null) === TwoFactorMethod::PASSKEY->value) {
            $data['primary_method'] = TwoFactorMethod::EMAIL->value;
        }

        self::upsertSettings($userId, $data);
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
        // One guarded UPDATE ("used_at IS NULL" in the WHERE) makes redemption atomic.
        // Two concurrent submissions of the same code can't both win; DB::update reports only rows it changed.
        return DB::update(
            UPDATE: 'two_factor_recovery_codes',
            SET: ['used_at' => date('Y-m-d H:i:s')],
            WHERE: [
                'user_id' => $userId,
                'code_hash' => hash('sha256', strtoupper(trim($code))),
                'used_at' => null,
            ]
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
     * Set the user's "also skip 2FA when I use remember me" preference.
     *
     * @param int  $userId
     * @param bool $enabled
     *
     * @return void
     */
    public static function setRememberDevice(int $userId, bool $enabled): void
    {
        DB::update(UPDATE: 'user_two_factor', SET: ['remember_device' => $enabled ? 1 : 0], WHERE: ['user_id' => $userId]);
    }

    /**
     * The user's active trusted-device rows, newest first, for display in security settings.
     * Each row is enriched with a best-effort 'type'/'os'/'browser' breakdown of its stored user agent.
     *
     * @param int $userId
     *
     * @return array<int, array<string, mixed>>
     */
    public static function trustedDevicesFor(int $userId): array
    {
        $devices = DB::select(
            SELECT: ['id', 'label', 'ip_address', 'created_at', 'last_used_at'],
            FROM: 'trusted_devices',
            WHERE: ['user_id' => $userId, 'expires' => ['>', date('Y-m-d H:i:s')]],
            ORDER_BY: 'created_at DESC'
        );

        foreach ($devices as &$device) {
            $device += UserAgentParser::describe($device['label']);
        }

        return $devices;
    }

    /**
     * Whether ticking "remember me" should also skip 2FA on this device for $user.
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
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'expires' => date('Y-m-d H:i:s', $expires),
        ]);

        setcookie(self::TRUSTED_COOKIE, "$selector:$validator", ['expires' => $expires] + AppController::secureCookieFlags());
    }

    /**
     * Whether the current request carries a valid, unexpired trusted-device cookie for $user.
     * Bumps the row's last_used_at on a match, mirroring the passkey verify path.
     * Rechecks the same config gates shouldRememberDevice() applies at issuance, so an already-issued
     * cookie stops bypassing 2FA once trusted devices are disabled globally or the user gains a force-2FA role.
     *
     * @param array $user User row carrying 'id' and, ideally, a 'role' name (resolved with a query when absent)
     *
     * @return bool
     */
    public static function deviceIsTrusted(array $user): bool
    {
        if (!TWO_FACTOR_CONFIG['trusted_device']['allow']) return false;

        $userId = (int)$user['id'];
        $role = $user['role'] ?? (AuthController::getUserWithRole($userId) ?? [])['role'] ?? null;
        if (self::roleInList($role, TWO_FACTOR_CONFIG['trusted_device']['force_off_for_roles'])) return false;

        $cookie = $_COOKIE[self::TRUSTED_COOKIE] ?? '';
        if (!str_contains($cookie, ':')) return false;

        [$selector, $validator] = explode(':', $cookie, 2);

        $row = DB::single(SELECT: ['validator_hash', 'expires'], FROM: 'trusted_devices', WHERE: [
            'user_id' => $userId,
            'selector' => $selector,
        ]);

        if (!$row || strtotime((string)$row['expires']) < time()) return false;
        if (!hash_equals($row['validator_hash'], hash('sha256', $validator))) return false;

        DB::update(UPDATE: 'trusted_devices', SET: ['last_used_at' => date('Y-m-d H:i:s')], WHERE: ['selector' => $selector]);

        return true;
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
     * Revoke a single trusted device, scoped to $userId so one user can't forget another's row.
     *
     * @param int $userId
     * @param int $deviceId
     *
     * @return void
     */
    public static function forgetDevice(int $userId, int $deviceId): void
    {
        DB::delete(FROM: 'trusted_devices', WHERE: ['id' => $deviceId, 'user_id' => $userId]);
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
