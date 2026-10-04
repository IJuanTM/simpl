<?php

declare(strict_types=1);

use app\Database\DatabaseMigrator;
use app\Database\Migrations\Tables\CreateSchedulerRunsTable;

/* ---------------------------------------------------------------- */

DatabaseMigrator::register(CreateSchedulerRunsTable::class);

// @addon-placeholder
