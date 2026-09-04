<?php

declare(strict_types=1);

namespace tests\Utils;

use app\Utils\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private array $keys = [];

    public function testAllowsAttemptsUpToTheMax(): void
    {
        $key = $this->key('attempt');

        $this->assertTrue(RateLimiter::attempt($key, 3, 60));
        $this->assertTrue(RateLimiter::attempt($key, 3, 60));
        $this->assertTrue(RateLimiter::attempt($key, 3, 60));
    }

    private function key(string $name): string
    {
        $key = $name . '-' . bin2hex(random_bytes(8));
        $this->keys[] = $key;
        return $key;
    }

    public function testBlocksOnceTheMaxIsExceeded(): void
    {
        $key = $this->key('block');

        RateLimiter::attempt($key, 2, 60);
        RateLimiter::attempt($key, 2, 60);

        $this->assertFalse(RateLimiter::attempt($key, 2, 60));
    }

    public function testClearResetsTheWindow(): void
    {
        $key = $this->key('clear');

        RateLimiter::attempt($key, 1, 60);
        $this->assertFalse(RateLimiter::attempt($key, 1, 60));

        RateLimiter::clear($key);

        $this->assertTrue(RateLimiter::attempt($key, 1, 60));
    }

    public function testRetryAfterMsIsZeroWhenNotLimited(): void
    {
        $key = $this->key('retry-ok');

        RateLimiter::attempt($key, 5, 60);

        $this->assertSame(0, RateLimiter::retryAfterMs($key));
    }

    public function testRetryAfterMsIsPositiveOnceLimited(): void
    {
        $key = $this->key('retry-limited');

        RateLimiter::attempt($key, 1, 60);
        RateLimiter::attempt($key, 1, 60);

        $this->assertGreaterThan(0, RateLimiter::retryAfterMs($key));
    }

    public function testIpKeyFallsBackToUnknownWithoutRemoteAddr(): void
    {
        $previous = $_SERVER['REMOTE_ADDR'] ?? null;
        unset($_SERVER['REMOTE_ADDR']);

        $this->assertSame('login-unknown', RateLimiter::ipKey('login'));

        if ($previous !== null) $_SERVER['REMOTE_ADDR'] = $previous;
    }

    public function testPruneRemovesFilesOlderThanTheThreshold(): void
    {
        $key = $this->key('prune');
        RateLimiter::attempt($key, 5, 60);

        $file = BASEDIR . '/cache/ratelimit/' . hash('sha256', $key) . '.json';
        $this->assertFileExists($file);

        touch($file, time() - 1000);

        $deleted = RateLimiter::prune(500);

        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertFileDoesNotExist($file);
    }

    protected function tearDown(): void
    {
        foreach ($this->keys as $key) RateLimiter::clear($key);
    }
}
