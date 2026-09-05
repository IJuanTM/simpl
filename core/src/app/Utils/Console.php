<?php

declare(strict_types=1);

namespace app\Utils;

use app\Enums\Ansi;

/**
 * Formatted CLI output helpers (colored lines, boxes, dividers) shared by the framework's console scripts.
 */
class Console
{
    private const int LEFT_PADDING = 2;
    private const int BOX_WIDTH = 62;

    /**
     * Prints a bordered box containing $title.
     *
     * @param string $title
     *
     * @return void
     */
    public static function box(string $title): void
    {
        $pad = self::pad();
        $title = self::fit($title, self::BOX_WIDTH - self::LEFT_PADDING);
        $spaces = str_repeat(' ', self::BOX_WIDTH - self::LEFT_PADDING - mb_strlen($title));
        self::line();
        self::out($pad . '╭' . str_repeat('─', self::BOX_WIDTH) . '╮');
        self::out($pad . '│' . $pad . self::styled($title, Ansi::BOLD) . $spaces . '│');
        self::out($pad . '╰' . str_repeat('─', self::BOX_WIDTH) . '╯');
    }

    /**
     * Left padding string used to indent console output.
     *
     * @return string
     */
    private static function pad(): string
    {
        return str_repeat(' ', self::LEFT_PADDING);
    }

    /**
     * Truncates $text to $width characters, appending "..." if it was cut off.
     *
     * @param string $text
     * @param int    $width
     *
     * @return string
     */
    private static function fit(string $text, int $width): string
    {
        return mb_strlen($text) <= $width ? $text : mb_substr($text, 0, max(0, $width - 3)) . '...';
    }

    /**
     * Prints $message followed by a newline (a blank line when omitted).
     *
     * @param string $message
     *
     * @return void
     */
    public static function line(string $message = ''): void
    {
        echo $message . "\n";
    }

    /**
     * Prints $message followed by a newline, optionally wrapped in an ANSI color.
     *
     * @param string    $message
     * @param Ansi|null $color
     *
     * @return void
     */
    public static function out(string $message, ?Ansi $color = null): void
    {
        echo ($color !== null ? Ansi::wrap($message, $color) : $message) . "\n";
    }

    /**
     * Wraps $message in the given ANSI style sequences.
     *
     * @param string $message
     * @param Ansi   ...$styles
     *
     * @return string
     */
    public static function styled(string $message, Ansi ...$styles): string
    {
        return Ansi::wrap($message, ...$styles);
    }

    /**
     * Prints a blank line followed by a short dim horizontal divider.
     *
     * @return void
     */
    public static function divider(): void
    {
        self::line();
        self::out(self::pad() . str_repeat('─', 16), Ansi::DIM);
    }

    /**
     * Prints $message as a success line, prefixed with a green checkmark.
     *
     * @param string $message
     * @param bool   $bold
     *
     * @return void
     */
    public static function success(string $message, bool $bold = false): void
    {
        self::prefixed('✓', Ansi::GREEN, $message, bold: $bold);
    }

    /**
     * Prints $message indented and prefixed with $symbol in $color, optionally bold or dim.
     *
     * @param string $symbol
     * @param Ansi   $color
     * @param string $message
     * @param bool   $bold
     * @param bool   $dim
     *
     * @return void
     */
    private static function prefixed(string $symbol, Ansi $color, string $message, bool $bold = false, bool $dim = false): void
    {
        self::out(self::pad() . Ansi::wrap($symbol, $color) . ' ' . ($bold ? self::styled($message, Ansi::BOLD) : ($dim ? self::styled($message, Ansi::DIM) : $message)));
    }

    /**
     * Prints $message as an error and exits with status 1.
     *
     * @param string $message
     *
     * @return never
     */
    public static function fail(string $message): never
    {
        self::line();
        self::error($message);
        self::line();
        exit(1);
    }

    /**
     * Prints $message as an error line, prefixed with a red X.
     *
     * @param string $message
     * @param bool   $bold
     *
     * @return void
     */
    public static function error(string $message, bool $bold = false): void
    {
        self::prefixed('✕', Ansi::RED, $message, bold: $bold);
    }

    /**
     * Prints $message as a warning line, prefixed with a yellow warning sign.
     *
     * @param string $message
     * @param bool   $bold
     *
     * @return void
     */
    public static function warn(string $message, bool $bold = false): void
    {
        self::prefixed('⚠', Ansi::YELLOW, $message, bold: $bold);
    }

    /**
     * Prints $message as a dim info line, prefixed with a cyan circle.
     *
     * @param string $message
     *
     * @return void
     */
    public static function info(string $message): void
    {
        self::prefixed('◌', Ansi::CYAN, $message, dim: true);
    }

    /**
     * Prints $message as an indented task line.
     *
     * @param string $message
     *
     * @return void
     */
    public static function task(string $message): void
    {
        self::out(self::pad() . $message);
    }

    /**
     * Prints $message as an indented bullet list item, optionally dimmed.
     *
     * @param string $message
     * @param bool   $dim
     *
     * @return void
     */
    public static function item(string $message, bool $dim = false): void
    {
        self::out(self::pad() . Ansi::wrap('•', Ansi::CYAN) . ' ' . ($dim ? self::styled($message, Ansi::DIM) : $message));
    }
}
