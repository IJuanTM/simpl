<?php

declare(strict_types=1);

namespace app\Utils;

class Console
{
    private const string RESET = "\x1b[0m";
    private const string GREEN = "\x1b[32m";
    private const string YELLOW = "\x1b[33m";
    private const string RED = "\x1b[31m";
    private const string CYAN = "\x1b[36m";
    private const string BOLD = "\x1b[1m";
    private const string DIM = "\x1b[2m";

    public static function box(string $title): void
    {
        self::line();
        self::out("  ╭" . str_repeat('─', 62) . "╮");
        self::out("  │  " . self::BOLD . $title . self::RESET . str_repeat(' ', 60 - mb_strlen($title)) . "│");
        self::out("  ╰" . str_repeat('─', 62) . "╯");
    }

    public static function line(string $message = ''): void
    {
        echo $message . "\n";
    }

    public static function out(string $message, string $color = self::RESET): void
    {
        echo $color . $message . self::RESET . "\n";
    }

    public static function divider(): void
    {
        self::line();
        self::out("  " . str_repeat('─', 16), self::DIM);
        self::line();
    }

    public static function success(string $message): void
    {
        self::out("  " . self::GREEN . "✓" . self::RESET . " " . $message);
    }

    public static function error(string $message): void
    {
        self::out("  " . self::RED . "✗" . self::RESET . " " . $message);
    }

    public static function warn(string $message): void
    {
        self::out("  " . self::YELLOW . "⚠" . self::RESET . " " . $message);
    }

    public static function info(string $message): void
    {
        self::out("  " . self::CYAN . "•" . self::RESET . " " . self::DIM . $message . self::RESET);
    }

    public static function task(string $message): void
    {
        self::out("  " . self::BOLD . $message . self::RESET);
    }

    public static function successBold(string $message): void
    {
        self::out("  " . self::GREEN . "✓" . self::RESET . " " . self::BOLD . self::GREEN . $message . self::RESET);
    }
}
