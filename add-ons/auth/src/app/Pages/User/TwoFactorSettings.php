<?php

declare(strict_types=1);

namespace app\Pages\User;

use app\Controllers\AuthController;
use app\Controllers\FormController;
use app\Controllers\PageController;
use app\Controllers\RequestController;
use app\Controllers\SessionController;
use app\Controllers\TwoFactorController;
use app\Enums\AlertType;
use app\Enums\ErrorCode;
use app\Enums\TwoFactorMethod;
use app\Models\Page;
use app\Utils\RateLimiter;
use JsonException;

/**
 * The /user/settings/two-factor section: enrol in, switch between and turn off the 2FA methods
 * (email codes, authenticator app, passkeys), plus recovery codes and trusted devices.
 */
class TwoFactorSettings
{
    public bool $twoFactorAvailable = false;
    public bool $twoFactorEnabled = false;
    public bool $twoFactorRequired = false;
    /** @var string[] method values ('email'/'totp'/'passkey') the account policy requires enrolled */
    public array $requiredMethods = [];
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
    public string $activeTab = 'email';
    public string $activeManageTab = 'recovery';
    /** @var string[] method values the user is enrolled in, e.g. ['email', 'totp'] */
    public array $enrolledMethods = [];
    private int $userId;

    public function __construct()
    {
        $this->userId = (int)SessionController::get('user')['id'];
        $this->initialize();
    }

    /**
     * Loads render state, abandons a stale unconfirmed authenticator secret once the user has
     * navigated away from its tab, then dispatches a submitted form.
     *
     * @return void
     */
    private function initialize(): void
    {
        $this->loadState();

        if ($this->totpPending && $this->activeTab !== 'totp' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            TwoFactorController::disableTotp($this->userId);
            $this->totpPending = false;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) $this->dispatch();
    }

    /**
     * Loads the 2FA state the view renders, consuming a one-shot recovery-code list left by enable/regenerate.
     *
     * @return void
     */
    private function loadState(): void
    {
        $user = SessionController::get('user');

        $this->twoFactorAvailable = TWO_FACTOR_CONFIG['enabled'];
        $this->requiredMethods = TwoFactorController::requiredMethodsFor($user);
        $this->twoFactorRequired = TwoFactorController::isRequiredFor($user) || $this->requiredMethods !== [];

        $settings = TwoFactorController::settingsFor($this->userId);
        $this->twoFactorEnabled = $settings !== null && !empty($settings['enabled']);
        $this->rememberDevice = $settings === null || !empty($settings['remember_device']);
        $this->totpEnabled = $settings !== null && !empty($settings['totp_enabled']);
        $this->passkeyEnabled = $settings !== null && !empty($settings['passkey_enabled']);
        $this->primaryMethod = $settings['primary_method'] ?? TwoFactorMethod::EMAIL->value;
        $this->passkeys = TwoFactorController::passkeysFor($this->userId);

        $this->activeTab = self::validTab('tab', [TwoFactorMethod::EMAIL->value, TwoFactorMethod::TOTP->value, TwoFactorMethod::PASSKEY->value], $this->primaryMethod);
        $this->activeManageTab = self::validTab('manageTab', ['recovery', 'devices'], 'recovery');

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

        // Consume the one-shot list only on the GET that follows enable/regenerate.
        // This method also runs after a redirecting POST, so skip it on the POST that set the list.
        $codes = $_SERVER['REQUEST_METHOD'] !== 'POST' ? SessionController::get('2fa_new_recovery_codes') : null;
        if (is_array($codes)) {
            $this->newRecoveryCodes = $codes;
            SessionController::remove('2fa_new_recovery_codes');
        }
    }

