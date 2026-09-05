<?php

declare(strict_types=1);

namespace tests\Utils;

use app\Utils\Scheduler;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * run() calls DB::single() unconditionally for every registered task in its loop, regardless of isDue().
 * It is only safely testable with zero tasks registered, so setUp() resets $tasks to stay independent of whatever other tests registered.
 */
final class SchedulerTest extends TestCase
{
    private ReflectionProperty $tasksProp;

    public function testRunWithNoRegisteredTasksReportsNoneDue(): void
    {
        // Act
        $output = $this->captured(static fn() => Scheduler::run());

        // Assert
        $this->assertStringContainsString('No tasks due', $output);
    }

    private function captured(callable $fn): string
    {
        ob_start();
        $fn();
        return ob_get_clean();
    }

    public function testRunPrintsATestLabelInTestMode(): void
    {
        // Act
        $output = $this->captured(static fn() => Scheduler::run(true));

        // Assert
        $this->assertStringContainsString('[TEST]', $output);
    }

    public function testTaskRegistersAScheduledTaskUnderTheGivenName(): void
    {
        // Act
        $task = Scheduler::task('my-task', static fn() => null);

        // Assert
        $this->assertSame('my-task', $task->name);
        $this->assertCount(1, $this->tasksProp->getValue());
        $this->assertSame($task, $this->tasksProp->getValue()[0]);
    }

    public function testTaskReturnsTheTaskForFluentChaining(): void
    {
        // Act
        $task = Scheduler::task('my-task', static fn() => null)->daily();

        // Assert
        $this->assertSame(86400, $task->intervalSeconds);
    }

    protected function setUp(): void
    {
        $this->tasksProp = new ReflectionProperty(Scheduler::class, 'tasks');
        $this->tasksProp->setValue(null, []);
    }
}
