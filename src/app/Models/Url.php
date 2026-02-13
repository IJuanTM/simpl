<?php

declare(strict_types=1);

namespace app\Models;

use app\Utils\Log;

/**
 * URL helper
 *
 * Provides helpers to build URLs for assets and application routes. Caches the
 * computed base URL and root directory for the lifetime of the request.
 */
class Url
{
    private static ?string $baseUrl = null;
    private static ?string $rootDir = null;

    /**
     * Generate a public URL for an asset and append a version query based on
     * the file modification time to aid caching.
     *
     * @param string $subUrl Path relative to public/ (e.g. 'css/main.css')
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

        // Ensure we always get two parts when exploding on fragment (#)
        [$url, $fragment] = explode('#', $url . '#', 2);
        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . filemtime($filePath) . ($fragment ? "#$fragment" : '');
    }

    /**
     * Build a full URL for the given subpath based on the application's base URL.
     *
     * @param string $subUrl
     *
     * @return string
     */
    public static function to(string $subUrl = ''): string
    {
        return self::baseUrl() . '/' . ltrim($subUrl, '/');
    }

    /**
     * Compute and cache the application's base URL using server globals.
     *
     * @return string
     */
    private static function baseUrl(): string
    {
        if (!self::$baseUrl) {
            self::$rootDir = rtrim(BASEDIR, '/');

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

            self::$baseUrl = "$protocol://$host$scriptDir";
        }

        return self::$baseUrl;
    }
}
