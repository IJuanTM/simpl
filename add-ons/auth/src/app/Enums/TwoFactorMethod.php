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
}
