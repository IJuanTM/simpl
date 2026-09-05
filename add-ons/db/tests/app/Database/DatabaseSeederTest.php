<?php

declare(strict_types=1);

namespace tests\Database;

use app\Database\DatabaseSeeder;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * run()/truncate() both call DB::useDatabase() as their first step, which attempts a real connection.
 * That is not safely callable in a unit test. Only register()'s accumulation is pure.
 */
final class DatabaseSeederTest extends TestCase
{
    public function testRegisterAppendsToTheSeederList(): void
    {
        // Arrange
        $seeders = new ReflectionProperty(DatabaseSeeder::class, 'seeders');
        $before = count($seeders->getValue());
        $marker = 'tests\\Database\\FakeSeeder' . uniqid('', true);

        // Act
        DatabaseSeeder::register($marker);

        // Assert
        $after = $seeders->getValue();
        $this->assertCount($before + 1, $after);
        $this->assertSame($marker, end($after));
    }
}
