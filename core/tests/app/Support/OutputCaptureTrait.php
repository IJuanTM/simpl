<?php

declare(strict_types=1);

namespace tests\Support;

use Throwable;

/**
 * Shared by tests that assert on echoed/printed output.
 */
trait OutputCaptureTrait
{
    private function captured(callable $fn): string
    {
        ob_start();

        try {
            $fn();
            return ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
