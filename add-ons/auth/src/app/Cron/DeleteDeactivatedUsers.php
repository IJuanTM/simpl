<?php

declare(strict_types=1);

namespace app\Cron;

use app\Database\DB;
use app\Enums\UserStatus;
use app\Utils\Console;

class DeleteDeactivatedUsers
{
    public static function run(): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . INACTIVE_USER_DELETION_AFTER . ' days'));

        // Only auto-deactivated (unverified) accounts are purged here. Admin soft-deletes
        // (status 'deleted') are left untouched and are managed manually from the admin panel.
        $where = [
            'status' => UserStatus::DEACTIVATED->value,
            'inactive_since' => ['<', $cutoff]
        ];

        $pending = DB::count(FROM: 'users', WHERE: $where);

        if ($pending > 0) {
            DB::delete('users', $where);
            Console::info("Deleted $pending deactivated user" . ($pending !== 1 ? 's' : ''));
        } else Console::info("No users pending deletion");

        Console::line();
    }
}
