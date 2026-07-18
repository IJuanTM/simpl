<?php

declare(strict_types=1);

namespace app\Enums;

/**
 * Lifecycle state of a user account.
 *
 * - ACTIVE:      normal, usable account.
 * - DEACTIVATED: automatically disabled because the email was never verified in time; pending deletion.
 * - DELETED:     soft-deleted by an admin (trash); restorable or purgeable from the admin panel.
 */
enum UserStatus: string
{
    case ACTIVE = 'active';
    case DEACTIVATED = 'deactivated';
    case DELETED = 'deleted';
}
