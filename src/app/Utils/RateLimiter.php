<?php

declare(strict_types=1);

namespace app\Utils;

use app\Controllers\SessionController;

/**
 * Session-based sliding window rate limiter.
 * Stores attempt timestamps per key and prunes expired ones on each check.
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
        $sessionKey = 'rl_' . $key;

        $attempts = array_values(array_filter(
            SessionController::get($sessionKey) ?? [],
            fn(int $ts) => $now - $ts < $windowSeconds
        ));

        if (count($attempts) >= $max) {
            // Store when the oldest blocking attempt expires so the view can render data-timeout.
            SessionController::set('rl_retry_' . $key, $attempts[count($attempts) - $max] + $windowSeconds);
            return false;
        }

        $attempts[] = $now;
        SessionController::set($sessionKey, $attempts);
        SessionController::remove('rl_retry_' . $key);
        return true;
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
        $retryAt = SessionController::get('rl_retry_' . $key);
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
        SessionController::remove('rl_' . $key);
        SessionController::remove('rl_retry_' . $key);
    }
}
