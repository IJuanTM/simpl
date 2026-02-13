<?php

declare(strict_types=1);

namespace app\Enums;

/**
 * LogLevel enum
 *
 * Semantic log levels used by the application's logging utility.
 */
enum LogLevel: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';
    case DEBUG = 'debug';
}
