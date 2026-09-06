<?php

declare(strict_types=1);

namespace app\Pages\User;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\SessionController;
use app\Controllers\TwoFactorController;
use app\Enums\AlertType;
use app\Enums\ErrorCode;
use app\Enums\TwoFactorMethod;
use app\Models\Page;
use app\Utils\RateLimiter;
use JsonException;

/**
 * The /user/settings/security section: password change and two-factor authentication management.
 */
class SecuritySettings
{
    public bool $twoFactorAvailable = false;
    public bool $twoFactorEnabled = false;
    public bool $twoFactorRequired = false;
    public bool $rememberDevice = true;
    public int $recoveryCodesLeft = 0;
    public array $newRecoveryCodes = [];
    public array $trustedDevices = [];
    public bool $totpEnabled = false;
    public bool $totpPending = false;
    public ?string $totpSecret = null;
    public ?string $totpUri = null;
    public ?string $totpQrSvg = null;
    public bool $passkeyEnabled = false;
    public array $passkeys = [];
    public string $primaryMethod = 'email';
    /** @var string[] method values the user is enrolled in, e.g. ['email', 'totp'] */
    public array $enrolledMethods = [];
    private int $userId;

    public function __construct()
    {
        $this->userId = (int)SessionController::get('user')['id'];

        // State first, so the POST handlers can read the current 2FA settings and the view
        // renders correct values after a non-redirecting handler (a validation failure).
        $this->loadState();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->dispatch();
    }

    /**
     * Loads the 2FA state the view renders, consuming a one-shot recovery-code list left by enable/regenerate.
     *
     * @return void
     */
    private function loadState(): void
    {
        $this->twoFactorAvailable = TWO_FACTOR_CONFIG['enabled'];
        $this->twoFactorRequired = TwoFactorController::isRequiredFor(SessionController::get('user'));

        $settings = TwoFactorController::settingsFor($this->userId);
        $this->twoFactorEnabled = $settings !== null && !empty($settings['enabled']);
        $this->rememberDevice = $settings === null || !empty($settings['remember_device']);
        $this->totpEnabled = $settings !== null && !empty($settings['totp_enabled']);
        $this->passkeyEnabled = $settings !== null && !empty($settings['passkey_enabled']);
        $this->primaryMethod = $settings['primary_method'] ?? TwoFactorMethod::EMAIL->value;
        $this->passkeys = TwoFactorController::passkeysFor($this->userId);

        if ($this->twoFactorEnabled) {
            $this->recoveryCodesLeft = TwoFactorController::recoveryCodesRemaining($this->userId);
            $this->trustedDevices = TwoFactorController::trustedDevicesFor($this->userId);
            $this->enrolledMethods = array_map(static fn(TwoFactorMethod $m) => $m->value, TwoFactorController::enabledMethods($this->userId));
        }

        if ($settings !== null && !empty($settings['totp_secret']) && empty($settings['totp_enabled'])) {
            $setup = TwoFactorController::totpSetupData($this->userId, SessionController::get('user')['email']);
            if ($setup !== null) {
                $this->totpPending = true;
                $this->totpSecret = $setup['secret'];
                $this->totpUri = $setup['uri'];
                $this->totpQrSvg = $setup['svg'];
            }
        }

        // Consume the one-shot list only on the GET that follows enable/regenerate, not on the
        // POST request that set it (this method also runs after a redirecting action).
        $codes = $_SERVER['REQUEST_METHOD'] !== 'POST' ? SessionController::get('2fa_new_recovery_codes') : null;
        if (is_array($codes)) {
            $this->newRecoveryCodes = $codes;
            SessionController::remove('2fa_new_recovery_codes');
        }
    }

    /**
     * Routes the submitted form to its handler by the hidden "action" field.
     *
     * @return void
     */
    private function dispatch(): void
    {
        match ($_POST['action'] ?? 'change-password') {
            'enable-2fa' => $this->enableTwoFactor(),
            'disable-2fa' => $this->disableTwoFactor(),
            'recovery-codes' => $this->regenerateRecoveryCodes(),
            'trusted-devices' => $this->revokeTrustedDevices(),
            'remember-device' => $this->saveRememberDevicePreference(),
            'totp-setup' => $this->beginTotpSetup(),
            'totp-confirm' => $this->confirmTotpSetup(),
            'totp-remove' => $this->removeTotp(),
            'passkey-remove' => $this->removePasskey(),
            'set-primary' => $this->setPrimaryMethod(),
            default => $this->changePassword(),
        };
    }

    /**
     * Turns 2FA on (email method) and stashes the fresh recovery codes for a one-time display.
     *
     * @return void
     */
    private function enableTwoFactor(): void
    {
        if (!TWO_FACTOR_CONFIG['enabled'] || TwoFactorController::isEnabledFor($this->userId)) {
            PageController::redirect('user/settings/security');
            return;
        }

        SessionController::set('2fa_new_recovery_codes', TwoFactorController::enable($this->userId));
        PageController::redirectWithAlert('user/settings/security', 'Two-factor authentication is on. Save your recovery codes now - they are shown only once.', AlertType::SUCCESS, 8);
    }