    /**
     * Reads a tab-selector query param, falling back to $default when it's missing or names an
     * unrecognised tab. The page's two tab strips each own their own param so that switching one
     * never clobbers the other's remembered tab.
     *
     * @param string   $param   GET key naming the tab
     * @param string[] $allowed Recognised values for this param
     * @param string   $default Fallback when the param is missing or unrecognised
     *
     * @return string
     */
    private static function validTab(string $param, array $allowed, string $default): string
    {
        $value = $_GET[$param] ?? '';
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Routes the submitted form to its handler by the hidden "action" field.
     *
     * @return void
     */
    private function dispatch(): void
    {
        match ($_POST['action'] ?? '') {
            'enable-2fa' => $this->enableTwoFactor(),
            'disable-2fa' => $this->disableTwoFactor(),
            'recovery-codes' => $this->regenerateRecoveryCodes(),
            'trusted-devices' => $this->revokeTrustedDevices(),
            'forget-device' => $this->forgetDevice(),
            'remember-device' => $this->saveRememberDevicePreference(),
            'totp-setup' => $this->beginTotpSetup(),
            'totp-confirm' => $this->confirmTotpSetup(),
            'totp-remove' => $this->removeTotp(),
            'passkey-remove' => $this->removePasskey(),
            'set-primary' => $this->setPrimaryMethod(),
            default => PageController::redirect('user/settings/two-factor'),
        };
    }

    /**
     * Turns 2FA on (email method) and stashes the fresh recovery codes for a one-time display.
     *
     * @return void
     */
    private function enableTwoFactor(): void
    {
        if (!TWO_FACTOR_CONFIG['enabled'] || $this->twoFactorEnabled) {
            PageController::redirect('user/settings/two-factor');
            return;
        }

        SessionController::set('2fa_new_recovery_codes', TwoFactorController::enable($this->userId));
        PageController::redirectWithAlert('user/settings/two-factor?manageTab=recovery', 'Two-factor authentication is on. Save your recovery codes now - they are shown only once.', AlertType::SUCCESS, 8);
    }

    /**
     * Turns 2FA off after re-confirming the account password. Blocked for roles that mandate 2FA.
     *
     * @return void
     */
    private function disableTwoFactor(): void
    {
        if (!$this->requireEnabled()) return;

        if ($this->twoFactorRequired) {
            PageController::redirectWithAlert('user/settings/two-factor', 'Your role requires two-factor authentication; it cannot be turned off.', AlertType::WARNING, 5);
            return;
        }

        if (!$this->confirmCurrentPassword("2fa-disable-{$this->userId}", 'user/settings/two-factor?tab=email')) return;

        TwoFactorController::disableAll($this->userId);
        PageController::redirectWithAlert('user/settings/two-factor', 'Two-factor authentication has been turned off.', AlertType::SUCCESS, 4);
    }

    /**
     * Redirects to the page and returns false when 2FA isn't on, since none of the per-method
     * actions gated behind this make sense otherwise.
     *
     * @return bool
     */
    private function requireEnabled(): bool
    {
        if ($this->twoFactorEnabled) return true;

        PageController::redirect('user/settings/two-factor');
        return false;
    }

    /**
     * Re-confirms the account password before a change that weakens the account outright.
     * Turning 2FA off entirely and replacing recovery codes gate on this; removing a single
     * factor (authenticator, one passkey) does not, since email always remains enrolled.
     * Redirects with a global alert and returns false on a missing, rate-limited or wrong password,
     * since the caller's action never completes on this request either way.
     *
     * @param string $rateLimitKey   Per-action lockout key
     * @param string $redirectTarget Page (and tab) to send the user back to on failure
     *
     * @return bool
     */
    private function confirmCurrentPassword(string $rateLimitKey, string $redirectTarget): bool
    {
        $password = RequestController::rawPost('current-password');

        if ($password === null || $password === '' || mb_strlen($password) > MAX_PASSWORD_LENGTH) {
            PageController::redirectWithAlert($redirectTarget, 'Please enter your current password!', AlertType::WARNING);
            return false;
        }

        if (AuthController::checkPassword(SessionController::get('user')['email'], $password)) return true;

        // Only a wrong password counts against the lockout, matching PasswordSettings::changePassword().
        if (!RateLimiter::attempt($rateLimitKey, LOCKOUT_CONFIG['change_password']['max_attempts'], LOCKOUT_CONFIG['change_password']['window_seconds'])) {
            PageController::redirectWithAlert($redirectTarget, 'Too many incorrect attempts. Please wait a while before trying again.', AlertType::ERROR);
            return false;
        }

        PageController::redirectWithAlert($redirectTarget, 'Your current password is incorrect!', AlertType::WARNING);
        return false;
    }

    /**
     * Replaces the recovery codes and stashes the new set for a one-time display.
     *
     * @return void
     */
    private function regenerateRecoveryCodes(): void
    {
        if (!$this->requireEnabled()) return;

        if (!$this->confirmCurrentPassword("2fa-recovery-{$this->userId}", 'user/settings/two-factor?manageTab=recovery')) return;

        SessionController::set('2fa_new_recovery_codes', TwoFactorController::regenerateRecoveryCodes($this->userId));
        PageController::redirectWithAlert('user/settings/two-factor?manageTab=recovery', 'New recovery codes generated. Your old codes no longer work.', AlertType::SUCCESS, 8);
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
        PageController::redirectWithAlert('user/settings/two-factor?manageTab=devices', 'All remembered devices will be asked for two-factor again.', AlertType::SUCCESS, 4);
    }

    /**
     * Revokes a single remembered device.
     *
     * @return void
     */
    private function forgetDevice(): void
    {
        $id = (int)($_POST['device_id'] ?? 0);
        if ($id > 0) TwoFactorController::forgetDevice($this->userId, $id);

        PageController::redirectWithAlert('user/settings/two-factor?manageTab=devices', 'Device forgotten. It will be asked for two-factor again.', AlertType::SUCCESS, 4);
    }

    /**
     * Saves the "also skip 2FA when I use remember me" checkbox.
     *
     * @return void
     */
    private function saveRememberDevicePreference(): void
    {
        if ($this->twoFactorEnabled) TwoFactorController::setRememberDevice($this->userId, isset($_POST['remember_device']));

        PageController::redirectWithAlert('user/settings/two-factor?manageTab=devices', 'Preference saved.', AlertType::SUCCESS, 3);
    }

    /**
     * Stages a new authenticator-app secret and returns to the page, which then shows the QR code.
     * Email must be turned on first; it is the account's mandatory fallback method.
     *
     * @return void
     */
    private function beginTotpSetup(): void
    {
        if (TWO_FACTOR_CONFIG['enabled'] && $this->twoFactorEnabled && !$this->totpEnabled) TwoFactorController::beginTotpEnrolment($this->userId);

        PageController::redirect('user/settings/two-factor?tab=totp');
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
            // confirmTotp() has already issued and stashed the recovery codes for the one-time display.
            PageController::redirectWithAlert('user/settings/two-factor?manageTab=recovery', 'Authenticator app enabled. Save your recovery codes now - they are shown only once.', AlertType::SUCCESS, 8);
            return;
        }

        PageController::redirectWithAlert('user/settings/two-factor?tab=totp', 'Authenticator app enabled.', AlertType::SUCCESS, 4);
    }

