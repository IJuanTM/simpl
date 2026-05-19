<?php

declare(strict_types=1);

namespace app\Database;

use app\Database\Migrations\Schema;
use app\Database\Seeders\RolesSeeder;
use app\Database\Seeders\UserRolesSeeder;
use app\Database\Seeders\UsersSeeder;
use PDOException;
use Random\RandomException;

/**
 * Handles the execution of database seeding operations.
 * This class orchestrates the seeding of core data into the database
 * by invoking individual seeders in a specific order.
 */
class DatabaseSeeder
{
    // Order matters — seeders that depend on other seeded data must come after their dependencies
    private static array $seeders = [
        RolesSeeder::class,
        UsersSeeder::class,
        UserRolesSeeder::class,
    ];

    /**
     * Truncates all tables in the database to reset it.
     * This should be used with caution as it permanently deletes data.
     */
    public static function truncate(): void
    {
        DB::useDatabase(DB_NAME);

        try {
            Schema::disableForeignKeys();

            $tables = DB::query('SELECT LOWER(table_name) AS table_name FROM information_schema.tables WHERE table_schema = DATABASE()');
            foreach ($tables as $row) DB::query("TRUNCATE TABLE $row[table_name]");

            Schema::enableForeignKeys();
        } catch (PDOException $e) {
            Schema::enableForeignKeys();
            throw $e;
        }
    }

    /**
     * Executes the database seeding operations.
     * This method invokes individual seeders in a specific order
     * to populate the database with core data.
     *
     * @throws RandomException
     */
    public static function run(): void
    {
        DB::useDatabase(DB_NAME);

        foreach (self::$seeders as $seeder) $seeder::run();
    }
}