    /**
     * Turns 2FA off after re-confirming the account password. Blocked for roles that mandate 2FA.
     *
     * @return void
     */
    private function disableTwoFactor(): void
    {
        if (!TwoFactorController::isEnabledFor($this->userId)) {
            PageController::redirect('user/settings/security');
            return;
        }

        if ($this->twoFactorRequired) {
            PageController::redirectWithAlert('user/settings/security', 'Your role requires two-factor authentication; it cannot be turned off.', AlertType::WARNING, 5);
            return;
        }

        if (!FormController::validate('current-password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH])) return;

        $user = SessionController::get('user');

        if (!RateLimiter::attempt("2fa-disable-{$this->userId}", LOCKOUT_CONFIG['change_password']['max_attempts'], LOCKOUT_CONFIG['change_password']['window_seconds'])) {
            FormController::addAlert('Too many incorrect attempts. Please wait a while before trying again.', AlertType::ERROR);
            return;
        }

        if (!AuthController::checkPassword($user['email'], $_POST['current-password'])) {
            $_POST['current-password'] = '';
            FormController::addAlert('Your current password is incorrect!', AlertType::WARNING);
            return;
        }

        TwoFactorController::disableAll($this->userId);
        PageController::redirectWithAlert('user/settings/security', 'Two-factor authentication has been turned off.', AlertType::SUCCESS, 4);
    }

    /**
     * Replaces the recovery codes and stashes the new set for a one-time display.
     *
     * @return void
     */
    private function regenerateRecoveryCodes(): void
    {
        if (!TwoFactorController::isEnabledFor($this->userId)) {
            PageController::redirect('user/settings/security');
            return;
        }

        SessionController::set('2fa_new_recovery_codes', TwoFactorController::regenerateRecoveryCodes($this->userId));
        PageController::redirectWithAlert('user/settings/security', 'New recovery codes generated. Your old codes no longer work.', AlertType::SUCCESS, 8);
    }

    /**
     * Revokes every remembered device, including the current one.
     *
     * @return void
     */
    private function revokeTrustedDevices(): void
    {
        TwoFactorController::forgetAllDevices($this->userId);
        TwoFactorController::forgetThisDevice();
        PageController::redirectWithAlert('user/settings/security', 'All remembered devices will be asked for two-factor again.', AlertType::SUCCESS, 4);
    }

    /**
     * Saves the "also skip 2FA when I use remember me" checkbox.
     *
     * @return void
     */
    private function saveRememberDevicePreference(): void
    {
        if (TwoFactorController::isEnabledFor($this->userId)) TwoFactorController::setRememberDevice($this->userId, isset($_POST['remember_device']));

        PageController::redirectWithAlert('user/settings/security', 'Preference saved.', AlertType::SUCCESS, 3);
    }

    /**
     * Stages a new authenticator-app secret and returns to the page, which then shows the QR code.
     *
     * @return void
     */
    private function beginTotpSetup(): void
    {
        if (TWO_FACTOR_CONFIG['enabled'] && !$this->totpEnabled) TwoFactorController::beginTotpEnrolment($this->userId);

        PageController::redirect('user/settings/security');
    }

    /**
     * Confirms the staged authenticator secret with a code from the app, issuing recovery codes on first enable.
     *
     * @return void
     */
    private function confirmTotpSetup(): void
    {
        if (!FormController::validate('totp-code', ['required', 'maxLength' => 10])) return;

        if (!RateLimiter::attempt("2fa-totp-setup-{$this->userId}", TWO_FACTOR_CONFIG['challenge_max_attempts'], TWO_FACTOR_CONFIG['challenge_attempt_window'])) {
            FormController::addAlert('Too many attempts. Please wait a while before trying again.', AlertType::ERROR);
            return;
        }

        $wasEnabled = $this->twoFactorEnabled;

        if (!TwoFactorController::confirmTotp($this->userId, $_POST['totp-code'])) {
            $_POST['totp-code'] = '';
            FormController::addAlert('That code is incorrect. Check your authenticator app and try again.', AlertType::WARNING);
            return;
        }

        if (!$wasEnabled) {
            SessionController::set('2fa_new_recovery_codes', TwoFactorController::regenerateRecoveryCodes($this->userId));
            PageController::redirectWithAlert('user/settings/security', 'Authenticator app enabled. Save your recovery codes now - they are shown only once.', AlertType::SUCCESS, 8);
            return;
        }

        PageController::redirectWithAlert('user/settings/security', 'Authenticator app enabled.', AlertType::SUCCESS, 4);
    }

    /**
     * Removes the authenticator-app method, leaving email as the fallback.
     *
     * @return void
     */
    private function removeTotp(): void
    {
        TwoFactorController::disableTotp($this->userId);
        PageController::redirectWithAlert('user/settings/security', 'Authenticator app removed.', AlertType::SUCCESS, 4);
    }

    /**
     * Removes one of the user's passkeys.
     *
     * @return void
     */
    private function removePasskey(): void
    {
        $id = (int)($_POST['passkey_id'] ?? 0);
        if ($id > 0) TwoFactorController::deletePasskey($this->userId, $id);

        PageController::redirectWithAlert('user/settings/security', 'Passkey removed.', AlertType::SUCCESS, 4);
    }

    /**
     * Sets which enrolled method the login challenge offers first.
     *
     * @return void
     */
    private function setPrimaryMethod(): void
    {
        $method = TwoFactorMethod::tryFrom($_POST['primary_method'] ?? '');

        if ($method !== null && in_array($method->value, $this->enrolledMethods, true)) {
            TwoFactorController::setPrimaryMethod($this->userId, $method);
        }

        PageController::redirect('user/settings/security');
    }

    /**
     * Processes the change-password form submission.
     *
     * @return void
     */
    private function changePassword(): void
    {
        if (
            !FormController::validate('old-password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH]) ||
            !FormController::validate('new-password', ['required', 'maxLength' => MAX_PASSWORD_LENGTH]) ||
            !FormController::validate('new-password-check', ['required', 'maxLength' => MAX_PASSWORD_LENGTH])
        ) return;

        $user = SessionController::get('user');

        if (!AuthController::checkPassword($user['email'], $_POST['old-password'])) {
            // Only wrong-old-password attempts count against the lockout, so a correct password
            // never burns a slot on an unrelated validation failure elsewhere in the form.
            if (!RateLimiter::attempt("change-password-{$user['id']}", LOCKOUT_CONFIG['change_password']['max_attempts'], LOCKOUT_CONFIG['change_password']['window_seconds'])) {
                FormController::addAlert('Too many incorrect attempts. Please wait a while before trying again.', AlertType::ERROR);
                return;
            }

            $_POST['old-password'] = '';
            FormController::addAlert('The old password is incorrect!', AlertType::WARNING);
            return;
        }

        if ($_POST['old-password'] === $_POST['new-password']) {
            FormController::addAlert('The new password is the same as the old password!', AlertType::WARNING);
            return;
        }

        if (!FormController::validatePasswords('new-password', 'new-password-check')) return;

        // Copied into this session's own cached user data too.
        // Otherwise requireAuth() would treat this very session as stale on its very next request.
        $user['password_changed_at'] = AuthController::updatePassword($user['id'], $_POST['new-password']);

        $user['must_change_password'] = 0;
        SessionController::set('user', $user);

        AuthController::intendedRedirect('profile', 'Success! Your password has been changed!', AlertType::SUCCESS, 4);
    }

    /**
     * JSON passkey endpoints under /api/user/settings/security/passkey/*. Auth was already enforced by Settings::dispatch().
     *
     * @param Page $page
     *
     * @return void
     *
     * @throws JsonException
     */
    final public function api(Page $page): void
    {
        if ($page->subpage(2) !== 'passkey' || !TWO_FACTOR_CONFIG['enabled']) {
            PageController::error(ErrorCode::NOT_FOUND);
            return;
        }

        match ($page->subpage(3)) {
            'options' => $this->passkeyOptions(),
            'register' => $this->passkeyRegister(),
            default => PageController::error(ErrorCode::NOT_FOUND),
        };
    }

    /**
     * Returns the navigator.credentials.create() options for a new passkey.
     *
     * @return void
     *
     * @throws JsonException
     */
    private function passkeyOptions(): void
    {
        $options = json_decode(TwoFactorController::passkeyRegistrationOptions($this->userId, SessionController::get('user')['email']), true, flags: JSON_THROW_ON_ERROR);

        self::json(['publicKey' => $options]);
    }

    /**
     * Emits $data as a JSON response body.
     *
     * @param array<string, mixed> $data
     *
     * @return void
     *
     * @throws JsonException
     */
    private static function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * Validates and stores a passkey registration response.
     *
     * @return void
     *
     * @throws JsonException
     */
    private function passkeyRegister(): void
    {
        $payload = json_decode((string)file_get_contents('php://input'), true);

        if (!is_array($payload) || !is_array($payload['credential'] ?? null)) {
            PageController::error(ErrorCode::BAD_REQUEST);
            return;
        }

        $name = is_string($payload['name'] ?? null) ? $payload['name'] : 'Passkey';

        if (!TwoFactorController::savePasskey($this->userId, json_encode($payload['credential'], JSON_THROW_ON_ERROR), $name)) {
            PageController::error(ErrorCode::BAD_REQUEST);
            return;
        }

        // The first passkey enables 2FA; stash recovery codes for the one-time display after the JS reloads the page.
        if (!$this->twoFactorEnabled) SessionController::set('2fa_new_recovery_codes', TwoFactorController::regenerateRecoveryCodes($this->userId));

        self::json(['ok' => true]);
    }
}
