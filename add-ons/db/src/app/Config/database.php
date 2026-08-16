<?php

declare(strict_types=1);

define('DB_SERVER', $_ENV['DB_SERVER']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USERNAME', $_ENV['DB_USERNAME']);
define('DB_PASSWORD', $_ENV['DB_PASSWORD']);

// Schema defaults, applied during migrations
const DB_ENGINE = 'InnoDB';
const DB_CHARSET = 'utf8mb4';
const DB_COLLATION = 'utf8mb4_unicode_520_ci';
const DB_FOREIGN_KEY_ON_DELETE = 'CASCADE'; // CASCADE, SET NULL, or RESTRICT
const DB_PRIMARY_KEY = 'id';
