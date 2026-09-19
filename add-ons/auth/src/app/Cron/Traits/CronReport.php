<?php

declare(strict_types=1);

namespace app\Cron\Traits;

use app\Utils\Console;

/**
 * Shared "did N things, or nothing" closing line for the auth add-on's cron jobs.
 */
trait CronReport
{
    /**
     * Prints "<verb> <count> <noun>[s]" when anything happened, otherwise $nothingMessage, then a blank line.
     *
     * @param int    $count
     * @param string $verb           Past-tense action, e.g. 'Deactivated'
     * @param string $noun           Singular noun the count applies to, e.g. 'unverified user'
     * @param string $nothingMessage Line to print when $count is 0
     *
     * @return void
     */
    protected static function report(int $count, string $verb, string $noun, string $nothingMessage): void
    {
        Console::info($count > 0
            ? "$verb $count $noun" . ($count !== 1 ? 's' : '')
            : $nothingMessage);

        Console::line();
    }
}
