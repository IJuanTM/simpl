<?php

declare(strict_types=1);

namespace app\Pages\Traits;

use app\Controllers\FormController;
use app\Enums\AlertType;
use app\Utils\RateLimiter;

/**
 * IP-scoped rate limiting for single-submit forms (contact, forgot-password, register, ...).
 * Host class calls initRateLimitedForm() from its constructor - it returns whether the
 * request should be dispatched to post() - and attemptRateLimit() from within post().
 */
trait RateLimitedForm
{
    public int $cooldown = 0;
    private string $rlKey;

    /**
     * Scopes the rate limit to the client IP and reads the current cooldown for display,
     * unless this is the submitting request (attemptRateLimit() refreshes it itself if
     * the submission turns out to be blocked).
     *
     * @param string $prefix Unique prefix for this form's rate limit key
     *
     * @return bool True when this request should be dispatched to post()
     */
    private function initRateLimitedForm(string $prefix): bool
    {
        $isSubmitting = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit']);

        $this->rlKey = RateLimiter::ipKey($prefix);
        if (!$isSubmitting) $this->cooldown = RateLimiter::retryAfterMs($this->rlKey);

        return $isSubmitting;
    }

    /**
     * Records an attempt; on failure refreshes $cooldown and queues the standard alert.
     * Call from post(), after validation, before performing the rate-limited action.
     *
     * @param int $windowSeconds Rolling time window in seconds
     *
     * @return bool True when the attempt is allowed
     */
    private function attemptRateLimit(int $windowSeconds): bool
    {
        if (RateLimiter::attempt($this->rlKey, 1, $windowSeconds)) return true;

        $this->cooldown = RateLimiter::retryAfterMs($this->rlKey);
        FormController::addAlert('Too many attempts. Please wait a moment before trying again.', AlertType::WARNING);
        return false;
    }
}
