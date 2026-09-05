<?php

declare(strict_types=1);

namespace app\Models;

use app\Utils\Log;

/**
 * URL helpers: root-relative path normalization, cache-busted public-file URLs, and absolute URLs off APP_URL.
 * Every $subUrl argument tolerates an optional leading slash.
 */
class Url
{
    private static ?string $rootDir = null;

    /**
     * Generates a URL to a file within the public directory, appending a version query parameter based on the file's modification time.
     * Logs a warning and returns the unversioned URL if the file does not exist.
     *
     * @param string $subUrl
     *
     * @return string
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

        [$path, $fragment] = str_contains($url, '#') ? explode('#', $url, 2) : [$url, ''];
        return $path . (str_contains($path, '?') ? '&' : '?') . 'v=' . filemtime($filePath) . ($fragment !== '' ? "#$fragment" : '');
    }

    /**
     * Normalizes $subUrl to start with exactly one forward slash.
     *
     * @param string $subUrl
     *
     * @return string
     */
    public static function to(string $subUrl = ''): string
    {
        return '/' . ltrim($subUrl, '/');
    }

    /**
     * Initializes $rootDir, the filesystem root used to resolve public files, if not already set.
     *
     * @return void
     */
    private static function baseUrl(): void
    {
        self::$rootDir = rtrim(BASEDIR, '/');
    }

    /**
     * Constructs an absolute URL based on the configured APP_URL.
     * Use for links that leave the application (e.g. emails), where a root-relative path would not resolve.
     *
     * @param string $subUrl
     *
     * @return string
     */
    public static function absolute(string $subUrl = ''): string
    {
        return rtrim(APP_URL, '/') . '/' . ltrim($subUrl, '/');
    }
}
