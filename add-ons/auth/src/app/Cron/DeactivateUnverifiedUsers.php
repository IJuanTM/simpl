<?php

declare(strict_types=1);

namespace app\Cron;

use app\Cron\Traits\CronReport;
use app\Database\DB;
use app\Enums\TokenType;
use app\Enums\UserStatus;
use app\Utils\Console;

class DeactivateUnverifiedUsers
{
    use CronReport;

    /**
     * Deactivates ACTIVE users whose verification token has outlived the configured window.
     *
     * @return void
     */
    public static function run(): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . INACTIVE_USER_CONFIG['unverified_deactivation_after_days'] . ' days'));

        // A user with no verification token is already verified, so only join-matched rows qualify.
        $users = DB::select(
            SELECT: ['users.id', 'users.email'],
            FROM: 'users',
            JOIN: ['id', ['tokens', 'user_id']],
            WHERE: [
                'users.status' => UserStatus::ACTIVE->value,
                'tokens.type' => TokenType::VERIFICATION->value,
                'tokens.created' => ['<', $cutoff]
            ]
        );

        $deactivated = 0;

        foreach ($users as $user) {
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

        self::report($deactivated, 'Deactivated', 'unverified user', 'No unverified users to deactivate');
    }
}
