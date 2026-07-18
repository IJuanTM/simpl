<?php

declare(strict_types=1);

// Password policy
const MIN_PASSWORD_LENGTH = 8;
const REQUIRE_UPPERCASE = true;
const REQUIRE_LOWERCASE = true;
const REQUIRE_NUMBER = true;
const REQUIRE_SPECIAL_CHARACTER = false;

// Password hashing, use PASSWORD_BCRYPT for bcrypt
const PASSWORD_HASH_ALGO = PASSWORD_ARGON2ID;
const PASSWORD_HASH_OPTIONS = [
    'memory_cost' => 65536, // KiB
    'time_cost' => 4,       // iterations
    'threads' => 3
];

// Email verification
const EMAIL_VERIFICATION_REQUIRED = true;
const VERIFICATION_TOKEN_LENGTH = 8;     // characters
const VERIFICATION_MAX_ATTEMPTS = 10;    // wrong-code attempts per window before blocking
const VERIFICATION_ATTEMPT_WINDOW = 600; // seconds

// Remember me
const REMEMBER_ME_DURATION = 30;          // days

// Password reset
const RESET_TOKEN_LENGTH = 32;            // characters
const GENERATED_PASSWORD_LENGTH = 12;     // characters; for admin-created accounts
const PASSWORD_RESET_RESEND_TIMEOUT = 60; // seconds
const VERIFICATION_RESEND_TIMEOUT = 60;   // seconds
const CONTACT_RESEND_TIMEOUT = 60;        // seconds

// Inactive user cleanup
const UNVERIFIED_USER_DEACTIVATION_AFTER = 1; // days
const INACTIVE_USER_DELETION_AFTER = 7;       // days; counts from deactivation date
