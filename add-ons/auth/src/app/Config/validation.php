<?php

declare(strict_types=1);

// Must not exceed the corresponding database column lengths.
const MAX_EMAIL_LENGTH = 150;
const MAX_PASSWORD_LENGTH = 150;
const MAX_USERNAME_LENGTH = 100;
const MAX_NAME_LENGTH = 100;
const PUBLIC_ID_LENGTH = 16;

// Usernames regular users can't pick, on top of every role name and APP_NAME; matched loosely, see AuthController::isReservedUsername().
const RESERVED_USERNAMES = ['admin', 'administrator', 'moderator', 'mod', 'staff', 'support', 'system', 'root', 'owner', 'official', 'security'];

// Contact form
const MAX_CONTACT_SUBJECT_LENGTH = 100;
const MAX_CONTACT_MESSAGE_LENGTH = 1000;

// Roles
const MAX_ROLE_NAME_LENGTH = 50;
