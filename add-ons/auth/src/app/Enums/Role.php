<?php

declare(strict_types=1);

namespace app\Enums;

/**
 * User roles, assigned via the user_roles pivot table.
 */
enum Role: string
{
    case ADMIN = 'Admin';
    case USER = 'User';
}
