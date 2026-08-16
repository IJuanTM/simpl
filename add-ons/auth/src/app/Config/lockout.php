<?php

declare(strict_types=1);

// Uses exponential backoff: duration doubles after each threshold of failed attempts.

// Per-user lockout
const USER_LOGIN_ATTEMPTS = 5;        // attempts per window before lockout
const USER_LOCKOUT_WINDOW = 5;        // minutes
const MIN_USER_LOCKOUT_DURATION = 5;  // minutes; first lockout
const MAX_USER_LOCKOUT_DURATION = 60; // minutes

// Per-IP lockout
const IP_LOGIN_ATTEMPTS = 20;        // attempts per window before lockout
const IP_LOCKOUT_WINDOW = 15;        // minutes
const MIN_IP_LOCKOUT_DURATION = 15;  // minutes; first lockout
const MAX_IP_LOCKOUT_DURATION = 180; // minutes
