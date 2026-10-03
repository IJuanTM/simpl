<?php

declare(strict_types=1);

// Cast to a real boolean; env values arrive as strings, so "false"/"0" would otherwise be truthy.
define('DEV', filter_var($_ENV['DEV'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('APP_NAME', $_ENV['APP_NAME']);
define('APP_URL', $_ENV['APP_URL']);
define('LOG_DIR', $_ENV['LOG_DIR'] ?? BASEDIR . '/logs');

// See https://www.php.net/manual/en/timezones.php
const TIMEZONE = 'UTC';

const SESSION_LIFETIME = 3;      // days
const REDIRECT = 'home';         // page name
const ERROR_AUTO_REDIRECT = true;

const HISTORY_DEPTH = 5; // how many prior pages back()/prev() can navigate
const UI_BUTTON_COOLDOWN = 300; // milliseconds; shared data-cooldown value for repeat-click-guarded buttons

// Theme name => Font Awesome icon for the header's theme menu; each name needs a matching theme in scss/config/vars/_themes.scss.
// The "System" option follows the device's light/dark setting by picking the themes named light and dark.
const THEMES = ['light' => 'sun', 'dark' => 'moon'];

// ---------------------------------------------------------------- //

define('SIMPL_VERSION', $_ENV['SIMPL_VERSION']);
define('SIMPL_LAST_UPDATE', $_ENV['SIMPL_LAST_UPDATE']);
