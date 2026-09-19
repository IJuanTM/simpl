<?php

declare(strict_types=1);

namespace tests\Integration;

use app\Database\DatabaseMigrator;
use app\Database\DB;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Base class for tests that hit a real MariaDB schema. Migrations run once per process in setUpBeforeClass().
 * Each test then runs inside its own transaction, rolled back in tearDown(), so tests never see one another's writes.
 * Refuses to run against the real dev database, so a misconfigured .env can't point this at live data.
 */
abstract class IntegrationTestCase extends TestCase
{
    private static bool $migrated = false;

    public static function setUpBeforeClass(): void
    {
        if (DB_NAME === 'simpl') self::fail("Integration tests must not run against the real 'simpl' database.");

        if (self::$migrated) return;

        DatabaseMigrator::run();
        self::$migrated = true;
    }

    protected function setUp(): void
    {
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        // DDL and TRUNCATE implicitly commit in MySQL/MariaDB, which silently ends the transaction
        // started in setUp(); rolling back a connection with none open would throw.
        if ($this->pdo()->inTransaction()) DB::rollback();
    }

    private function pdo(): PDO
    {
        return (new ReflectionProperty(DB::class, 'pdo'))->getValue();
    }
}
