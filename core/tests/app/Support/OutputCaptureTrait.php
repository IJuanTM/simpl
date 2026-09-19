<?php

declare(strict_types=1);

namespace tests\Support;

/**
 * Shared by tests that assert on echoed/printed output.
 */
trait OutputCaptureTrait
{
    private function captured(callable $fn): string
    {
        ob_start();
        $fn();
        return ob_get_clean();
    }
}
