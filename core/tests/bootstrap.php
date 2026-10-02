<?php

declare(strict_types=1);

// Keeps deliberately-triggered error/warning-path tests out of the real dev logs (src/logs).
$_ENV['LOG_DIR'] = sys_get_temp_dir() . '/simpl-test-logs';

require_once __DIR__ . '/../src/app/Scripts/start.php';

$_SERVER['HTTP_HOST'] ??= 'localhost';
$_SERVER['SCRIPT_NAME'] ??= '/index.php';
$_SERVER['REQUEST_URI'] ??= '/';
$_SESSION ??= [];
