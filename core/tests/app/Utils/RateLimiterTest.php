<?php

declare(strict_types=1);

namespace tests\Utils;

use app\Utils\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private array $keys = [];

    public function testIpKeyFallsBackToUnknownWithoutRemoteAddr(): void
    {
        // Arrange
        $previous = $_SERVER['REMOTE_ADDR'] ?? null;
        unset($_SERVER['REMOTE_ADDR']);

        try {
            // Act + Assert
            $this->assertSame('login-unknown', RateLimiter::ipKey('login'));
        } finally {
            // Cleanup
            if ($previous !== null) $_SERVER['REMOTE_ADDR'] = $previous;
        }
    }

    public function testAllowsAttemptsUpToTheMax(): void
    {
        // Arrange
        $key = $this->key('attempt');

        // Act + Assert
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
        // Arrange
        $key = $this->key('block');

        // Act
        RateLimiter::attempt($key, 2, 60);
        RateLimiter::attempt($key, 2, 60);

        // Assert
        $this->assertFalse(RateLimiter::attempt($key, 2, 60));
    }

    public function testBackoffAllowsAttemptsUpToTheMax(): void
    {
        // Arrange
        $key = $this->key('backoff-attempt');

        // Act + Assert
        $this->assertTrue(RateLimiter::attemptWithBackoff($key, 3, 60, 10, 100));
        $this->assertTrue(RateLimiter::attemptWithBackoff($key, 3, 60, 10, 100));
        $this->assertTrue(RateLimiter::attemptWithBackoff($key, 3, 60, 10, 100));
    }

    public function testBackoffBlocksOnceTheBurstIsExceeded(): void
    {
        // Arrange
        $key = $this->key('backoff-block');

        // Act
        RateLimiter::attemptWithBackoff($key, 2, 60, 10, 100);
        RateLimiter::attemptWithBackoff($key, 2, 60, 10, 100);

        // Assert
        $this->assertFalse(RateLimiter::attemptWithBackoff($key, 2, 60, 10, 100));
    }

    public function testBackoffRejectedAttemptDoesNotExtendTheLockout(): void
    {
        // Arrange
        $key = $this->key('backoff-no-extend');
        RateLimiter::attemptWithBackoff($key, 1, 60, 10, 100);
        RateLimiter::attemptWithBackoff($key, 1, 60, 10, 100);
        $firstRetry = RateLimiter::retryAfterMs($key);

        // Act
        RateLimiter::attemptWithBackoff($key, 1, 60, 10, 100);
        $secondRetry = RateLimiter::retryAfterMs($key);

        // Assert
        $this->assertGreaterThan(0, $firstRetry);
        $this->assertEqualsWithDelta($firstRetry, $secondRetry, 1000);
    }

    public function testBackoffRetryAfterMsIsZeroWhenNotLimited(): void
    {
        // Arrange
        $key = $this->key('backoff-retry-ok');
        RateLimiter::attemptWithBackoff($key, 5, 60, 10, 100);

        // Act + Assert
        $this->assertSame(0, RateLimiter::retryAfterMs($key));
    }

    public function testBackoffLockoutLiftsOnceElapsedThenTheNextBurstEscalates(): void
    {
        // Arrange
        $key = $this->key('backoff-tiers');
        $file = BASEDIR . '/cache/ratelimit/' . hash('sha256', $key) . '.json';
        RateLimiter::attemptWithBackoff($key, 1, 60, 10, 1000);
        $this->assertFalse(RateLimiter::attemptWithBackoff($key, 1, 60, 10, 1000));
        $firstRetry = RateLimiter::retryAfterMs($key);

        // Simulate the tier-1 lockout being fully served: its burst has aged out and $retry is in the past.
        file_put_contents($file, json_encode(['attempts' => [time() - 120], 'tier' => 1, 'retry' => time() - 1]));

        // Act
        $allowedAgain = RateLimiter::attemptWithBackoff($key, 1, 60, 10, 1000);
        $reLocked = RateLimiter::attemptWithBackoff($key, 1, 60, 10, 1000);
        $secondRetry = RateLimiter::retryAfterMs($key);

        // Assert
        $this->assertTrue($allowedAgain);
        $this->assertFalse($reLocked);
        $this->assertGreaterThan(0, $firstRetry);
        $this->assertGreaterThan($firstRetry, $secondRetry);
    }

    public function testRetryAfterMsIsZeroWhenNotLimited(): void
    {
        // Arrange
        $key = $this->key('retry-ok');
        RateLimiter::attempt($key, 5, 60);

        // Act + Assert
        $this->assertSame(0, RateLimiter::retryAfterMs($key));
    }

    public function testRetryAfterMsIsPositiveOnceLimited(): void
    {
        // Arrange
        $key = $this->key('retry-limited');
        RateLimiter::attempt($key, 1, 60);
        RateLimiter::attempt($key, 1, 60);

        // Act + Assert
        $this->assertGreaterThan(0, RateLimiter::retryAfterMs($key));
    }

    public function testClearResetsTheWindow(): void
    {
        // Arrange
        $key = $this->key('clear');
        RateLimiter::attempt($key, 1, 60);
        $this->assertFalse(RateLimiter::attempt($key, 1, 60));

        // Act
        RateLimiter::clear($key);

        // Assert
        $this->assertTrue(RateLimiter::attempt($key, 1, 60));
    }

    public function testPruneRemovesFilesOlderThanTheThreshold(): void
    {
        // Arrange
        $key = $this->key('prune');
        RateLimiter::attempt($key, 5, 60);
        $file = BASEDIR . '/cache/ratelimit/' . hash('sha256', $key) . '.json';
        $this->assertFileExists($file);
        touch($file, time() - 1000);

        // Act
        $deleted = RateLimiter::prune(500);

        // Assert
        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertFileDoesNotExist($file);
    }

    protected function tearDown(): void
    {
        foreach ($this->keys as $key) RateLimiter::clear($key);
    }
}
