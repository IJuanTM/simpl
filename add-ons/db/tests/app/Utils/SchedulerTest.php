<?php

declare(strict_types=1);

namespace tests\Utils;

use app\Utils\Scheduler;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * run() calls DB::single() unconditionally as the first thing inside its task loop, for every
 * registered task regardless of isDue() - only safely testable here with zero tasks registered,
 * so $tasks is reset in setUp() to stay independent of whatever other tests may have registered.
 */
class SchedulerTest extends TestCase
{
    private ReflectionProperty $tasksProp;

    public function testTaskRegistersAScheduledTaskUnderTheGivenName(): void
    {
        $task = Scheduler::task('my-task', static fn() => null);

        $this->assertSame('my-task', $task->name);
        $this->assertCount(1, $this->tasksProp->getValue());
        $this->assertSame($task, $this->tasksProp->getValue()[0]);
    }

    public function testTaskReturnsTheTaskForFluentChaining(): void
    {
        $task = Scheduler::task('my-task', static fn() => null)->daily();

        $this->assertSame(86400, $task->intervalSeconds);
    }

    public function testRunWithNoRegisteredTasksReportsNoneDue(): void
    {
        $output = $this->captured(static fn() => Scheduler::run());

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
        $output = $this->captured(static fn() => Scheduler::run(true));

        $this->assertStringContainsString('[TEST]', $output);
    }

    protected function setUp(): void
    {
        $this->tasksProp = new ReflectionProperty(Scheduler::class, 'tasks');
        $this->tasksProp->setValue(null, []);
    }
}
