<?php

declare(strict_types=1);

namespace app\Utils;

use app\Enums\LogLevel;
use JsonException;

/**
 * Writes leveled log entries (error/warning/info/debug) to per-level files in src/logs.
 */
class Log
{
    use EnsuresDirectory;

    /**
     * Log an error-level message.
     *
     * @param string $message Message template
     * @param array  $context Context values for interpolation
     *
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Write a log entry to the appropriate level file, optionally including a trace.
     *
     * @param LogLevel $level Log severity
     * @param string   $message Message template
     * @param array    $context Context values for interpolation
     * @param bool     $includeTrace Whether to include a filtered stack trace
     *
     * @return void
     */
    private static function log(LogLevel $level, string $message, array $context = [], bool $includeTrace = true): void
    {
        $dir = BASEDIR . '/logs';
        self::ensureDirectory($dir);

        $timestamp = date('Y-m-d H:i:s.v P');
        $interpolated = self::interpolate($message, $context);
        $logLine = sprintf('[%s] %s: %s', $timestamp, strtoupper($level->value), $interpolated);

        if ($includeTrace) {
            $trace = array_filter(
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
                static fn($e) => !in_array($e['function'], ['log', 'error', 'warning', 'info', 'debug'], true) && basename($e['file'] ?? '') !== 'index.php'
            );

            if ($trace) {
                $logLine .= PHP_EOL . 'Stack trace:';
                foreach (array_values($trace) as $i => $e) {
                    $logLine .= sprintf(
                        PHP_EOL . '#%d %s(%d): %s%s%s()',
                        $i,
                        $e['file'] ?? '[internal]',
                        $e['line'] ?? 0,
                        $e['class'] ?? '',
                        $e['type'] ?? '',
                        $e['function']
                    );
                }
            }
        }

        if ($context) {
            try {
                $logLine .= PHP_EOL . 'Context: ' . json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch (JsonException $e) {
                $logLine .= PHP_EOL . 'Context: [JSON encoding failed: ' . $e->getMessage() . ']';
            }
        }

        file_put_contents("$dir/$level->value.log", $logLine . PHP_EOL . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Interpolate context values into a message template.
     *
     * @param string $message Message template with {placeholders}
     * @param array  $context Context data to insert
     *
     * @return string
     */
    private static function interpolate(string $message, array $context): string
    {
        if (!$context) return $message;

        $replace = [];

        // Strip newlines/control chars so a context value can't inject a fake extra log line.
        foreach ($context as $key => $val) if ($val === null || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) $replace['{' . $key . '}'] = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$val);

        return strtr($message, $replace);
    }

    /**
     * Logs a warning for a missing named asset (view part/component, SVG icon, ...) and returns the fallback text to show for it.
     * The fallback is visible in DEV, hidden inside an HTML comment otherwise.
     * Shared by every "not found" caller so that DEV/comment behavior can't drift between them.
     *
     * @param string $what The kind of asset that's missing, e.g. "Part", "Component", "SVG"
     * @param string $name The name that was looked up
     *
     * @return string
     */
    public static function missingAsset(string $what, string $name): string
    {
        $message = "$what \"$name\" not found";
        self::warning($message);
        return DEV ? $message : "<!-- $message -->";
    }

    /**
     * Log a warning-level message.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Log an info-level message.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(LogLevel::INFO, $message, $context);
    }

    /**
     * Log a debug-level message, optionally including a stack trace.
     *
     * @param string $message
     * @param array  $context
     * @param bool   $includeTrace
     *
     * @return void
     */
    public static function debug(string $message, array $context = [], bool $includeTrace = true): void
    {
        self::log(LogLevel::DEBUG, $message, $context, $includeTrace);
    }
}
