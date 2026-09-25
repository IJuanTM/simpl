<?php

declare(strict_types=1);

use app\Database\DatabaseMigrator;
use app\Enums\Ansi;
use app\Utils\Console;

/* ---------------------------------------------------------------- */

require_once 'start.php';

/* ---------------------------------------------------------------- */

Console::titleBox('Migrate database', DB_NAME);
Console::line();

if (in_array('--rollback', $_SERVER['argv'] ?? [], true)) {
    Console::task('⏪ Rolling back last batch...');

    try {
        $rolled = DatabaseMigrator::rollback();
    } catch (Exception $e) {
        Console::fail('Rollback failed: ' . $e->getMessage());
    }

    Console::divider();

    if (empty($rolled)) Console::info('Nothing to roll back');
    else {
        foreach ($rolled as $migration) Console::item($migration);

        Console::line();
        Console::success(Console::styled('Rolled back ' . Console::plural(count($rolled), 'migration') . '!', Ansi::BOLD, Ansi::GREEN), true);
    }

    Console::line();
    exit(0);
}

if (in_array('--fresh', $_SERVER['argv'] ?? [], true)) {
    Console::task('🗑️ Dropping existing database...');

    try {
        DatabaseMigrator::drop();
    } catch (Exception $e) {
        Console::fail('Failed to drop database: ' . $e->getMessage());
    }

    Console::line();
    Console::success('Existing database dropped');
    Console::line();
}

Console::task('🏗️ Running migrations...');

try {
    $applied = DatabaseMigrator::run();
} catch (Exception $e) {
    Console::fail('Migration failed: ' . $e->getMessage());
}

Console::divider();

if (empty($applied)) Console::info('Nothing to migrate');
else {
    foreach ($applied as $migration) Console::item($migration);

    Console::line();
    Console::success(Console::styled('Ran ' . Console::plural(count($applied), 'migration') . '!', Ansi::BOLD, Ansi::GREEN), true);
}

Console::line();
