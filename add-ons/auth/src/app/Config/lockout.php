<?php

declare(strict_types=1);

// Uses exponential backoff: duration doubles after each threshold of failed attempts.
const LOCKOUT_CONFIG = [
    'user' => [
        'max_attempts' => 5,         // attempts per window before lockout
        'window_minutes' => 5,
        'min_duration_minutes' => 5, // first lockout
        'max_duration_minutes' => 60,
    ],
    'ip' => [
        'max_attempts' => 20,
        'window_minutes' => 15,
        'min_duration_minutes' => 15,
        'max_duration_minutes' => 180,
    ],
    // Change-password old-password check, per authenticated user (always known, no IP tier needed)
    'change_password' => [
        'max_attempts' => 5,     // wrong-old-password attempts before blocking
        'window_seconds' => 300,
    ],
];
