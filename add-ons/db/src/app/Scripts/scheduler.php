<?php

declare(strict_types=1);

use app\Utils\Console;
use app\Utils\Scheduler;

/* ---------------------------------------------------------------- */

require_once 'start.php';

/* ---------------------------------------------------------------- */

try {
    if (Scheduler::run(in_array('--test', $_SERVER['argv'] ?? [], true)) > 0) exit(1);
} catch (Exception $e) {
    Console::fail("Scheduler failed: " . $e->getMessage());
}
