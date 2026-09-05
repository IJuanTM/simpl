<?php

declare(strict_types=1);

namespace tests\Utils;

use app\Enums\Ansi;
use app\Utils\Console;
use PHPUnit\Framework\TestCase;

class ConsoleTest extends TestCase
{
    public function testLineOutputsTheMessageWithATrailingNewline(): void
    {
        $this->assertSame("hello\n", $this->captured(static fn() => Console::line('hello')));
    }

    private function captured(callable $fn): string
    {
        ob_start();
        $fn();
        return ob_get_clean();
    }

    public function testLineWithNoArgumentOutputsJustANewline(): void
    {
        $this->assertSame("\n", $this->captured(static fn() => Console::line()));
    }

    public function testOutWithNoColorOutputsThePlainMessage(): void
    {
        $this->assertSame("hello\n", $this->captured(static fn() => Console::out('hello')));
    }

    public function testOutWithAColorWrapsTheMessageInAnsiCodes(): void
    {
        $output = $this->captured(static fn() => Console::out('hello', Ansi::GREEN));

        $this->assertStringContainsString("\x1b[32m", $output);
        $this->assertStringContainsString('hello', $output);
        $this->assertStringContainsString("\x1b[0m", $output);
    }

    public function testStyledWrapsTheMessageWithoutANewline(): void
    {
        $this->assertSame("\x1b[1mhello\x1b[0m", Console::styled('hello', Ansi::BOLD));
    }

    public function testSuccessPrefixesWithACheckmark(): void
    {
        $this->assertStringContainsString('✓', $this->captured(static fn() => Console::success('done')));
    }

    public function testErrorPrefixesWithAnX(): void
    {
        $this->assertStringContainsString('✕', $this->captured(static fn() => Console::error('bad')));
    }

    public function testWarnPrefixesWithAWarningSymbol(): void
    {
        $this->assertStringContainsString('⚠', $this->captured(static fn() => Console::warn('careful')));
    }

    public function testInfoPrefixesWithADot(): void
    {
        $this->assertStringContainsString('◌', $this->captured(static fn() => Console::info('note')));
    }

    public function testTaskOutputsThePaddedMessage(): void
    {
        $this->assertSame("  a task\n", $this->captured(static fn() => Console::task('a task')));
    }

    public function testItemPrefixesWithABullet(): void
    {
        $this->assertStringContainsString('•', $this->captured(static fn() => Console::item('thing')));
    }

    public function testBoxDrawsATopAndBottomBorderAroundTheTitle(): void
    {
        $output = $this->captured(static fn() => Console::box('My Title'));

        $this->assertStringContainsString('╭', $output);
        $this->assertStringContainsString('╰', $output);
        $this->assertStringContainsString('My Title', $output);
    }

    public function testBoxTruncatesATitleLongerThanTheBoxWidth(): void
    {
        $longTitle = str_repeat('x', 100);

        $output = $this->captured(static fn() => Console::box($longTitle));

        $this->assertStringContainsString('...', $output);
        $this->assertStringNotContainsString($longTitle, $output);
    }

    public function testDividerOutputsADimLine(): void
    {
        $output = $this->captured(static fn() => Console::divider());

        $this->assertStringContainsString('─', $output);
    }
}
