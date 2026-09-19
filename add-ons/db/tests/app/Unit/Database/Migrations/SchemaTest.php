<?php

declare(strict_types=1);

namespace tests\Unit\Database\Migrations;

use app\Database\Migrations\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Only databaseName() is covered here; every other method on Schema calls DB::raw() as its first
 * operation and needs a real database connection.
 */
final class SchemaTest extends TestCase
{
    public function testDatabaseNamePassesAValidNameThrough(): void
    {
        // Act + Assert
        $this->assertSame('my_app_db', $this->call('databaseName', ['my_app_db']));
    }

    private function call(string $method, array $args): mixed
    {
        return new ReflectionMethod(Schema::class, $method)->invoke(null, ...$args);
    }

    public function testDatabaseNameRejectsABacktick(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);

        // Act
        $this->call('databaseName', ['evil`db']);
    }
}
