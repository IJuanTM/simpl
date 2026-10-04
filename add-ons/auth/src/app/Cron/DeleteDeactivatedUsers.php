<?php

declare(strict_types=1);

namespace app\Cron;

use app\Cron\Traits\CronReport;
use app\Database\DB;
use app\Enums\UserStatus;

class DeleteDeactivatedUsers
{
    use CronReport;

    /**
     * Deletes auto-deactivated accounts past the configured retention window.
     *
     * @return void
     */
    public static function run(): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . INACTIVE_USER_CONFIG['deletion_after_days'] . ' days'));

        // Only auto-deactivated (unverified) accounts are purged here.
        // Admin soft-deletes (status 'deleted') are left untouched and are managed manually from the admin panel.
        $where = [
            'status' => UserStatus::DEACTIVATED->value,
            'inactive_since' => ['<', $cutoff]
        ];

        $pending = DB::count(FROM: 'users', WHERE: $where);

        if ($pending > 0) DB::delete('users', $where);

        self::report($pending, 'Deleted', 'deactivated user', 'No users pending deletion');
    }
}
