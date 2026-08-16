<?php

declare(strict_types=1);

namespace app\Database\Migrations;

use app\Database\DB;
use Closure;

class Schema
{
    public static function createDatabase(string $name): void
    {
        DB::raw("CREATE SCHEMA IF NOT EXISTS `$name` CHARACTER SET " . DB_CHARSET . " COLLATE " . DB_COLLATION);
    }

    public static function dropDatabase(string $name): void
    {
        DB::raw("DROP SCHEMA IF EXISTS `$name`");
    }

    public static function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $blueprint->build();
    }

    public static function drop(string $table): void
    {
        DB::raw("DROP TABLE IF EXISTS `$table`");
    }

    public static function disableForeignKeys(): void
    {
        DB::raw('SET FOREIGN_KEY_CHECKS = 0');
    }

    public static function enableForeignKeys(): void
    {
        DB::raw('SET FOREIGN_KEY_CHECKS = 1');
    }
}
