<?php

declare(strict_types=1);

namespace tests\Utils;

use app\Utils\Log;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LogTest extends TestCase
{
    public function testErrorWritesToTheErrorLogFile(): void
    {
        Log::error('something broke');

        $this->assertStringContainsString('ERROR: something broke', $this->lastLine('error'));
    }

    private function lastLine(string $level): string
    {
        $lines = explode("\n\n", trim(file_get_contents(BASEDIR . "/logs/$level.log")));
        return end($lines);
    }

    public function testWarningWritesToTheWarningLogFile(): void
    {
        Log::warning('careful now');

        $this->assertStringContainsString('WARNING: careful now', $this->lastLine('warning'));
    }

    public function testInfoWritesToTheInfoLogFile(): void
    {
        Log::info('fyi');

        $this->assertStringContainsString('INFO: fyi', $this->lastLine('info'));
    }

    public function testDebugWritesToTheDebugLogFile(): void
    {
        Log::debug('trace this');

        $this->assertStringContainsString('DEBUG: trace this', $this->lastLine('debug'));
    }

    public function testMessageIsPrefixedWithATimestamp(): void
    {
        Log::info('hi');

        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+ [+-]\d{2}:\d{2}\]/', $this->lastLine('info'));
    }

    public function testContextValuesAreInterpolatedIntoPlaceholders(): void
    {
        Log::error('user {id} failed', ['id' => 42]);

        $this->assertStringContainsString('user 42 failed', $this->lastLine('error'));
    }

    public function testContextIsAppendedAsJson(): void
    {
        Log::error('failed', ['id' => 42, 'reason' => 'timeout']);

        $line = $this->lastLine('error');
        $this->assertStringContainsString('Context:', $line);
        $this->assertStringContainsString('"id":42', $line);
        $this->assertStringContainsString('"reason":"timeout"', $line);
    }

    public function testNoContextMeansNoContextLine(): void
    {
        Log::error('failed');

        $this->assertStringNotContainsString('Context:', $this->lastLine('error'));
    }

    public function testIncludesAStackTraceByDefault(): void
    {
        Log::error('failed');

        $this->assertStringContainsString('Stack trace:', $this->lastLine('error'));
    }

    public function testDebugCanSkipTheStackTrace(): void
    {
        Log::debug('failed', [], false);

        $this->assertStringNotContainsString('Stack trace:', $this->lastLine('debug'));
    }

    public function testInterpolateOnlySubstitutesScalarAndStringableValues(): void
    {
        $method = new ReflectionMethod(Log::class, 'interpolate');

        $result = $method->invoke(null, 'a={a} b={b} c={c}', ['a' => 1, 'b' => [1, 2], 'c' => null]);

        $this->assertSame('a=1 b={b} c=', $result);
    }

    public function testInterpolateWithNoContextReturnsTheMessageUnchanged(): void
    {
        $method = new ReflectionMethod(Log::class, 'interpolate');

        $this->assertSame('hello {name}', $method->invoke(null, 'hello {name}', []));
    }

    protected function tearDown(): void
    {
        foreach (['error', 'warning', 'info', 'debug'] as $level) {
            $file = BASEDIR . "/logs/$level.log";
            if (is_file($file)) unlink($file);
        }
    }
}
