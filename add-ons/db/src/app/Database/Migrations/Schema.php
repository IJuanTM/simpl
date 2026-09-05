<?php

declare(strict_types=1);

namespace app\Database\Migrations;

use app\Database\DB;
use Closure;

/**
 * Static helpers for schema-level DDL: creating/dropping databases and tables, and toggling
 * foreign key checks during bulk operations.
 */
class Schema
{
    /**
     * Creates the database if it doesn't already exist, using the configured charset/collation.
     */
    public static function createDatabase(string $name): void
    {
        DB::raw("CREATE SCHEMA IF NOT EXISTS `$name` CHARACTER SET " . DB_SCHEMA_DEFAULTS['charset'] . " COLLATE " . DB_SCHEMA_DEFAULTS['collation']);
    }

    /**
     * Drops the database if it exists.
     */
    public static function dropDatabase(string $name): void
    {
        DB::raw("DROP SCHEMA IF EXISTS `$name`");
    }

    /**
     * Builds a new table via a Blueprint, populated by $callback.
     */
    public static function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $blueprint->build();
    }

    /**
     * Drops a table if it exists.
     */
    public static function drop(string $table): void
    {
        DB::raw("DROP TABLE IF EXISTS `$table`");
    }

    /**
     * Disables foreign key checks on the current connection (e.g. while truncating tables out of dependency order).
     */
    public static function disableForeignKeys(): void
    {
        DB::raw('SET FOREIGN_KEY_CHECKS = 0');
    }

    /**
     * Re-enables foreign key checks on the current connection.
     */
    public static function enableForeignKeys(): void
    {
        DB::raw('SET FOREIGN_KEY_CHECKS = 1');
    }
}
