<?php

declare(strict_types=1);

namespace tests\Database;

use app\Database\DatabaseMigrator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * run()/rollback() both call DB::useDatabase()/DB::raw() as their first step, which attempts
 * a real connection - not safely callable in a unit test (would hang or exit via
 * DB::handleError()). Only register()'s accumulation and the private name() helper are pure.
 */
class DatabaseMigratorTest extends TestCase
{
    public function testRegisterAppendsToTheMigrationList(): void
    {
        $before = count($this->migrations());
        $marker = 'tests\\Database\\FakeMigration' . uniqid();

        DatabaseMigrator::register($marker);

        $after = $this->migrations();
        $this->assertCount($before + 1, $after);
        $this->assertSame($marker, end($after));
    }

    private function migrations(): array
    {
        return (new ReflectionProperty(DatabaseMigrator::class, 'migrations'))->getValue();
    }

    public function testNameKeepsTheFullyQualifiedNameToAvoidBasenameCollisionsAcrossAddOns(): void
    {
        $method = new ReflectionMethod(DatabaseMigrator::class, 'name');

        $this->assertSame('app\\Database\\Migrations\\Tables\\CreateUsersTable', $method->invoke(null, 'app\\Database\\Migrations\\Tables\\CreateUsersTable'));
    }

    public function testNameStripsALeadingBackslash(): void
    {
        $method = new ReflectionMethod(DatabaseMigrator::class, 'name');

        $this->assertSame('app\\Database\\Migrations\\Tables\\CreateUsersTable', $method->invoke(null, '\\app\\Database\\Migrations\\Tables\\CreateUsersTable'));
    }

    public function testNameWithNoNamespaceReturnsTheClassNameUnchanged(): void
    {
        $method = new ReflectionMethod(DatabaseMigrator::class, 'name');

        $this->assertSame('PlainClass', $method->invoke(null, 'PlainClass'));
    }
}
