<?php

declare(strict_types=1);

namespace app\Utils;

/**
 * Pads a callback's execution time up to a fixed floor, so how long it took doesn't
 * leak which branch it took (e.g. a login check that returns quickly for a nonexistent
 * user and slowly for a wrong password). Call returnEarly() from inside the callback
 * for outcomes that don't need the floor, such as a successful check.
 */
class Timebox
{
    private bool $returnEarly = false;

    /**
     * Runs $callback, then sleeps out the remainder of $microseconds unless
     * returnEarly() was called from inside it.
     *
     * @param callable(self): mixed $callback
     * @param int                   $microseconds Minimum total duration for the call
     *
     * @return mixed The callback's return value
     */
    final public function call(callable $callback, int $microseconds): mixed
    {
        $this->returnEarly = false;
        $start = hrtime(true);

        try {
            return $callback($this);
        } finally {
            if (!$this->returnEarly) {
                $remaining = $microseconds - (int)((hrtime(true) - $start) / 1000);
                if ($remaining > 0) usleep($remaining);
            }
        }
    }

    /**
     * Call from inside the callback to skip the minimum-duration floor for this call.
     *
     * @return void
     */
    final public function returnEarly(): void
    {
        $this->returnEarly = true;
    }
}
