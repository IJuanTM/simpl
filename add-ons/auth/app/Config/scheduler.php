<?php

declare(strict_types=1);

use app\Cron\DeactivateUnverifiedUsers;
use app\Cron\DeleteInactiveUsers;
use app\Utils\Scheduler;

/* ---------------------------------------------------------------- */

// Every day, deactivate users who haven't verified their email within 24 hours
Scheduler::task('deactivate-unverified-users', static fn() => DeactivateUnverifiedUsers::run())
    ->daily();

// Every week, delete users who haven't verified their email within 7 days'
Scheduler::task('delete-inactive-users', static fn() => DeleteInactiveUsers::run())
    ->weekly();
