<?php

declare(strict_types=1);

namespace app\Models;

use app\Utils\Log;

/**
 * Provides utility methods for generating and managing URLs within the application.
 * Includes functionalities for resolving URLs to public files, constructing normalized paths,
 * and retrieving the application's base URL.
 */
class Url
{
    private static ?string $baseUrl = null;
    private static ?string $rootDir = null;

    /**
     * Generates a URL to a file within the public directory, appending a version query parameter based on the file's modification time.
     * Logs a warning if the specified file does not exist.
     *
     * @param string $subUrl The sub-path to the file, optionally starting with a slash.
     *
     * @return string The URL to the file with a version query parameter for cache busting, or the base URL if the file is missing.
     */
    public static function file(string $subUrl = ''): string
    {
        $url = self::to($subUrl);

        if (!self::$rootDir) self::baseUrl();

        $filePath = self::$rootDir . '/public/' . ltrim($subUrl, '/');

        if (!is_file($filePath)) {
            Log::warning("Could not find file \"$filePath\"");
            return $url;
        }

        // Ensure we always get two parts when exploding on fragment (#)
        [$url, $fragment] = explode('#', $url . '#', 2);
        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . filemtime($filePath) . ($fragment ? "#$fragment" : '');
    }

    /**
     * Constructs a normalized URL by ensuring it starts with a single forward slash.
     *
     * @param string $subUrl The sub-path to be appended, optionally starting with a slash.
     *
     * @return string The resulting URL starting with a single forward slash.
     */
    public static function to(string $subUrl = ''): string
    {
        return '/' . ltrim($subUrl, '/');
    }

    /**
     * Initializes the base URL for the application if it is not already set.
     * The base URL is constructed using the server's protocol, host, and script directory.
     *
     * @return void
     */
    private static function baseUrl(): void
    {
        if (!self::$baseUrl) {
            self::$rootDir = rtrim(BASEDIR, '/');

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

            self::$baseUrl = "$protocol://$host$scriptDir";
        }
    }

    /**
     * Constructs an absolute URL based on the configured APP_URL. Use for links that leave the
     * application (e.g. emails), where a root-relative path would not resolve.
     *
     * @param string $subUrl The sub-path to be appended, optionally starting with a slash.
     *
     * @return string The absolute URL.
     */
    public static function absolute(string $subUrl = ''): string
    {
        return rtrim(APP_URL, '/') . '/' . ltrim($subUrl, '/');
    }
}
