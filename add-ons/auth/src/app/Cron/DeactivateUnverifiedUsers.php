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
        // A user with no verification token is already verified, so only join-matched rows qualify.
        $users = DB::select(
            SELECT: ['users.id', 'users.email', 'tokens.created AS token_created'],
            FROM: 'users',
            JOIN: ['id', ['tokens', 'user_id']],
            WHERE: [
                'users.status' => UserStatus::ACTIVE->value,
                'tokens.type' => TokenType::VERIFICATION->value
            ]
        );

        $cutoff = date('Y-m-d H:i:s', strtotime('-' . INACTIVE_USER_CONFIG['unverified_deactivation_after_days'] . ' days'));
        $deactivated = 0;

        foreach ($users as $user) {
            if ($user['token_created'] >= $cutoff) continue;

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
