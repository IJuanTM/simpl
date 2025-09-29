<?php

// Auth configuration
const MIN_PASSWORD_LENGTH = 8;
const REQUIRE_UPPERCASE = true;
const REQUIRE_LOWERCASE = true;
const REQUIRE_NUMBER = true;
const REQUIRE_SPECIAL_CHARACTER = false;

const EMAIL_VERIFICATION_REQUIRED = true;

const USER_LOGIN_ATTEMPTS = 5;
const MIN_USER_LOCKOUT_DURATION = 5; // in minutes
const MAX_USER_LOCKOUT_DURATION = 60; // in minutes
const USER_LOCKOUT_WINDOW = 5; // in minutes

const IP_LOGIN_ATTEMPTS = 20;
const MIN_IP_LOCKOUT_DURATION = 15; // in minutes
const MAX_IP_LOCKOUT_DURATION = 180; // in minutes
const IP_LOCKOUT_WINDOW = 15; // in minutes
