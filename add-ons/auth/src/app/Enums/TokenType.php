<?php

declare(strict_types=1);

namespace app\Enums;

/**
 * Purpose of a row in the tokens table: email verification, remember-me auto-login, password reset, or a login-time two-factor email code.
 */
enum TokenType: string
{
    case VERIFICATION = 'verification';
    case REMEMBER = 'remember';
    case RESET = 'reset';
    case TWO_FACTOR_EMAIL = 'two_factor_email';
}
