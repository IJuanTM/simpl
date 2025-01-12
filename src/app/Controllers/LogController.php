<?php

namespace app\Controllers;

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
        // Get the Logs directory
        $dir = BASEDIR . '/app/Logs';

        // Check if the Logs directory exists and create it if it doesn't
        if (!is_dir($dir)) mkdir($dir);

        // Open log file or create it if it doesn't exist
        $file = fopen(BASEDIR . "/app/Logs/$type.log", 'a');

        // Get debug backtrace
        $trace = debug_backtrace();

        // Get the caller
        $caller = array_shift($trace);

        // Get the relative path to the file
        $path = str_replace(BASEDIR, '', $caller['file']);

        // Write message to file
        fwrite($file, '[' . date('Y-M-d H:i:s e') . "] $message ($path on line $caller[line])" . PHP_EOL);

        // Close log file
        fclose($file);
    }
}
