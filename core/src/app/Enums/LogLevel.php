<?php

declare(strict_types=1);

namespace app\Enums;

/**
 * Represents the severity levels of logging.
 */
enum LogLevel: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';
    case DEBUG = 'debug';
}
