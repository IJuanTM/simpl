<?php

declare(strict_types=1);

namespace tests\Utils;

use app\Enums\Ansi;
use app\Utils\Console;
use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    public function testBoxDrawsATopAndBottomBorderAroundTheTitle(): void
    {
        // Act
        $output = $this->captured(static fn() => Console::box('My Title'));

        // Assert
        $this->assertStringContainsString('╭', $output);
        $this->assertStringContainsString('╰', $output);
        $this->assertStringContainsString('My Title', $output);
    }

    private function captured(callable $fn): string
    {
        ob_start();
        $fn();
        return ob_get_clean();
    }

    public function testBoxTruncatesATitleLongerThanTheBoxWidth(): void
    {
        // Arrange
        $longTitle = str_repeat('x', 100);

        // Act
        $output = $this->captured(static fn() => Console::box($longTitle));

        // Assert
        $this->assertStringContainsString('...', $output);
        $this->assertStringNotContainsString($longTitle, $output);
    }

    public function testLineOutputsTheMessageWithATrailingNewline(): void
    {
        // Act + Assert
        $this->assertSame("hello\n", $this->captured(static fn() => Console::line('hello')));
    }

    public function testLineWithNoArgumentOutputsJustANewline(): void
    {
        // Act + Assert
        $this->assertSame("\n", $this->captured(static fn() => Console::line()));
    }

    public function testOutWithNoColorOutputsThePlainMessage(): void
    {
        // Act + Assert
        $this->assertSame("hello\n", $this->captured(static fn() => Console::out('hello')));
    }

    public function testOutWithAColorWrapsTheMessageInAnsiCodes(): void
    {
        // Act
        $output = $this->captured(static fn() => Console::out('hello', Ansi::GREEN));

        // Assert
        $this->assertStringContainsString("\x1b[32m", $output);
        $this->assertStringContainsString('hello', $output);
        $this->assertStringContainsString("\x1b[0m", $output);
    }

    public function testStyledWrapsTheMessageWithoutANewline(): void
    {
        // Act + Assert
        $this->assertSame("\x1b[1mhello\x1b[0m", Console::styled('hello', Ansi::BOLD));
    }

    public function testDividerOutputsADimLine(): void
    {
        // Act
        $output = $this->captured(static fn() => Console::divider());

        // Assert
        $this->assertStringContainsString('─', $output);
    }

    public function testSuccessPrefixesWithACheckmark(): void
    {
        // Act + Assert
        $this->assertStringContainsString('✓', $this->captured(static fn() => Console::success('done')));
    }

    public function testErrorPrefixesWithAnX(): void
    {
        // Act + Assert
        $this->assertStringContainsString('✕', $this->captured(static fn() => Console::error('bad')));
    }

    public function testWarnPrefixesWithAWarningSymbol(): void
    {
        // Act + Assert
        $this->assertStringContainsString('⚠', $this->captured(static fn() => Console::warn('careful')));
    }

    public function testInfoPrefixesWithADot(): void
    {
        // Act + Assert
        $this->assertStringContainsString('◌', $this->captured(static fn() => Console::info('note')));
    }

    public function testTaskOutputsThePaddedMessage(): void
    {
        // Act + Assert
        $this->assertSame("  a task\n", $this->captured(static fn() => Console::task('a task')));
    }

    public function testItemPrefixesWithABullet(): void
    {
        // Act + Assert
        $this->assertStringContainsString('•', $this->captured(static fn() => Console::item('thing')));
    }
}
