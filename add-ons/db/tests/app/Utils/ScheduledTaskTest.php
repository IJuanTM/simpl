<?php

declare(strict_types=1);

namespace tests\Utils;

use app\Utils\ScheduledTask;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ScheduledTaskTest extends TestCase
{
    public function testIntervalBuildersProduceExpectedSeconds(): void
    {
        $this->assertSame(120, $this->task()->everyMinutes(2)->intervalSeconds);
        $this->assertSame(3600, $this->task()->hourly()->intervalSeconds);
        $this->assertSame(7200, $this->task()->everyHours(2)->intervalSeconds);
        $this->assertSame(86400, $this->task()->daily()->intervalSeconds);
        $this->assertSame(604800, $this->task()->weekly()->intervalSeconds);
        $this->assertSame(1209600, $this->task()->everyWeeks(2)->intervalSeconds);
    }

    private function task(): ScheduledTask
    {
        return new ScheduledTask('test', static fn() => null);
    }

    public function testSettingAnIntervalClearsAnyCronExpression(): void
    {
        $task = $this->task()->cron('* * * * *');
        $task->hourly();

        $this->assertNull($task->cronExpression);
        $this->assertSame(3600, $task->intervalSeconds);
    }

    public function testSettingACronExpressionClearsAnyInterval(): void
    {
        $task = $this->task()->hourly();
        $task->cron('* * * * *');

        $this->assertNull($task->intervalSeconds);
        $this->assertSame('* * * * *', $task->cronExpression);
    }

    public function testIsDueWithoutALastRunIsAlwaysDue(): void
    {
        $this->assertTrue($this->task()->daily()->isDue(null));
    }

    public function testIsDueWithAnIntervalComparesAgainstLastRun(): void
    {
        $task = $this->task()->everyMinutes(1);

        $this->assertFalse($task->isDue(date('Y-m-d H:i:s', time() - 30)));
        $this->assertTrue($task->isDue(date('Y-m-d H:i:s', time() - 90)));
    }

    public function testIsDueWithACronExpressionThatAlwaysMatches(): void
    {
        $this->assertTrue($this->task()->cron('* * * * *')->isDue(null));
    }

    public function testIsDueWithAMalformedCronExpressionIsNeverDue(): void
    {
        $this->assertFalse($this->task()->cron('* * *')->isDue(null));
    }

    public function testMatchesCronFieldWildcard(): void
    {
        $this->assertTrue($this->matchesCronField(37, '*'));
    }

    private function matchesCronField(int $current, string $field): bool
    {
        $method = new ReflectionMethod(ScheduledTask::class, 'matchesCronField');
        return $method->invoke($this->task(), $current, $field);
    }

    public function testMatchesCronFieldExactValue(): void
    {
        $this->assertTrue($this->matchesCronField(5, '5'));
        $this->assertFalse($this->matchesCronField(6, '5'));
    }

    public function testMatchesCronFieldRange(): void
    {
        $this->assertTrue($this->matchesCronField(10, '9-17'));
        $this->assertTrue($this->matchesCronField(9, '9-17'));
        $this->assertTrue($this->matchesCronField(17, '9-17'));
        $this->assertFalse($this->matchesCronField(18, '9-17'));
        $this->assertFalse($this->matchesCronField(8, '9-17'));
    }

    public function testMatchesCronFieldStepFromZero(): void
    {
        $this->assertTrue($this->matchesCronField(0, '*/15'));
        $this->assertTrue($this->matchesCronField(15, '*/15'));
        $this->assertTrue($this->matchesCronField(30, '*/15'));
        $this->assertFalse($this->matchesCronField(20, '*/15'));
    }

    public function testMatchesCronFieldStepFromAnOffset(): void
    {
        $this->assertTrue($this->matchesCronField(5, '5/10'));
        $this->assertTrue($this->matchesCronField(15, '5/10'));
        $this->assertFalse($this->matchesCronField(4, '5/10'));
        $this->assertFalse($this->matchesCronField(10, '5/10'));
    }

    public function testMatchesCronFieldList(): void
    {
        $this->assertTrue($this->matchesCronField(1, '1,3,5'));
        $this->assertTrue($this->matchesCronField(3, '1,3,5'));
        $this->assertFalse($this->matchesCronField(2, '1,3,5'));
    }

    public function testMatchesCronFieldListContainingARange(): void
    {
        $this->assertTrue($this->matchesCronField(1, '1,3,5-10'));
        $this->assertTrue($this->matchesCronField(3, '1,3,5-10'));
        $this->assertTrue($this->matchesCronField(5, '1,3,5-10'));
        $this->assertTrue($this->matchesCronField(7, '1,3,5-10'));
        $this->assertTrue($this->matchesCronField(10, '1,3,5-10'));
        $this->assertFalse($this->matchesCronField(2, '1,3,5-10'));
        $this->assertFalse($this->matchesCronField(4, '1,3,5-10'));
        $this->assertFalse($this->matchesCronField(11, '1,3,5-10'));
    }

    public function testMatchesCronFieldRangeWithStep(): void
    {
        $this->assertTrue($this->matchesCronField(1, '1-10/2'));
        $this->assertTrue($this->matchesCronField(3, '1-10/2'));
        $this->assertTrue($this->matchesCronField(9, '1-10/2'));
        $this->assertFalse($this->matchesCronField(2, '1-10/2'));
        $this->assertFalse($this->matchesCronField(10, '1-10/2'));
        $this->assertFalse($this->matchesCronField(11, '1-10/2'));
    }

    public function testIsCronDueOrsDayOfMonthAndWeekdayWhenBothAreRestricted(): void
    {
        $today = (int)date('j');
        $otherDay = $today === 1 ? 2 : 1;
        $todayWeekday = (int)date('w');
        $otherWeekday = ($todayWeekday + 1) % 7;

        $this->assertTrue($this->task()->cron("* * $today * *")->isDue(null));
        $this->assertTrue($this->task()->cron("* * * * $todayWeekday")->isDue(null));
        $this->assertTrue($this->task()->cron("* * $today * $otherWeekday")->isDue(null));
        $this->assertFalse($this->task()->cron("* * $otherDay * $otherWeekday")->isDue(null));
    }

    public function testIsCronDueAndsDayOfMonthAndWeekdayWhenOneIsWildcard(): void
    {
        $otherDay = (int)date('j') === 1 ? 2 : 1;

        $this->assertFalse($this->task()->cron("* * $otherDay * *")->isDue(null));
    }
}
