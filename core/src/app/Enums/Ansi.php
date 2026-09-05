<?php

declare(strict_types=1);

namespace app\Enums;

/**
 * ANSI escape sequences used for console styling.
 */
enum Ansi: string
{
    case RESET = "\x1b[0m";
    case GREEN = "\x1b[32m";
    case YELLOW = "\x1b[33m";
    case RED = "\x1b[31m";
    case CYAN = "\x1b[36m";
    case BLUE = "\x1b[34m";
    case GRAY = "\x1b[90m";
    case BOLD = "\x1b[1m";
    case DIM = "\x1b[2m";

    /**
     * Wraps $message in the given style sequences, followed by a reset code (no-op when $styles is empty).
     *
     * @param string $message
     * @param self   ...$styles
     *
     * @return string
     */
    public static function wrap(string $message, self ...$styles): string
    {
        return empty($styles) ? $message : self::sequence(...$styles) . $message . self::RESET->value;
    }

    /**
     * Concatenates the escape sequences for the given styles into one combined sequence.
     *
     * @param self ...$styles
     *
     * @return string
     */
    public static function sequence(self ...$styles): string
    {
        return implode('', array_map(static fn(self $s): string => $s->value, $styles));
    }
}
