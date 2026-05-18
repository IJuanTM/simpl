<?php

declare(strict_types=1);

namespace app\Database;

use app\Database\Migrations\Schema;
use app\Database\Migrations\Tables\CreateLoginAttemptsTable;
use app\Database\Migrations\Tables\CreateRolesTable;
use app\Database\Migrations\Tables\CreateSchedulerRunsTable;
use app\Database\Migrations\Tables\CreateTokensTable;
use app\Database\Migrations\Tables\CreateUserRolesTable;
use app\Database\Migrations\Tables\CreateUsersTable;
use PDOException;

/**
 * Handles the execution of database migration operations.
 * This class orchestrates the creation of the database schema and tables
 * by invoking individual migrations in the correct dependency order.
 */
class DatabaseMigrator
{
    // Order matters — tables with foreign keys must come after the tables they reference
    private static array $migrations = [
        CreateUsersTable::class,
        CreateLoginAttemptsTable::class,
        CreateTokensTable::class,
        CreateRolesTable::class,
        CreateUserRolesTable::class,
        CreateSchedulerRunsTable::class,
    ];

    /**
     * Drops the entire database schema.
     * This should be used with caution as it permanently deletes all data and structure.
     */
    public static function drop(): void
    {
        Schema::dropDatabase(DB_NAME);
    }

    /**
     * Executes the database migration operations.
     * This method creates the schema and all tables in dependency order.
     *
     * @throws PDOException
     */
    public static function run(): void
    {
        Schema::createDatabase(DB_NAME);

        // Switch the connection to the newly created database
        DB::useDatabase(DB_NAME);

        try {
            DB::beginTransaction();

            foreach (self::$migrations as $migration) $migration::run();

            DB::commit();
        } catch (PDOException $e) {
            DB::rollback();
            throw $e;
        }
    }
}
