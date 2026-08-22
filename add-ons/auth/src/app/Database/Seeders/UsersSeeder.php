<?php

declare(strict_types=1);

namespace app\Database\Seeders;

use app\Database\DB;
use app\Enums\UserStatus;
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

        DB::insert(
            'users',
            [
                'username' => 'User',
                'email' => 'user@example.com',
                'password' => password_hash('user', PASSWORD_CONFIG['hash_algo'], PASSWORD_CONFIG['hash_options']),
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
                    'password' => password_hash('user', PASSWORD_CONFIG['hash_algo'], PASSWORD_CONFIG['hash_options']),
                    'must_change_password' => (random_int(1, 100) <= 10) ? 1 : 0,
                    'last_login' => random_int(0, 1) ? self::randomDate($createdAt, 'now') : null,
                    'created_at' => $createdAt,
                    'last_update' => $createdAt,
                    'status' => $deletedAt ? UserStatus::DELETED->value : UserStatus::ACTIVE->value,
                    'inactive_since' => $deletedAt
                ]
            );
        }
    }

    /**
     * Generates a random date between the given start and end dates.
     *
     * @param string $start The starting date in a valid date format.
     * @param string $end The ending date in a valid date format.
     *
     * @return string A random date between the start and end dates, formatted as 'Y-m-d H:i:s'.
     * @throws RandomException
     */
    private static function randomDate(string $start, string $end): string
    {
        return date('Y-m-d H:i:s', random_int(strtotime($start), strtotime($end)));
    }

    /**
     * Determines if the given date may be deleted based on a random condition.
     *
     * @param string $createdAt The creation date to evaluate.
     *
     * @return string|null A randomly generated date string if the condition is met, or null otherwise.
     * @throws RandomException
     */
    private static function maybeDeleted(string $createdAt): ?string
    {
        if (random_int(1, 100) > 10) return null;
        return self::randomDate($createdAt, 'now');
    }
}
