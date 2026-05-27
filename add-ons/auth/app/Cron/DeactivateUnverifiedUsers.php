<?php

declare(strict_types=1);

namespace app\Cron;

use app\Database\DB;
use app\Utils\Console;

class DeactivateUnverifiedUsers
{
    public static function run(): void
    {
        // Get all active users
        $users = DB::select(
            SELECT: '*',
            FROM: 'users',
            WHERE: [
                'is_active' => 1
            ]
        );

        $deactivated = 0;

        foreach ($users as $user) {
            // Check if the user has a verification token that was created within the last 24 hours
            $token = DB::single(
                SELECT: '*',
                FROM: 'tokens',
                WHERE: [
                    'user_id' => $user['id'],
                    'type' => 'verification'
                ]
            );

            if (!$token || $token['created'] >= date('Y-m-d H:i:s', strtotime('-' . UNVERIFIED_USER_DEACTIVATION_AFTER . ' days'))) continue;

            // Deactivate the user
            DB::update(
                UPDATE: 'users',
                SET: [
                    'is_active' => 0,
                    'deleted_at' => date('Y-m-d H:i:s')
                ],
                WHERE: [
                    'id' => $user['id']
                ]
            );

            Console::info("Deactivated user #{$user['id']} ({$user['email']})");
            $deactivated++;
        }

        Console::line();
        if ($deactivated > 0) Console::info("Deactivated $deactivated unverified user" . ($deactivated !== 1 ? 's' : ''));
        else Console::info("No unverified users to deactivate");
        Console::line();
    }
}
