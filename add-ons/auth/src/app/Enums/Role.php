<?php

declare(strict_types=1);

namespace app\Enums;

enum Role: string
{
    case ADMIN = 'Admin';
    case USER = 'User';
}
