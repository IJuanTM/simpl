<?php

declare(strict_types=1);

use app\Utils\Scheduler;

/* ---------------------------------------------------------------- */

require_once 'start.php';

/* ---------------------------------------------------------------- */

Scheduler::run(in_array('--test', $_SERVER['argv'] ?? [], true));
