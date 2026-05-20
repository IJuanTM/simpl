<?php

declare(strict_types=1);

use app\Database\DatabaseMigrator;
use app\Utils\Console;

/* ---------------------------------------------------------------- */

require_once 'start.php';

/* ---------------------------------------------------------------- */

Console::box('Migrating Database: ' . DB_NAME);
Console::line();

if (in_array('--rollback', $argv, true)) {
    Console::task("⏪ Rolling back last batch...");

    try {
        $rolled = DatabaseMigrator::rollback();
    } catch (Exception $e) {
        Console::line();
        Console::error("Rollback failed: " . $e->getMessage());
        Console::line();
        exit(1);
    }

    Console::divider();
    Console::line();

    if (empty($rolled)) Console::info("Nothing to roll back.");
    else {
        foreach ($rolled as $migration) Console::warn("↩ $migration");

        Console::line();
        Console::success("Rolled back " . count($rolled) . " migration(s)!", true);
    }

    Console::line();
    exit(0);
}

if (in_array('--fresh', $argv, true)) {
    Console::task("🗑️ Dropping existing database...");
    Console::line();

    try {
        DatabaseMigrator::drop();
    } catch (Exception $e) {
        Console::error("Failed to drop database: " . $e->getMessage());
        Console::line();
        exit(1);
    }

    Console::success("Existing database dropped");
    Console::line();
}

Console::task("🏗️ Running migrations...");

try {
    $applied = DatabaseMigrator::run();
} catch (Exception $e) {
    Console::line();
    Console::error("Migration failed: " . $e->getMessage());
    Console::line();
    exit(1);
}

Console::divider();
Console::line();

if (empty($applied)) Console::info("Nothing to migrate.");
else {
    foreach ($applied as $migration) Console::success($migration);

    Console::line();
    Console::success("Migrated " . count($applied) . " migration(s)!", true);
}

Console::line();
