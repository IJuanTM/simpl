<?php

declare(strict_types=1);

namespace app\Cron;

use app\Utils\Console;
use app\Utils\RateLimiter;

class PruneRateLimitCache
{
    /**
     * Prunes stale rate-limit cache files and reports how many were deleted.
     *
     * @return void
     */
    public static function run(): void
    {
        $deleted = RateLimiter::prune();

        Console::info($deleted > 0
            ? "Pruned $deleted stale rate limit file" . ($deleted !== 1 ? 's' : '')
            : 'No stale rate limit files to prune');

        Console::line();
    }
}
