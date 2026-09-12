<?php

declare(strict_types=1);

const TWO_FACTOR_CONFIG = [
    'enabled' => true,                    // when false, no 2FA UI or login challenge exists
    'code_length' => 6,
    'email_code_expiry' => 600,           // seconds
    'totp_leeway' => 15,                  // seconds of clock drift tolerated on a TOTP code (must be < 30)
    'recovery_code_count' => 10,

    // Wrong-code throttling at the login challenge: per-account backoff, then a per-IP cap.
    'challenge_max_attempts' => 5,
    'challenge_attempt_window' => 300,    // seconds
    'challenge_min_lockout' => 120,       // seconds
    'challenge_max_lockout' => 900,       // seconds
    'challenge_ip_max_attempts' => 20,
    'challenge_ip_window' => 900,         // seconds

    'resend_cooldown' => 60,              // seconds between login email-code resends

    'force_for_roles' => ['Admin'],

    // "Remember me" at login also remembers the 2FA challenge on that device.
    // Overridden by opting out in security settings, a force-off role here, or 'allow' => false.
    'trusted_device' => [
        'allow' => true,
        'force_off_for_roles' => [],
    ],

    // Passkeys are bound to the APP_URL host and shown under APP_NAME; only the browser timeout is tunable.
    'webauthn' => [
        'timeout' => 60000,              // milliseconds
    ],
];
