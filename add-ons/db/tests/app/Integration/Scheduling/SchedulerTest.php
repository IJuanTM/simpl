<?php

declare(strict_types=1);

namespace tests\Integration\Scheduling;

use app\Database\DB;
use app\Utils\Scheduler;
use ReflectionProperty;
use RuntimeException;
use tests\Integration\IntegrationTestCase;
use tests\Support\OutputCaptureTrait;

/**
 * Feature\Scheduling\SchedulerTest only covers the zero-tasks path, since run() calls DB::single()
 * unconditionally for every registered task; this covers the due-task path against a real scheduler_runs table.
 */
final class SchedulerTest extends IntegrationTestCase
{
    use OutputCaptureTrait;

    public function testRunExecutesADueTaskAndRecordsItsSuccess(): void
    {
        // Arrange
        $called = false;
        Scheduler::task('integration-due-task', function () use (&$called) {
            $called = true;
        })->everyMinutes();

        // Act
        $output = $this->captured(static fn() => Scheduler::run());

        // Assert
        $this->assertTrue($called);
        $this->assertStringContainsString('Completed in', $output);
        $record = DB::single(SELECT: '*', FROM: 'scheduler_runs', WHERE: ['task_name' => 'integration-due-task']);
        $this->assertNotNull($record);
        $this->assertSame('success', $record['last_status']);
    }

    public function testRunRecordsAFailedTaskWithoutStoppingTheOthers(): void
    {
        // Arrange
        Scheduler::task('integration-failing-task', function () {
            throw new RuntimeException('boom');
        })->everyMinutes();

        // Act
        $failures = Scheduler::run();

        // Assert
        $this->assertSame(1, $failures);
        $record = DB::single(SELECT: '*', FROM: 'scheduler_runs', WHERE: ['task_name' => 'integration-failing-task']);
        $this->assertSame('failed', $record['last_status']);
        $this->assertSame('boom', $record['last_error']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        new ReflectionProperty(Scheduler::class, 'tasks')->setValue(null, []);
    }
}
