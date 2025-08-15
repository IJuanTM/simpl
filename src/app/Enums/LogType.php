<?php

namespace app\Enums;

/**
 * The LogType enum is used for logging different types of messages. Each type creates a new log file with the type as the filename in the Logs directory.
 */
enum LogType: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
    case DEBUG = 'debug';

    case SESSION = 'session';
    case DATABASE = 'database';
    case MAIL = 'mail';
}
