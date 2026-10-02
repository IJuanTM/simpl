<?php

declare(strict_types=1);

namespace tests\Integration\Seeding;

use app\Database\DatabaseSeeder;
use app\Database\DB;
use ReflectionProperty;
use tests\Integration\IntegrationTestCase;

/**
 * Row counts are summed across every non-bookkeeping table rather than a specific one, so this stays
 * valid whether or not any seeders are registered (db-standalone registers none; auth does).
 */
final class DatabaseSeederTest extends IntegrationTestCase
{
    public function testRunPopulatesRegisteredSeedersAndTruncateClearsThem(): void
    {
        // Arrange
        DatabaseSeeder::truncate();
        $registered = new ReflectionProperty(DatabaseSeeder::class, 'seeders')->getValue();

        // Act
        DatabaseSeeder::run();
        $afterRun = $this->totalNonBookkeepingRows();
        DatabaseSeeder::truncate();
        $afterTruncate = $this->totalNonBookkeepingRows();

        // Assert
        if (empty($registered)) $this->assertSame(0, $afterRun);
        else $this->assertGreaterThan(0, $afterRun);
        $this->assertSame(0, $afterTruncate);
    }

    private function totalNonBookkeepingRows(): int
    {
        $tables = DB::query(
            "SELECT LOWER(table_name) AS table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND LOWER(table_name) NOT IN ('migrations', 'scheduler_runs')"
        );

        $total = 0;
        foreach ($tables as $row) $total += DB::count(FROM: $row['table_name']);

        return $total;
    }

    public function testTruncatePreservesBookkeepingTables(): void
    {
        // Arrange
        $migrationsBefore = DB::count(FROM: 'migrations');

        // Act
        DatabaseSeeder::truncate();

        // Assert
        $this->assertGreaterThan(0, $migrationsBefore);
        $this->assertSame($migrationsBefore, DB::count(FROM: 'migrations'));
    }
}
