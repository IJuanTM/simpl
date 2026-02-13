<?php

declare(strict_types=1);

namespace app\Enums;

/**
 * AlertType enum
 *
 * Represents CSS classes / semantic types for user-facing alert messages.
 */
enum AlertType: string
{
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
    case INFO = 'info';
}
