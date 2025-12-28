<?php
// Set the base directory
define('BASEDIR', realpath(dirname(__DIR__, 2)));

// Require the composer autoloader
require_once BASEDIR . '/vendor/autoload.php';

// Load the .env file
Dotenv\Dotenv::createImmutable(BASEDIR)->safeLoad();

// Require the config files
foreach (glob(BASEDIR . '/app/Config/*.php') as $file) require_once $file;

// Validate the timezone constant
if (!in_array(TIMEZONE, DateTimeZone::listIdentifiers(), true)) throw new InvalidArgumentException('Invalid timezone constant: ' . TIMEZONE);

// Set the timezone to be used
date_default_timezone_set(TIMEZONE);

// Enable PHP error logging
ini_set('log_errors', 1);

// Set error reporting based on the environment
if (DEV) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
