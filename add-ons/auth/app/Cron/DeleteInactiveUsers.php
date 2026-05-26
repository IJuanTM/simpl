<?php

declare(strict_types=1);

namespace app\Cron;

use app\Database\DB;
use app\Utils\Console;

class DeleteInactiveUsers
{
    public static function run(): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime(INACTIVE_USER_DELETION_AFTER));

        // Get all users pending deletion
        $pending = DB::count(
            'users',
            [
                'deleted_at' => ['<', $cutoff]
            ]
        );

        if ($pending > 0) {
            // Delete users who have been inactive for more than 1 week
            DB::delete(
                'users',
                [
                    'deleted_at' => ['<', $cutoff]
                ]
            );

            Console::info("Deleted $pending inactive user" . ($pending !== 1 ? 's' : ''));
        } else Console::info("No users pending deletion");

        Console::line();
    }
}
