<?php

declare(strict_types=1);

use app\Database\DatabaseMigrator;
use app\Database\Migrations\Tables\CreateSchedulerRunsTable;

/* ---------------------------------------------------------------- */

// Bookkeeping table for the Scheduler mechanism itself, not domain data - registered here
// rather than by whichever add-on happens to use the scheduler.
DatabaseMigrator::register(CreateSchedulerRunsTable::class);

// @addon-placeholder
