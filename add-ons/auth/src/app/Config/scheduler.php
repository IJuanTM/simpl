@addon-insert:replace('// @addon-placeholder')
// Every day, deactivate users who haven't verified their email within the grace period
Scheduler::task('deactivate-unverified-users', static fn() => \app\Cron\DeactivateUnverifiedUsers::run())
->daily();

// Every week, permanently delete deactivated (unverified) users past the grace period
Scheduler::task('delete-deactivated-users', static fn() => \app\Cron\DeleteDeactivatedUsers::run())
->weekly();

// Every week, delete rate limit cache files that have been quiet long enough to be dead weight
Scheduler::task('prune-rate-limit-cache', static fn() => \app\Cron\PruneRateLimitCache::run())
->weekly();
@addon-end
