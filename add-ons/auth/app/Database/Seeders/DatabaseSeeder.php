<?php

declare(strict_types=1);

namespace app\Database\Seeders;

/**
 * Handles the execution of database seeding operations.
 * This class orchestrates the seeding of core data into the database
 * by invoking individual seeders in a specific order.
 */
class DatabaseSeeder
{
    public static function run(): void
    {
        RolesSeeder::run();
        UsersSeeder::run();
        UserRolesSeeder::run();
    }
}
