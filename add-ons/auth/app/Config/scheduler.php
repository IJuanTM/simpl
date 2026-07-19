<?php

declare(strict_types=1);

use app\Cron\DeactivateUnverifiedUsers;
use app\Cron\DeleteDeactivatedUsers;
use app\Cron\PruneRateLimitCache;
use app\Utils\Scheduler;

/* ---------------------------------------------------------------- */

// Every day, deactivate users who haven't verified their email within the grace period
Scheduler::task('deactivate-unverified-users', static fn() => DeactivateUnverifiedUsers::run())
    ->daily();

// Every week, permanently delete deactivated (unverified) users past the grace period
Scheduler::task('delete-deactivated-users', static fn() => DeleteDeactivatedUsers::run())
    ->weekly();

// Every week, delete rate limit cache files that have been quiet long enough to be dead weight
Scheduler::task('prune-rate-limit-cache', static fn() => PruneRateLimitCache::run())
    ->weekly();
