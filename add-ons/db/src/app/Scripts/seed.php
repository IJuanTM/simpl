<?php

declare(strict_types=1);

use app\Database\DatabaseSeeder;
use app\Utils\Console;

/* ---------------------------------------------------------------- */

require_once 'start.php';

/* ---------------------------------------------------------------- */

// Seeding inserts development/test data, including accounts with weak default passwords.
// Refuse to run outside of development to avoid seeding those into a production database.
if (!DEV) Console::fail('Seeding is disabled outside of development. Set DEV=true to seed.');

Console::box('Seeding Database: ' . DB_NAME);
Console::line();

if (in_array('--fresh', $_SERVER['argv'] ?? [], true)) {
    Console::task("🗑️ Dropping existing data...");
    Console::line();

    try {
        DatabaseSeeder::truncate();
    } catch (Exception $e) {
        Console::fail("Failed to drop data: " . $e->getMessage());
    }

    Console::success("Existing data dropped");
    Console::line();
}

Console::task("🌱 Seeding database...");

try {
    DatabaseSeeder::run();
} catch (Exception $e) {
    Console::fail("Seeding failed: " . $e->getMessage());
}

Console::divider();
Console::line();
Console::success("Database seeded successfully!", true);
Console::line();
