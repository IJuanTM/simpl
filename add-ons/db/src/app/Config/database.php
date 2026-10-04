<?php

declare(strict_types=1);

define('DB_SERVER', $_ENV['DB_SERVER']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USERNAME', $_ENV['DB_USERNAME']);
define('DB_PASSWORD', $_ENV['DB_PASSWORD']);

// Schema defaults, applied during migrations
const DB_SCHEMA_DEFAULTS = [
    'engine' => 'InnoDB',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_520_ci',
    'foreign_key_on_delete' => 'CASCADE',    // CASCADE, SET NULL, or RESTRICT
    'primary_key' => 'id',
];
