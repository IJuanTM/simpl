<?php

declare(strict_types=1);

const PASSWORD_CONFIG = [
    'min_length' => 8,
    'require_uppercase' => true,
    'require_lowercase' => true,
    'require_number' => true,
    'require_special_character' => false,
    'hash_algo' => PASSWORD_ARGON2ID,     // use PASSWORD_BCRYPT for bcrypt
    'hash_options' => [
        'memory_cost' => 65536,           // KiB
        'time_cost' => 4,                 // iterations
        'threads' => 3,
    ],
    'generated_length' => 12,             // for admin-created accounts
];

// So response time can't reveal why a login failed or whether a submitted email is registered
const TIMING_FLOOR_MS = [
    'login' => 200,
    'register' => 200,
    'password_reset' => 200,
];

const VERIFICATION_CONFIG = [
    'required' => true,
    'token_length' => 8,             // characters
    'token_expiry' => 86400,         // seconds
    'account_max_attempts' => 5,     // wrong-code attempts per account before blocking
    'account_attempt_window' => 300, // seconds
    'ip_max_attempts' => 20,         // wrong-code attempts per IP before blocking
    'ip_attempt_window' => 900,      // seconds
];

const REMEMBER_ME_DURATION = 30; // days

const PASSWORD_RESET_CONFIG = [
    'token_length' => 32,             // characters
    'token_expiry' => 3600,           // seconds
    'account_max_attempts' => 3,      // reset requests per email before blocking
    'account_attempt_window' => 3600, // seconds
];

// Minimum seconds between resubmissions of each form
const RESEND_TIMEOUTS = [
    'verification' => 60,
    'contact' => 60,
    'register' => 60,
    'password_reset' => 60,
];

const INACTIVE_USER_CONFIG = [
    'unverified_deactivation_after_days' => 1,
    'deletion_after_days' => 7,                // counts from deactivation date
];
