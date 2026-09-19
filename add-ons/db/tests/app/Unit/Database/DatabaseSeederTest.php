<?php

declare(strict_types=1);

namespace tests\Unit\Database;

use app\Database\DatabaseSeeder;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * run()/truncate() both call DB::useDatabase() as their first step, which attempts a real connection.
 * That is not safely callable in a unit test. Only register()'s accumulation is pure.
 */
final class DatabaseSeederTest extends TestCase
{
    private array $seedersBeforeTest;

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

    // register() accumulates into a static property shared by every test in the process (including Integration tests, which run DatabaseSeeder::run() for real), so a fake entry left behind here would poison them.

    protected function setUp(): void
    {
        $this->seedersBeforeTest = (new ReflectionProperty(DatabaseSeeder::class, 'seeders'))->getValue();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(DatabaseSeeder::class, 'seeders'))->setValue(null, $this->seedersBeforeTest);
    }
}
