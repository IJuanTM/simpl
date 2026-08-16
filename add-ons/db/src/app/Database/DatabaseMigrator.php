<?php

declare(strict_types=1);

namespace app\Database;

use app\Database\Migrations\Schema;

/**
 * Handles the execution of database migration operations.
 * Runs migration classes registered via register(), tracking which have already run in a
 * `migrations` table it creates for itself. Add-ons register their own migrations, in
 * dependency order, from their own Config file.
 */
class DatabaseMigrator
{
    /** @var class-string[] */
    private static array $migrations = [];

    /**
     * Register a migration class to be run. Call in dependency order - migrations with
     * foreign keys must be registered after the tables they reference.
     *
     * @param class-string $migration
     *
     * @return void
     */
    public static function register(string $migration): void
    {
        self::$migrations[] = $migration;
    }

    /**
     * Drops the entire database schema.
     * This should be used with caution as it permanently deletes all data and structure.
     */
    public static function drop(): void
    {
        Schema::dropDatabase(DB_NAME);
    }

    /**
     * Runs all pending migrations and returns the names of those applied.
     *
     * @return string[]
     */
    public static function run(): array
    {
        Schema::createDatabase(DB_NAME);
        DB::useDatabase(DB_NAME);
        self::ensureMigrationsTable();

        $ran = self::getRanMigrations();
        $pending = array_filter(self::$migrations, static fn($m) => !in_array(self::name($m), $ran, true));

        if (empty($pending)) return [];

        $batch = self::nextBatch();
        $applied = [];

        foreach ($pending as $migration) {
            $migration::up();
            self::record(self::name($migration), $batch);
            $applied[] = self::name($migration);
        }

        return $applied;
    }

    private static function ensureMigrationsTable(): void
    {
        DB::raw(
            "CREATE TABLE IF NOT EXISTS `migrations` (\n" .
            "  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,\n" .
            "  `migration` VARCHAR(255) NOT NULL,\n" .
            "  `batch` INT UNSIGNED NOT NULL,\n" .
            "  `ran_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n" .
            "  PRIMARY KEY (`id`)\n" .
            ") ENGINE = " . DB_SCHEMA_DEFAULTS['engine']
        );
    }

    private static function getRanMigrations(): array
    {
        return array_column(DB::select(
            SELECT: 'migration',
            FROM: 'migrations',
            ORDER_BY: 'id'
        ), 'migration');
    }

    private static function name(string $class): string
    {
        // Full name, not basename, so migrations from different add-ons can't collide.
        return ltrim($class, '\\');
    }

    private static function nextBatch(): int
    {
        return (int)DB::single(
            SELECT: 'COALESCE(MAX(batch), 0) + 1 AS next',
            FROM: 'migrations'
        )['next'];
    }

    private static function record(string $name, int $batch): void
    {
        DB::insert(
            INTO: 'migrations',
            VALUES: ['migration' => $name, 'batch' => $batch]
        );
    }

    /**
     * Rolls back the last batch of migrations and returns the names of those reversed.
     *
     * @return string[]
     */
    public static function rollback(): array
    {
        DB::useDatabase(DB_NAME);
        self::ensureMigrationsTable();

        $batch = self::lastBatch();
        if ($batch === null) return [];

        $names = self::batchMigrations($batch);
        $map = array_combine(
            array_map(static fn($m) => self::name($m), self::$migrations),
            self::$migrations
        );

        $rolled = [];

        foreach (array_reverse($names) as $name) {
            if (isset($map[$name])) {
                $map[$name]::down();
                self::remove($name);
                $rolled[] = $name;
            }
        }

        return $rolled;
    }

    private static function lastBatch(): ?int
    {
        $val = DB::single(
            SELECT: 'MAX(batch) AS last',
            FROM: 'migrations'
        )['last'] ?? null;
        return $val !== null ? (int)$val : null;
    }

    private static function batchMigrations(int $batch): array
    {
        return array_column(DB::select(
            SELECT: 'migration',
            FROM: 'migrations',
            WHERE: ['batch' => $batch],
            ORDER_BY: 'id'
        ), 'migration');
    }

    private static function remove(string $name): void
    {
        DB::delete(
            FROM: 'migrations',
            WHERE: ['migration' => $name]
        );
    }
}
