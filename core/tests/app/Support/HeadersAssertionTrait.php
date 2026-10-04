<?php

declare(strict_types=1);

namespace tests\Support;

/**
 * Shared by tests that assert on sent header content.
 * Requires Xdebug (CLI doesn't track headers_list() without it); degrades to a skipped test rather than a false pass.
 */
trait HeadersAssertionTrait
{
    private function headersContain(string $needle): bool
    {
        if (!function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('xdebug_get_headers() not available');
        }

        foreach (xdebug_get_headers() as $header) if (str_contains($header, $needle)) return true;
        return false;
    }
}
