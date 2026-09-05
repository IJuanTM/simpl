<?php

declare(strict_types=1);

namespace tests\Utils;

use app\Utils\Log;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class LogTest extends TestCase
{
    public function testErrorWritesToTheErrorLogFile(): void
    {
        // Act
        Log::error('something broke');

        // Assert
        $this->assertStringContainsString('ERROR: something broke', $this->lastLine('error'));
    }

    private function lastLine(string $level): string
    {
        $lines = explode("\n\n", trim(file_get_contents(BASEDIR . "/logs/$level.log")));
        return end($lines);
    }

    public function testContextValuesAreInterpolatedIntoPlaceholders(): void
    {
        // Act
        Log::error('user {id} failed', ['id' => 42]);

        // Assert
        $this->assertStringContainsString('user 42 failed', $this->lastLine('error'));
    }

    public function testContextIsAppendedAsJson(): void
    {
        // Act
        Log::error('failed', ['id' => 42, 'reason' => 'timeout']);

        // Assert
        $line = $this->lastLine('error');
        $this->assertStringContainsString('Context:', $line);
        $this->assertStringContainsString('"id":42', $line);
        $this->assertStringContainsString('"reason":"timeout"', $line);
    }

    public function testNoContextMeansNoContextLine(): void
    {
        // Act
        Log::error('failed');

        // Assert
        $this->assertStringNotContainsString('Context:', $this->lastLine('error'));
    }

    public function testIncludesAStackTraceByDefault(): void
    {
        // Act
        Log::error('failed');

        // Assert
        $this->assertStringContainsString('Stack trace:', $this->lastLine('error'));
    }

    public function testMessageIsPrefixedWithATimestamp(): void
    {
        // Act
        Log::info('hi');

        // Assert
        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+ [+-]\d{2}:\d{2}\]/', $this->lastLine('info'));
    }

    public function testInterpolateOnlySubstitutesScalarAndStringableValues(): void
    {
        // Arrange
        $method = new ReflectionMethod(Log::class, 'interpolate');

        // Act
        $result = $method->invoke(null, 'a={a} b={b} c={c}', ['a' => 1, 'b' => [1, 2], 'c' => null]);

        // Assert
        $this->assertSame('a=1 b={b} c=', $result);
    }

    public function testInterpolateWithNoContextReturnsTheMessageUnchanged(): void
    {
        // Arrange
        $method = new ReflectionMethod(Log::class, 'interpolate');

        // Act + Assert
        $this->assertSame('hello {name}', $method->invoke(null, 'hello {name}', []));
    }

    public function testWarningWritesToTheWarningLogFile(): void
    {
        // Act
        Log::warning('careful now');

        // Assert
        $this->assertStringContainsString('WARNING: careful now', $this->lastLine('warning'));
    }

    public function testInfoWritesToTheInfoLogFile(): void
    {
        // Act
        Log::info('fyi');

        // Assert
        $this->assertStringContainsString('INFO: fyi', $this->lastLine('info'));
    }

    public function testDebugWritesToTheDebugLogFile(): void
    {
        // Act
        Log::debug('trace this');

        // Assert
        $this->assertStringContainsString('DEBUG: trace this', $this->lastLine('debug'));
    }

    public function testDebugCanSkipTheStackTrace(): void
    {
        // Act
        Log::debug('failed', [], false);

        // Assert
        $this->assertStringNotContainsString('Stack trace:', $this->lastLine('debug'));
    }

    protected function tearDown(): void
    {
        foreach (['error', 'warning', 'info', 'debug'] as $level) {
            $file = BASEDIR . "/logs/$level.log";
            if (is_file($file)) unlink($file);
        }
    }
}
