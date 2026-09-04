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
     * Builds a rate-limit key scoped to the client's IP address (falls back to 'unknown').
     *
     * @param string $prefix
     *
     * @return string
     */
    public static function ipKey(string $prefix): string
    {
        return $prefix . '-' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    /**
     * Record an attempt and return whether it is within the allowed limit.
     * Read-check-write runs under one exclusive lock to avoid a race between concurrent calls.
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
        $file = self::path($key);

        $handle = fopen($file, 'c+');
        if ($handle === false) throw new RuntimeException(sprintf('Could not open rate limit file "%s"', $file));

        try {
            flock($handle, LOCK_EX);

            $raw = stream_get_contents($handle);
            $data = $raw ? json_decode($raw, true) : null;

            $attempts = array_values(array_filter(
                is_array($data) ? ($data['attempts'] ?? []) : [],
                static fn(int $ts) => $now - $ts < $windowSeconds
            ));

            $allowed = count($attempts) < $max;
            if ($allowed) $attempts[] = $now;

            // Report the next-allowed time whenever the window is now at capacity, even on an allowed call.
            // That way the view can render an accurate data-timeout right away, not only after the following (rejected) attempt.
            $retry = count($attempts) >= $max ? $attempts[count($attempts) - $max] + $windowSeconds : 0;
            self::writeLocked($handle, ['attempts' => $attempts, 'retry' => $retry]);

            return $allowed;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
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
        $dir = BASEDIR . '/cache/ratelimit';

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        return $dir . '/' . hash('sha256', $key) . '.json';
    }

    /**
     * Overwrites the record in an already-open, already-locked file handle.
     *
     * @param resource $handle
     * @param array    $data
     *
     * @return void
     */
    private static function writeLocked($handle, array $data): void
    {
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($data));
        fflush($handle);
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
     * Read the stored record for a key, or an empty array when none exists.
     * Takes a shared lock so it can't observe attempt()'s write mid-truncate.
     *
     * @param string $key
     *
     * @return array
     */
    private static function read(string $key): array
    {
        $file = self::path($key);
        if (!is_file($file)) return [];

        $handle = fopen($file, 'r');
        if ($handle === false) return [];

        try {
            flock($handle, LOCK_SH);
            $raw = stream_get_contents($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $data = json_decode((string)$raw, true);
        return is_array($data) ? $data : [];
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

    /**
     * Deletes cache files whose mtime is older than $olderThanSeconds. Call from a scheduled task.
     *
     * @param int $olderThanSeconds Age threshold; defaults to RATE_LIMIT_CACHE_RETENTION
     *
     * @return int Number of files deleted
     */
    public static function prune(int $olderThanSeconds = RATE_LIMIT_CACHE_RETENTION): int
    {
        $dir = BASEDIR . '/cache/ratelimit';
        if (!is_dir($dir)) return 0;

        $cutoff = time() - $olderThanSeconds;
        $deleted = 0;

        foreach (glob("$dir/*.json") ?: [] as $file) {
            $mtime = filemtime($file);
            if ($mtime !== false && $mtime < $cutoff && unlink($file)) $deleted++;
        }

        return $deleted;
    }
}
