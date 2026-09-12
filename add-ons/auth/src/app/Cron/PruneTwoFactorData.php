<?php

declare(strict_types=1);

namespace app\Cron;

use app\Cron\Traits\CronReport;
use app\Database\DB;
use app\Enums\TokenType;

class PruneTwoFactorData
{
    use CronReport;

    /**
     * Deletes expired trusted-device rows, spent login email codes, and already-redeemed recovery codes.
     *
     * @return void
     */
    public static function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $expiredDevices = ['expires' => ['<', $now]];
        $expiredEmailCodes = ['type' => TokenType::TWO_FACTOR_EMAIL->value, 'expires' => ['<', $now]];
        $usedRecoveryCodes = ['used_at' => ['!=', null]];

        $pending = DB::count(FROM: 'trusted_devices', WHERE: $expiredDevices)
            + DB::count(FROM: 'tokens', WHERE: $expiredEmailCodes)
            + DB::count(FROM: 'two_factor_recovery_codes', WHERE: $usedRecoveryCodes);

        if ($pending > 0) {
            DB::delete(FROM: 'trusted_devices', WHERE: $expiredDevices);
            DB::delete(FROM: 'tokens', WHERE: $expiredEmailCodes);
            DB::delete(FROM: 'two_factor_recovery_codes', WHERE: $usedRecoveryCodes);
        }

        self::report($pending, 'Pruned', 'stale two-factor row', 'No stale two-factor data to prune');
    }
}
