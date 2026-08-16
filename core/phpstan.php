<?php

declare(strict_types=1);

// PHPStan analyzes files without executing them, so these runtime-only constants
// (normally define()'d in start.php/app.php) need to exist here too.
define('BASEDIR', __DIR__);
define('DEV', true); // matches .env's template default
