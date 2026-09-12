<?php

declare(strict_types=1);

namespace app\Utils;

/**
 * Best-effort User-Agent parsing for the trusted-devices list. Not a full UA database - covers
 * the common desktop/mobile OSes and browsers well enough to label a remembered device.
 */
final class UserAgentParser
{
    /**
     * @param string|null $userAgent
     *
     * @return array{type: string, os: string, browser: ?string}
     */
    public static function describe(?string $userAgent): array
    {
        $ua = $userAgent ?? '';

        return [
            'type' => self::detectType($ua),
            'os' => self::detectOs($ua),
            'browser' => self::detectBrowser($ua),
        ];
    }

    /**
     * @param string $ua
     *
     * @return string
     */
    private static function detectType(string $ua): string
    {
        return match (true) {
            $ua === '' => 'Unknown',
            (bool)preg_match('/iPad|Tablet/i', $ua) => 'Tablet',
            (bool)preg_match('/Mobi|iPhone|Android/i', $ua) => 'Mobile',
            default => 'Desktop',
        };
    }

    /**
     * @param string $ua
     *
     * @return string
     */
    private static function detectOs(string $ua): string
    {
        return match (true) {
            $ua === '' => 'Unknown',
            (bool)preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown',
        };
    }

    /**
     * @param string $ua
     *
     * @return string|null
     */
    private static function detectBrowser(string $ua): ?string
    {
        return match (true) {
            $ua === '' => null,
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'CriOS/'), str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };
    }
}
