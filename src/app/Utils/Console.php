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
        'blue' => "\x1b[34m",
        'gray' => "\x1b[90m",
        'bold' => "\x1b[1m",
        'dim' => "\x1b[2m",
    ];

    public static function box(string $title): void
    {
        $pad = self::pad();
        $title = self::fit($title, self::BOX_WIDTH - self::LEFT_PADDING);
        $spaces = str_repeat(' ', self::BOX_WIDTH - self::LEFT_PADDING - mb_strlen($title));
        self::line();
        self::out($pad . '╭' . str_repeat('─', self::BOX_WIDTH) . '╮');
        self::out($pad . '│' . $pad . self::styled($title, 'bold') . $spaces . '│');
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

    public static function out(string $message, ?string $color = null): void
    {
        echo ($color !== null ? $color . $message . self::ansi('reset') : $message) . "\n";
    }

    private static function ansi(string $name): string
    {
        return self::$ansi[$name];
    }

    public static function styled(string $message, string ...$styles): string
    {
        if (empty($styles)) return $message;
        return implode('', array_map(fn($s) => self::ansi($s), $styles)) . $message . self::ansi('reset');
    }

    public static function divider(): void
    {
        self::line();
        self::out(self::pad() . str_repeat('─', 16), self::ansi('dim'));
        self::line();
    }

    public static function answer(string $question, string $value): void
    {
        self::out($question . ': ' . self::styled($value, 'cyan'));
    }

    public static function success(string $message, bool $bold = false): void
    {
        self::prefixed('✓', 'green', $message, bold: $bold);
    }

    private static function prefixed(string $symbol, string $color, string $message, bool $bold = false, bool $dim = false): void
    {
        self::out(self::pad() . self::ansi($color) . $symbol . self::ansi('reset') . ' ' . ($bold ? self::styled($message, 'bold') : ($dim ? self::styled($message, 'dim') : $message)));
    }

    public static function error(string $message, bool $bold = false): void
    {
        self::prefixed('✕', 'red', $message, bold: $bold);
    }

    public static function warn(string $message, bool $bold = false): void
    {
        self::prefixed('⚠', 'yellow', $message, bold: $bold);
    }

    public static function info(string $message): void
    {
        self::prefixed('◌', 'cyan', $message, dim: true);
    }

    public static function task(string $message): void
    {
        self::out(self::pad() . $message);
    }

    public static function item(string $message, bool $dim = false): void
    {
        self::out(self::pad() . self::ansi('cyan') . '•' . self::ansi('reset') . ' ' . ($dim ? self::styled($message, 'dim') : $message));
    }
}
