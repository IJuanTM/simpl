<?php

declare(strict_types=1);

namespace app\Database\Seeders;

use app\Controllers\TwoFactorController;
use app\Database\DB;
use app\Enums\TwoFactorMethod;
use app\Enums\UserStatus;
use app\Utils\Crypto;
use OTPHP\TOTP;
use Random\RandomException;

class UsersSeeder
{
    private static array $users = [
        ['Alex', 'Taylor'],
        ['Jordan', 'Smith'],
        ['Casey', 'Brown'],
        ['Taylor', 'Johnson'],
        ['Sam', 'Lee'],
        ['Riley', 'Walker'],
        ['Morgan', 'Harris'],
        ['Jamie', 'Clark'],
        ['Cameron', 'Martin'],
        ['Drew', 'Lewis'],
        ['Sydney', 'Adams'],
        ['Avery', 'Scott'],
        ['Reese', 'Thompson'],
        ['Parker', 'Reed'],
        ['Quinn', 'Brooks'],
        ['Blake', 'Bennett'],
        ['Devon', 'Carter'],
        ['Finley', 'Davis'],
        ['Gray', 'Evans'],
        ['Hunter', 'Foster'],
        ['Indigo', 'Green'],
        ['Jay', 'Hill'],
        ['Kelly', 'Jackson'],
        ['Logan', 'King'],
        ['Marley', 'Miller'],
        ['Nat', 'Nelson'],
        ['Owen', 'Oliver'],
        ['Sage', 'Parker'],
        ['Skyler', 'Roberts'],
        ['Tatum', 'Robinson'],
        ['Tyler', 'Thomas'],
        ['Vale', 'Turner'],
        ['Whitney', 'White'],
        ['Xander', 'Williams'],
        ['Zane', 'Young']
    ];

    /**
     * Inserts the admin and demo user accounts, then a batch of randomly generated users (some
     * soft-deleted) for exercising the admin panel's listing/filtering.
     *
     * @throws RandomException
     */
    public static function run(): void
    {
        DB::insert(
            'users',
            [
                'username' => 'Admin',
                'email' => 'admin@example.com',
                'password' => password_hash('admin', PASSWORD_CONFIG['hash_algo'], PASSWORD_CONFIG['hash_options']),
                'status' => UserStatus::ACTIVE->value
            ]
        );

        $userPass = password_hash('user', PASSWORD_CONFIG['hash_algo'], PASSWORD_CONFIG['hash_options']);

        DB::insert(
            'users',
            [
                'username' => 'User',
                'email' => 'user@example.com',
                'password' => $userPass,
                'status' => UserStatus::ACTIVE->value
            ]
        );

        foreach (self::$users as [$first, $last]) {
            $username = strtolower($first . '.' . $last);
            $createdAt = self::randomDate('-365 days', '-1 days');
            $deletedAt = self::maybeDeleted($createdAt);

            DB::insert(
                'users',
                [
                    'username' => $username,
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $username . '@example.com',
                    'password' => $userPass,
                    'must_change_password' => (random_int(1, 100) <= 10) ? 1 : 0,
                    'last_login' => random_int(0, 1) ? self::randomDate($createdAt, 'now') : null,
                    'created_at' => $createdAt,
                    'last_update' => $createdAt,
                    'status' => $deletedAt ? UserStatus::DELETED->value : UserStatus::ACTIVE->value,
                    'inactive_since' => $deletedAt
                ]
            );

            if (!$deletedAt) self::maybeSeedTwoFactor((int)DB::lastInsertId());
        }
    }

    /**
     * Generates a random date between the given start and end dates, formatted as 'Y-m-d H:i:s'.
     *
     * @throws RandomException
     */
    private static function randomDate(string $start, string $end): string
    {
        return date('Y-m-d H:i:s', random_int(strtotime($start), strtotime($end)));
    }

    /**
     * Randomly decides whether the given creation date should be treated as soft-deleted, returning the deletion date if so.
     *
     * @throws RandomException
     */
    private static function maybeDeleted(string $createdAt): ?string
    {
        if (random_int(1, 100) > 10) return null;
        return self::randomDate($createdAt, 'now');
    }

    /**
     * Randomly enrols a seeded user into two-factor authentication, mirroring the DB state
     * TwoFactorController itself would produce so the admin panel has realistic data to browse.
     * Passkeys are skipped, they require real WebAuthn credential material that can't be faked.
     *
     * @throws RandomException
     */
    private static function maybeSeedTwoFactor(int $userId): void
    {
        if (random_int(1, 100) > 40) return;

        TwoFactorController::enable($userId);
        if (random_int(1, 100) > 50) return;

        $secret = TOTP::generate(secretSize: 20)->getSecret();

        DB::update(
            UPDATE: 'user_two_factor',
            SET: [
                'totp_secret' => Crypto::encrypt($secret),
                'totp_enabled' => 1,
                'totp_confirmed_at' => date('Y-m-d H:i:s'),
                'primary_method' => TwoFactorMethod::TOTP->value
            ],
            WHERE: ['user_id' => $userId]
        );
    }
}
