<?php

declare(strict_types=1);

namespace app\Cron;

use app\Database\DB;
use app\Enums\TokenType;
use app\Enums\UserStatus;
use app\Utils\Console;

class DeactivateUnverifiedUsers
{
    public static function run(): void
    {
        $users = DB::select(
            SELECT: '*',
            FROM: 'users',
            WHERE: [
                'status' => UserStatus::ACTIVE->value
            ]
        );

        $deactivated = 0;

        foreach ($users as $user) {
            $token = DB::single(
                SELECT: '*',
                FROM: 'tokens',
                WHERE: [
                    'user_id' => $user['id'],
                    'type' => TokenType::VERIFICATION->value
                ]
            );

            if (!$token || $token['created'] >= date('Y-m-d H:i:s', strtotime('-' . INACTIVE_USER_CONFIG['unverified_deactivation_after_days'] . ' days'))) continue;

            DB::update(
                UPDATE: 'users',
                SET: [
                    'status' => UserStatus::DEACTIVATED->value,
                    'inactive_since' => date('Y-m-d H:i:s')
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