    /**
     * Removes the authenticator-app method. Blocked when the account's policy requires it.
     *
     * @return void
     */
    private function removeTotp(): void
    {
        if ($this->totpEnabled && in_array(TwoFactorMethod::TOTP->value, $this->requiredMethods, true)) {
            PageController::redirectWithAlert('user/settings/two-factor?tab=totp', 'Your account requires the authenticator app; it cannot be removed.', AlertType::WARNING, 5);
            return;
        }

        $message = $this->totpEnabled ? 'Authenticator app removed.' : 'Authenticator setup cancelled.';
        TwoFactorController::disableTotp($this->userId);
        PageController::redirectWithAlert('user/settings/two-factor?tab=totp', $message, AlertType::SUCCESS, 4);
    }

    /**
     * Removes one of the user's passkeys. Blocked when it's the last one and the account's policy requires it.
     *
     * @return void
     */
    private function removePasskey(): void
    {
        if (count($this->passkeys) <= 1 && in_array(TwoFactorMethod::PASSKEY->value, $this->requiredMethods, true)) {
            PageController::redirectWithAlert('user/settings/two-factor?tab=passkey', 'Your account requires a passkey; you must keep at least one.', AlertType::WARNING, 5);
            return;
        }

        $id = (int)($_POST['passkey_id'] ?? 0);
        if ($id > 0) TwoFactorController::deletePasskey($this->userId, $id);

        PageController::redirectWithAlert('user/settings/two-factor?tab=passkey', 'Passkey removed.', AlertType::SUCCESS, 4);
    }

    /**
     * Sets which enrolled method the login challenge offers first.
     *
     * @return void
     */
    private function setPrimaryMethod(): void
    {
        $method = TwoFactorMethod::tryFrom($_POST['primary_method'] ?? '');
        $tab = $this->activeTab;

        if ($method !== null && in_array($method->value, $this->enrolledMethods, true)) {
            TwoFactorController::setPrimaryMethod($this->userId, $method);
            $tab = $method->value;
        }

        PageController::redirect('user/settings/two-factor?tab=' . $tab);
    }

    /**
     * JSON passkey endpoints under /api/user/settings/two-factor/passkey/*. Auth was already enforced by Settings::dispatch().
     *
     * @param Page $page
     *
     * @return void
     *
     * @throws JsonException
     */
    final public function api(Page $page): void
    {
        if (!TWO_FACTOR_CONFIG['enabled'] || $page->subpage(2) !== 'passkey') {
            PageController::error(ErrorCode::NOT_FOUND);
            return;
        }

        // Reachable directly by a client, unlike beginTotpSetup(), so it needs its own email-first guard.
        if (!$this->twoFactorEnabled) {
            PageController::error(ErrorCode::BAD_REQUEST);
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

        self::json(['ok' => true]);
    }
}
