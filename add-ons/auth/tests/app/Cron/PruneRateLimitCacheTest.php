<?php

declare(strict_types=1);

namespace tests\Cron;

use app\Cron\PruneRateLimitCache;
use app\Utils\RateLimiter;
use PHPUnit\Framework\TestCase;

class PruneRateLimitCacheTest extends TestCase
{
    public function testReportsWhenNothingWasPruned(): void
    {
        // Clears any stale file left over from other runs/local dev use of this shared directory.
        RateLimiter::prune(0);

        ob_start();
        PruneRateLimitCache::run();
        $output = ob_get_clean();

        $this->assertStringContainsString('No stale rate limit files to prune', $output);
    }

    public function testReportsHowManyFilesWerePruned(): void
    {
        $key = 'prune-cron-test-' . uniqid();
        RateLimiter::attempt($key, 5, 60);
        $file = BASEDIR . '/cache/ratelimit/' . hash('sha256', $key) . '.json';
        touch($file, time() - 90000); // older than prune()'s default 86400s (1 day) threshold

        ob_start();
        PruneRateLimitCache::run();
        $output = ob_get_clean();

        $this->assertMatchesRegularExpression('/Pruned [1-9]\d* stale rate limit files?/', $output);
    }
}
