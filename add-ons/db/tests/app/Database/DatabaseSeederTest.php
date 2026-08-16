<?php

declare(strict_types=1);

namespace tests\Database;

use app\Database\DatabaseSeeder;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * run()/truncate() both call DB::useDatabase() as their first step, which attempts a real
 * connection - not safely callable in a unit test. Only register()'s accumulation is pure.
 */
class DatabaseSeederTest extends TestCase
{
    public function testRegisterAppendsToTheSeederList(): void
    {
        $seeders = new ReflectionProperty(DatabaseSeeder::class, 'seeders');
        $before = count($seeders->getValue());
        $marker = 'tests\\Database\\FakeSeeder' . uniqid();

        DatabaseSeeder::register($marker);

        $after = $seeders->getValue();
        $this->assertCount($before + 1, $after);
        $this->assertSame($marker, end($after));
    }
}
