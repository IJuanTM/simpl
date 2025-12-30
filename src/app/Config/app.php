<?php
// Environment configuration
define('DEV', $_ENV['DEV']);

// Timezone configuration
const TIMEZONE = 'UTC'; // Change to your desired timezone, see https://www.php.net/manual/en/timezones.php

// App configuration
define('APP_NAME', $_ENV['APP_NAME']);
define('APP_URL', $_ENV['APP_URL']);

// Session configuration
const SESSION_LIFETIME = 3; // in days

// Redirect configuration
const REDIRECT = 'home';
const ERROR_AUTO_REDIRECT = true;

// Simpl variables
define('SIMPL_VERSION', $_ENV['SIMPL_VERSION']);
define('SIMPL_LAST_UPDATE', $_ENV['SIMPL_LAST_UPDATE']);
