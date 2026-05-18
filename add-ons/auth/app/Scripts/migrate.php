<?php

declare(strict_types=1);

use app\Database\DatabaseMigrator;
use app\Utils\Console;

/* ---------------------------------------------------------------- */

require_once 'start.php';

/* ---------------------------------------------------------------- */

Console::box('Migrate Database');
Console::line();

if (in_array('--fresh', $argv, true)) {
    Console::task("🗑️ Dropping existing database...");

    try {
        DatabaseMigrator::drop();
    } catch (Exception $e) {
        Console::line();
        Console::error("Failed to drop database: " . $e->getMessage());
        Console::line();
        exit(1);
    }

    Console::success("Existing database dropped");
    Console::line();
}

Console::task("🏗️ Running migrations...");

try {
    DatabaseMigrator::run();
} catch (Exception $e) {
    Console::line();
    Console::error("Migration failed: " . $e->getMessage());
    exit(1);
}

Console::divider();
Console::line();
Console::success("Database migrated successfully!", true);
Console::line();
