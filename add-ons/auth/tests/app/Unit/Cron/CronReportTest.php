<?php

declare(strict_types=1);

namespace tests\Unit\Cron;

use app\Cron\Traits\CronReport;
use PHPUnit\Framework\TestCase;

final class CronReportHost
{
    use CronReport;

    public static function run(int $count, string $verb, string $noun, string $nothingMessage): void
    {
        self::report($count, $verb, $noun, $nothingMessage);
    }
}

final class CronReportTest extends TestCase
{
    public function testZeroCountPrintsTheNothingMessage(): void
    {
        // Act
        $output = $this->captured(static fn() => CronReportHost::run(0, 'Deactivated', 'unverified user', 'No unverified users to deactivate.'));

        // Assert
        $this->assertStringContainsString('No unverified users to deactivate.', $output);
    }

    private function captured(callable $fn): string
    {
        ob_start();
        $fn();
        return ob_get_clean();
    }

    public function testSingularCountDoesNotPluralizeTheNoun(): void
    {
        // Act
        $output = $this->captured(static fn() => CronReportHost::run(1, 'Deactivated', 'unverified user', 'No unverified users to deactivate.'));

        // Assert
        $this->assertStringContainsString('Deactivated 1 unverified user', $output);
        $this->assertStringNotContainsString('unverified users', $output);
    }

    public function testPluralCountPluralizesTheNoun(): void
    {
        // Act
        $output = $this->captured(static fn() => CronReportHost::run(3, 'Deactivated', 'unverified user', 'No unverified users to deactivate.'));

        // Assert
        $this->assertStringContainsString('Deactivated 3 unverified users', $output);
    }
}
