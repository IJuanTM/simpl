<?php

namespace app\Controllers;

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
     * @param string $type
     *
     * @return void
     */
    public static function log(string $message, string $type): void
    {
        // Check if the log directory exists, if not create it.
        $dir = BASEDIR . '/app/Logs';

        if (!mkdir($dir) && !is_dir($dir)) throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));

        // Open the log file in append mode.
        $file = fopen("$dir/$type.log", 'ab');

        // Filter out the log method and index.php from the stack trace.
        $traceLog = array_filter(debug_backtrace(), static fn($entry) => !($entry['function'] === 'log' || basename($entry['file'] ?? '') === 'index.php'));

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
