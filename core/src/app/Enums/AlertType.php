<?php

declare(strict_types=1);

namespace app\Enums;

/**
 * Represents the types of alerts that can be used to categorize a message or notification.
 */
enum AlertType: string
{
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
    case INFO = 'info';
}
