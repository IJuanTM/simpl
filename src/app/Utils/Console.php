<?php

declare(strict_types=1);

namespace app\Utils;

class Console
{
    private const int LEFT_PADDING = 2;
    private const int BOX_WIDTH = 62;
    private static array $ansi = [
        'reset' => "\x1b[0m",
        'green' => "\x1b[32m",
        'yellow' => "\x1b[33m",
        'red' => "\x1b[31m",
        'cyan' => "\x1b[36m",
        'bold' => "\x1b[1m",
        'dim' => "\x1b[2m",
    ];

    public static function box(string $title): void
    {
        $pad = self::pad();
        self::line();
        self::out($pad . "╭" . str_repeat('─', self::BOX_WIDTH) . "╮");

        $title = self::fit($title, self::BOX_WIDTH - self::LEFT_PADDING);

        self::out($pad . "│" . $pad . self::text($title, bold: true) . str_repeat(' ', self::BOX_WIDTH - self::LEFT_PADDING - mb_strlen($title)) . "│");
        self::out($pad . "╰" . str_repeat('─', self::BOX_WIDTH) . "╯");
        self::line();
    }

    private static function pad(): string
    {
        return str_repeat(' ', self::LEFT_PADDING);
    }

    public static function line(string $message = ''): void
    {
        echo $message . "\n";
    }

    public static function out(string $message, ?string $color = null): void
    {
        echo ($color ?? self::ansi('reset')) . $message . self::ansi('reset') . "\n";
    }

    private static function ansi(string $name): string
    {
        return self::$ansi[$name];
    }

    private static function fit(string $text, int $width): string
    {
        return mb_strlen($text) <= $width ? $text : mb_substr($text, 0, max(0, $width - 3)) . '...';
    }

    private static function text(string $message, bool $bold = false, bool $dim = false): string
    {
        $style = $bold ? self::ansi('bold') : ($dim ? self::ansi('dim') : '');

        return $style === '' ? $message : $style . $message . self::ansi('reset');
    }

    public static function divider(): void
    {
        self::line();
        self::out(self::pad() . str_repeat('─', 16), self::ansi('dim'));
        self::line();
    }

    public static function success(string $message, bool $bold = false): void
    {
        self::prefixed('✓', self::ansi('green'), $message, $bold);
    }

    private static function prefixed(string $symbol, string $color, string $message, bool $bold = false, bool $dim = false): void
    {
        self::out(self::pad() . $color . $symbol . self::ansi('reset') . ' ' . self::text($message, $bold, $dim));
    }

    public static function error(string $message, bool $bold = false): void
    {
        self::prefixed('✕', self::ansi('red'), $message, $bold);
    }

    public static function warn(string $message, bool $bold = false): void
    {
        self::prefixed('⚠', self::ansi('yellow'), $message, $bold);
    }

    public static function info(string $message, bool $bold = false): void
    {
        self::prefixed('ℹ', self::ansi('cyan'), $message, $bold, true);
    }

    public static function task(string $message, bool $bold = false): void
    {
        self::out(self::pad() . self::text($message, $bold));
    }
}
