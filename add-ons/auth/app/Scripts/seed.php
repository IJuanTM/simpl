<?php

declare(strict_types=1);

use app\Database\DatabaseSeeder;
use app\Utils\Console;

/* ---------------------------------------------------------------- */

require_once 'start.php';

/* ---------------------------------------------------------------- */

Console::box('Seeding Database: ' . DB_NAME);
Console::line();

if (in_array('--fresh', $argv, true)) {
    Console::task("🗑️ Dropping existing data...");
    Console::line();

    try {
        DatabaseSeeder::truncate();
    } catch (Exception $e) {
        Console::error("Failed to drop data: " . $e->getMessage());
        Console::line();
        exit(1);
    }

    Console::success("Existing data dropped");
    Console::line();
}

Console::task("🌱 Seeding database...");
Console::line();

try {
    DatabaseSeeder::run();
} catch (Exception $e) {
    Console::error("Seeding failed: " . $e->getMessage());
    exit(1);
}

Console::divider();
Console::line();
Console::success("Database seeded successfully!", true);
Console::line();
