<?php

declare(strict_types=1);

use app\Utils\Console;
use app\Utils\Scheduler;

/* ---------------------------------------------------------------- */

require_once 'start.php';

/* ---------------------------------------------------------------- */

try {
    Scheduler::run(in_array('--test', $_SERVER['argv'] ?? [], true));
} catch (Exception $e) {
    Console::line();
    Console::error("Scheduler failed: " . $e->getMessage());
    Console::line();
    exit(1);
}
