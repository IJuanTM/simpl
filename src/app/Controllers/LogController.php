<?php

namespace app\Controllers;

use app\Enums\LogType;
use RuntimeException;

/**
 * The LogController class is used for logging different types of messages.
 */
class LogController
{
    /**
     * This method is for logging different types of messages.
     *
     * @param string $message
     * @param LogType $type
     */
    public static function log(string $message, LogType $type): void
    {
        // Check if the log directory exists, if not create it.
        $dir = BASEDIR . '/app/Logs';

        // Ensure the directory exists, create it if it doesn't.
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));

        // Open the log file in append mode.
        $file = fopen("$dir/$type->value", 'ab');

        // Filter out the log method and index.php from the stack trace.
        $traceLog = array_filter(debug_backtrace(), static fn($entry) => !(($entry['function'] ?? '') === 'log' || basename($entry['file'] ?? '') === 'index.php'));

        // Format the log message.
        $logMessage = '[' . date('Y-M-d H:i:s e') . "] $message" . PHP_EOL;
        if ($traceLog) {
            $logMessage .= 'Stack trace:' . PHP_EOL;

            foreach ($traceLog as $index => $entry) $logMessage .= "#$index {$entry['file']}({$entry['line']}): " . ($entry['class'] ?? '') . ($entry['type'] ?? '') . $entry['function'] . '()' . PHP_EOL;
        }

        // Write the log message to the log file and close it.
        fwrite($file, $logMessage . PHP_EOL);
        fclose($file);
    }
}
