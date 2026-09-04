<?php

declare(strict_types=1);

namespace app\Utils;

use app\Enums\Ansi;

class Console
{
    private const int LEFT_PADDING = 2;
    private const int BOX_WIDTH = 62;

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

    private static function pad(): string
    {
        return str_repeat(' ', self::LEFT_PADDING);
    }

    private static function fit(string $text, int $width): string
    {
        return mb_strlen($text) <= $width ? $text : mb_substr($text, 0, max(0, $width - 3)) . '...';
    }

    public static function line(string $message = ''): void
    {
        echo $message . "\n";
    }

    public static function out(string $message, ?Ansi $color = null): void
    {
        echo ($color !== null ? Ansi::wrap($message, $color) : $message) . "\n";
    }

    public static function styled(string $message, Ansi ...$styles): string
    {
        return Ansi::wrap($message, ...$styles);
    }

    public static function divider(): void
    {
        self::line();
        self::out(self::pad() . str_repeat('─', 16), Ansi::DIM);
    }

    public static function success(string $message, bool $bold = false): void
    {
        self::prefixed('✓', Ansi::GREEN, $message, bold: $bold);
    }

    private static function prefixed(string $symbol, Ansi $color, string $message, bool $bold = false, bool $dim = false): void
    {
        self::out(self::pad() . Ansi::wrap($symbol, $color) . ' ' . ($bold ? self::styled($message, Ansi::BOLD) : ($dim ? self::styled($message, Ansi::DIM) : $message)));
    }

    /**
     * Prints $message as an error and exits with status 1.
     */
    public static function fail(string $message): never
    {
        self::line();
        self::error($message);
        self::line();
        exit(1);
    }

    public static function error(string $message, bool $bold = false): void
    {
        self::prefixed('✕', Ansi::RED, $message, bold: $bold);
    }

    public static function warn(string $message, bool $bold = false): void
    {
        self::prefixed('⚠', Ansi::YELLOW, $message, bold: $bold);
    }

    public static function info(string $message): void
    {
        self::prefixed('◌', Ansi::CYAN, $message, dim: true);
    }

    public static function task(string $message): void
    {
        self::out(self::pad() . $message);
    }

    public static function item(string $message, bool $dim = false): void
    {
        self::out(self::pad() . Ansi::wrap('•', Ansi::CYAN) . ' ' . ($dim ? self::styled($message, Ansi::DIM) : $message));
    }
}
