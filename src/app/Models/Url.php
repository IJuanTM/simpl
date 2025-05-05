<?php

namespace app\Models;

use app\Controllers\LogController;

class Url
{
    private static ?string $baseUrl = null;
    private static ?string $rootDir = null;

    /**
     * Method to generate a full URL for a file. Adds a version query string to the URL based on the file's last modification time.
     *
     * @param string $subUrl
     *
     * @return string
     */
    public static function file(string $subUrl = ''): string
    {
        // Generate the URL for the file
        $url = self::to($subUrl);

        if (!self::$rootDir) self::baseUrl();

        // Construct the full file path
        $filePath = self::$rootDir . '/public/' . ltrim($subUrl, '/');

        // Check if the file exists
        if (!is_file($filePath)) {
            // Log the error if the environment is development
            if (DEV) LogController::log("Could not find file \"$filePath\"", 'debug');

            return $url;
        }

        // Split the URL into the base URL and fragment
        [$url, $fragment] = explode('#', $url . '#', 2);

        // Return the URL with the version query string based on the file's last modification time
        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . filemtime($filePath) . ($fragment ? "#$fragment" : '');
    }

    /**
     * Method to generate a full URL for the given sub URL.
     *
     * @param string $subUrl
     *
     * @return string
     */
    public static function to(string $subUrl = ''): string
    {
        // Return the base URL with the sub URL appended
        return self::baseUrl() . '/' . ltrim($subUrl, '/');
    }

    /**
     * Method for generating the base URL for the application.
     *
     * @return string
     */
    private static function baseUrl(): string
    {
        if (!self::$baseUrl) {
            // Set the root directory to the BASEDIR constant
            self::$rootDir = rtrim(BASEDIR, '/');

            // Construct the base URL using the server variables
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

            // Set the base URL
            self::$baseUrl = "$protocol://$host$scriptDir";
        }

        // Return the base URL
        return self::$baseUrl;
    }
}
