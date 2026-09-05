<?php

declare(strict_types=1);

namespace app\Enums;

/**
 * Purpose of a row in the tokens table: email verification, remember-me auto-login, or password reset.
 */
enum TokenType: string
{
    case VERIFICATION = 'verification';
    case REMEMBER = 'remember';
    case RESET = 'reset';
}
