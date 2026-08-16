<?php

declare(strict_types=1);

namespace app\Enums;

enum TokenType: string
{
    case VERIFICATION = 'verification';
    case REMEMBER = 'remember';
    case RESET = 'reset';
}
