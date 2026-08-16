<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/app/Scripts/start.php';

$_SERVER['HTTP_HOST'] ??= 'localhost';
$_SERVER['SCRIPT_NAME'] ??= '/index.php';
$_SERVER['REQUEST_URI'] ??= '/';
$_SESSION ??= [];
