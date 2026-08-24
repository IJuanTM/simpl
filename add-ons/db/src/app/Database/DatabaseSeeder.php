<?php

declare(strict_types=1);

namespace app\Database;

use app\Database\Migrations\Schema;
use PDOException;
use Random\RandomException;

/**
 * Handles the execution of database seeding operations.
 * Runs seeder classes registered via register(), in the order they were registered. Add-ons
 * register their own seeders, in dependency order, from their own Config file.
 */
class DatabaseSeeder
{
    private const array BOOKKEEPING_TABLES = ['migrations', 'scheduler_runs'];
    /** @var class-string[] */
    private static array $seeders = [];

    // Schema-bookkeeping tables db owns and manages itself - never domain data, so they're
    // excluded from truncate() (unlike migration files, they have no re-seedable content anyway).

    /**
     * Register a seeder class to be run. Call in dependency order - seeders with foreign
     * keys must be registered after the tables they reference.
     *
     * @param class-string $seeder
     *
     * @return void
     */
    public static function register(string $seeder): void
    {
        self::$seeders[] = $seeder;
    }

    /**
     * Truncates all tables in the database to reset it, except schema-bookkeeping tables.
     * This should be used with caution as it permanently deletes data.
     */
    public static function truncate(): void
    {
        DB::useDatabase(DB_NAME);

        try {
            Schema::disableForeignKeys();

            $placeholders = [];
            $params = [];
            foreach (self::BOOKKEEPING_TABLES as $i => $table) {
                $placeholders[] = ":bookkeeping$i";
                $params[":bookkeeping$i"] = $table;
            }

            $tables = DB::query('SELECT LOWER(table_name) AS table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND LOWER(table_name) NOT IN (' . implode(', ', $placeholders) . ')', $params);
            foreach ($tables as $row) DB::query("TRUNCATE TABLE `$row[table_name]`");

            Schema::enableForeignKeys();
        } catch (PDOException $e) {
            Schema::enableForeignKeys();
            throw $e;
        }
    }

    /**
     * Executes the database seeding operations.
     * Runs each registered seeder in turn to populate the database with core data.
     *
     * @throws RandomException
     */
    public static function run(): void
    {
        DB::useDatabase(DB_NAME);

        foreach (self::$seeders as $seeder) $seeder::run();
    }
}
