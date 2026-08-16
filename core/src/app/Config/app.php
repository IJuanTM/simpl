<?php

declare(strict_types=1);

// Cast to a real boolean; env values arrive as strings, so "false"/"0" would otherwise be truthy.
define('DEV', filter_var($_ENV['DEV'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('APP_NAME', $_ENV['APP_NAME']);
define('APP_URL', $_ENV['APP_URL']);

// See https://www.php.net/manual/en/timezones.php
const TIMEZONE = 'UTC';

const SESSION_LIFETIME = 3;      // days
const REDIRECT = 'home';         // page name
const ERROR_AUTO_REDIRECT = true;

const RATE_LIMIT_CACHE_RETENTION = 86400; // seconds; how long stale rate-limit files are kept before pruning
const HISTORY_DEPTH = 5; // how many prior pages back()/prev() can navigate

// ---------------------------------------------------------------- //

define('SIMPL_VERSION', $_ENV['SIMPL_VERSION']);
define('SIMPL_LAST_UPDATE', $_ENV['SIMPL_LAST_UPDATE']);
