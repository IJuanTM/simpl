<?php

declare(strict_types=1);

namespace app\Enums;

/**
 * A second factor a user can enrol in. The primary method is the one offered first at the login challenge.
 */
enum TwoFactorMethod: string
{
    case EMAIL = 'email';
    case TOTP = 'totp';
    case PASSKEY = 'passkey';

    /**
     * Display label used wherever methods are listed for a person to pick from (settings tabs, admin multi-selects).
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::TOTP => 'Authenticator app',
            self::PASSKEY => 'Passkeys',
        };
    }
}
