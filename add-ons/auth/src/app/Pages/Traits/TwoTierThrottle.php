<?php

declare(strict_types=1);

namespace app\Pages\Traits;

use app\Controllers\FormController;
use app\Enums\AlertType;
use app\Utils\RateLimiter;

/**
 * Two-tier attempt throttle: an escalating per-account backoff, then a flat per-IP cap.
 * Shared by pages that guard a per-account secret (2FA code, reset token, verification token)
 * against guessing, where the account tier needs to escalate far more aggressively than the shared IP behind it.
 */
trait TwoTierThrottle
{
    /**
     * Checks the account-scoped backoff first, then the IP-scoped cap, queuing an alert and
     * returning true on whichever tier is hit first.
     *
     * @param string $accountKey           Unique rate-limit key for this account/action
     * @param int    $accountMaxAttempts   Attempts allowed in one burst before a lockout starts
     * @param int    $accountWindowSeconds Burst window: attempts must land within this of the newest to count together
     * @param int    $accountMinLockout    First lockout duration
     * @param int    $accountMaxLockout    Lockout duration ceiling
     * @param string $accountMessage       Alert shown when the account tier is hit
     * @param string $ipKeyPrefix          Prefix for the IP-scoped rate limit key
     * @param int    $ipMaxAttempts        Maximum attempts allowed per IP in the window
     * @param int    $ipWindowSeconds      Rolling time window in seconds for the IP tier
     * @param string $ipMessage            Alert shown when the IP tier is hit
     *
     * @return bool True when either tier was hit and an alert has been queued
     */
    private function twoTierThrottle(
        string $accountKey,
        int    $accountMaxAttempts,
        int    $accountWindowSeconds,
        int    $accountMinLockout,
        int    $accountMaxLockout,
        string $accountMessage,
        string $ipKeyPrefix,
        int    $ipMaxAttempts,
        int    $ipWindowSeconds,
        string $ipMessage
    ): bool
    {
        if (!RateLimiter::attemptWithBackoff($accountKey, $accountMaxAttempts, $accountWindowSeconds, $accountMinLockout, $accountMaxLockout)) {
            FormController::addAlert($accountMessage, AlertType::ERROR);
            return true;
        }

        if (!RateLimiter::attempt(RateLimiter::ipKey($ipKeyPrefix), $ipMaxAttempts, $ipWindowSeconds)) {
            FormController::addAlert($ipMessage, AlertType::ERROR);
            return true;
        }

        return false;
    }
}
