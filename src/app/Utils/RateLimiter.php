<?php

declare(strict_types=1);

namespace app\Utils;

use RuntimeException;

/**
 * File-based sliding window rate limiter.
 *
 * Attempt timestamps are stored on disk keyed by a hash of the caller's key, so limits
 * survive across requests and cannot be reset by clearing the session cookie. Callers that
 * need per-client scoping should include the client IP (or user id) in the key.
 */
class RateLimiter
{
    /**
     * Record an attempt and return whether it is within the allowed limit.
     *
     * @param string $key           Unique identifier for the action being limited
     * @param int    $max           Maximum number of attempts allowed in the window
     * @param int    $windowSeconds Rolling time window in seconds
     *
     * @return bool True if the attempt is allowed, false if the limit is exceeded
     */
    public static function attempt(string $key, int $max, int $windowSeconds): bool
    {
        $now = time();

        $attempts = array_values(array_filter(
            self::read($key)['attempts'] ?? [],
            static fn(int $ts) => $now - $ts < $windowSeconds
        ));

        if (count($attempts) >= $max) {
            // Store when the oldest blocking attempt expires so the view can render data-timeout.
            self::write($key, ['attempts' => $attempts, 'retry' => $attempts[count($attempts) - $max] + $windowSeconds]);
            return false;
        }

        $attempts[] = $now;
        self::write($key, ['attempts' => $attempts, 'retry' => 0]);
        return true;
    }

    /**
     * Read the stored record for a key, or an empty array when none exists.
     *
     * @param string $key
     *
     * @return array
     */
    private static function read(string $key): array
    {
        $file = self::path($key);
        if (!is_file($file)) return [];

        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Resolve the on-disk path for a key, creating the storage directory if needed.
     *
     * @param string $key
     *
     * @return string
     */
    private static function path(string $key): string
    {
        $dir = BASEDIR . '/app/Cache/ratelimit';

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        return $dir . '/' . hash('sha256', $key) . '.json';
    }

    /**
     * Persist the record for a key.
     *
     * @param string $key
     * @param array  $data
     *
     * @return void
     */
    private static function write(string $key, array $data): void
    {
        file_put_contents(self::path($key), json_encode($data), LOCK_EX);
    }

    /**
     * Remaining milliseconds until another attempt is allowed.
     * Returns 0 when not rate limited. Intended for rendering data-timeout on submit buttons.
     *
     * @param string $key
     *
     * @return int
     */
    public static function retryAfterMs(string $key): int
    {
        $retryAt = self::read($key)['retry'] ?? 0;
        return $retryAt ? max(0, ($retryAt - time()) * 1000) : 0;
    }

    /**
     * Clear the attempt history for a key.
     *
     * @param string $key
     *
     * @return void
     */
    public static function clear(string $key): void
    {
        $file = self::path($key);
        if (is_file($file)) unlink($file);
    }
}
