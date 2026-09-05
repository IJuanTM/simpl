<?php

declare(strict_types=1);

namespace tests\Database;

use app\Database\DatabaseMigrator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * run()/rollback() both call DB::useDatabase()/DB::raw() as their first step, which attempts a real connection.
 * That is not safely callable in a unit test - it would hang or exit via DB::handleError().
 * Only register()'s accumulation and the private name() helper are pure.
 */
final class DatabaseMigratorTest extends TestCase
{
    public function testRegisterAppendsToTheMigrationList(): void
    {
        // Arrange
        $before = count($this->migrations());
        $marker = 'tests\\Database\\FakeMigration' . uniqid('', true);

        // Act
        DatabaseMigrator::register($marker);

        // Assert
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
        // Arrange
        $method = new ReflectionMethod(DatabaseMigrator::class, 'name');

        // Act + Assert
        $this->assertSame('app\\Database\\Migrations\\Tables\\CreateUsersTable', $method->invoke(null, 'app\\Database\\Migrations\\Tables\\CreateUsersTable'));
    }

    public function testNameStripsALeadingBackslash(): void
    {
        // Arrange
        $method = new ReflectionMethod(DatabaseMigrator::class, 'name');

        // Act + Assert
        $this->assertSame('app\\Database\\Migrations\\Tables\\CreateUsersTable', $method->invoke(null, '\\app\\Database\\Migrations\\Tables\\CreateUsersTable'));
    }

    public function testNameWithNoNamespaceReturnsTheClassNameUnchanged(): void
    {
        // Arrange
        $method = new ReflectionMethod(DatabaseMigrator::class, 'name');

        // Act + Assert
        $this->assertSame('PlainClass', $method->invoke(null, 'PlainClass'));
    }
}
