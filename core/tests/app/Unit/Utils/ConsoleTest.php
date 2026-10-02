<?php

declare(strict_types=1);

namespace tests\Unit\Utils;

use app\Enums\Ansi;
use app\Utils\Console;
use PHPUnit\Framework\TestCase;
use tests\Support\OutputCaptureTrait;

final class ConsoleTest extends TestCase
{
    use OutputCaptureTrait;

    public function testBoxDrawsATopAndBottomBorderAroundTheTitle(): void
    {
        // Act
        $output = $this->captured(static fn() => Console::box('My Title'));

        // Assert
        $this->assertStringContainsString('╭', $output);
        $this->assertStringContainsString('╰', $output);
        $this->assertStringContainsString('My Title', $output);
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

    public function testBoxIgnoresAnsiCodesWhenPaddingTheTitle(): void
    {
        // Arrange
        $strip = static fn(string $s): string => preg_replace('/\e\[[0-9;]*m/', '', $s);

        // Act
        $plain = $this->captured(static fn() => Console::box('My Title'));
        $styled = $this->captured(static fn() => Console::box(Console::styled('My Title', Ansi::CYAN)));

        // Assert
        $this->assertSame($strip($plain), $strip($styled));
    }

    public function testTitleBoxShowsTheTitleAndDetail(): void
    {
        // Act
        $output = preg_replace('/\e\[[0-9;]*m/', '', $this->captured(static fn() => Console::titleBox('Migrate database', 'simpl')));

        // Assert
        $this->assertStringContainsString('Simpl - Migrate database (simpl)', $output);
    }

    public function testPluralAddsAnSOnlyWhenTheCountIsNotOne(): void
    {
        // Act + Assert
        $this->assertSame("\x1b[1m1\x1b[0m file", Console::plural(1, 'file'));
        $this->assertSame("\x1b[1m2\x1b[0m files", Console::plural(2, 'file'));
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
