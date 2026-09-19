<?php

declare(strict_types=1);

namespace tests\Integration\Migrations;

use app\Database\DatabaseMigrator;
use tests\Integration\IntegrationTestCase;

/**
 * setUpBeforeClass() on IntegrationTestCase already applies every registered migration once per process, so run() here proves idempotency.
 * rollback()/run() proves the reverse path, restoring the schema before returning so later tests still see a fully-migrated database.
 */
final class DatabaseMigratorTest extends IntegrationTestCase
{
    public function testRunReturnsNothingWhenEverythingIsAlreadyApplied(): void
    {
        // Act
        $applied = DatabaseMigrator::run();

        // Assert
        $this->assertSame([], $applied);
    }

    public function testRollbackReversesTheLastBatchAndRunReappliesIt(): void
    {
        // Act
        $rolledBack = DatabaseMigrator::rollback();
        $reapplied = DatabaseMigrator::run();

        // Assert
        $this->assertNotEmpty($rolledBack);
        $this->assertSame(array_reverse($rolledBack), $reapplied);
    }
}
