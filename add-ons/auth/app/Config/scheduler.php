<?php

declare(strict_types=1);

use app\Cron\DeactivateUnverifiedUsers;
use app\Cron\DeleteDeactivatedUsers;
use app\Utils\Scheduler;

/* ---------------------------------------------------------------- */

// Every day, deactivate users who haven't verified their email within the grace period
Scheduler::task('deactivate-unverified-users', static fn() => DeactivateUnverifiedUsers::run())
    ->daily();

// Every week, permanently delete deactivated (unverified) users past the grace period
Scheduler::task('delete-deactivated-users', static fn() => DeleteDeactivatedUsers::run())
    ->weekly();
