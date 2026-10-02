<?php

declare(strict_types=1);

namespace app\Cron;

use app\Cron\Traits\CronReport;
use app\Utils\RateLimiter;

class PruneRateLimitCache
{
    use CronReport;

    /**
     * Prunes stale rate-limit cache files and reports how many were deleted.
     *
     * @return void
     */
    public static function run(): void
    {
        self::report(RateLimiter::prune(), 'Pruned', 'stale rate limit file', 'No stale rate limit files to prune');
    }
}
