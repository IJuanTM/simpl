<?php

declare(strict_types=1);

define('DEV', $_ENV['DEV']);
define('APP_NAME', $_ENV['APP_NAME']);
define('APP_URL', $_ENV['APP_URL']);

// See https://www.php.net/manual/en/timezones.php
const TIMEZONE = 'UTC';

const SESSION_LIFETIME = 3;      // days
const REDIRECT = 'home';         // page name
const ERROR_AUTO_REDIRECT = true;

// ---------------------------------------------------------------- //

// Framework version info (set in .env)
define('SIMPL_VERSION', $_ENV['SIMPL_VERSION']);
define('SIMPL_LAST_UPDATE', $_ENV['SIMPL_LAST_UPDATE']);
