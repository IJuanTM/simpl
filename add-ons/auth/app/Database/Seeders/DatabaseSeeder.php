<?php

declare(strict_types=1);

namespace app\Database\Seeders;

use app\Database\DB;
use PDOException;
use Random\RandomException;

/**
 * Handles the execution of database seeding operations.
 * This class orchestrates the seeding of core data into the database
 * by invoking individual seeders in a specific order.
 */
class DatabaseSeeder
{
    /**
     * Truncates all seeded tables to reset the database.
     * This should be used with caution as it permanently deletes data.
     */
    public static function truncate(): void
    {
        try {
            // Disable foreign key checks temporarily
            DB::query('SET FOREIGN_KEY_CHECKS=0');

            // Truncate tables in reverse order of dependencies
            DB::query('TRUNCATE TABLE user_roles');
            DB::query('TRUNCATE TABLE users');
            DB::query('TRUNCATE TABLE roles');
            DB::query('TRUNCATE TABLE login_attempts');
            DB::query('TRUNCATE TABLE tokens');

            // Re-enable foreign key checks
            DB::query('SET FOREIGN_KEY_CHECKS=1');
        } catch (PDOException $e) {
            // Re-enable foreign key checks if truncate fails
            DB::query('SET FOREIGN_KEY_CHECKS=1');

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
        RolesSeeder::run();
        UsersSeeder::run();
        UserRolesSeeder::run();
    }
}
