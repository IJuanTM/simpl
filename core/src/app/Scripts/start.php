<?php

declare(strict_types=1);

define('BASEDIR', realpath(dirname(__DIR__, 2)));

require_once BASEDIR . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(BASEDIR)->safeLoad();

foreach (glob(BASEDIR . '/app/Config/*.php') as $file) require_once $file;

if (!in_array(TIMEZONE, DateTimeZone::listIdentifiers(), true)) throw new InvalidArgumentException('Invalid timezone constant: ' . TIMEZONE);

date_default_timezone_set(TIMEZONE);

ini_set('log_errors', 1);

if (DEV) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
