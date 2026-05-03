<?php

declare(strict_types=1);

namespace app\Database\Seeders;

use app\Database\DB;

class UsersSeeder
{
    public static function run(): void
    {
        DB::insert('users', [
            'email' => 'admin@example.com',
            'password' => password_hash('admin', PASSWORD_BCRYPT, ['cost' => 12]),
        ]);

        DB::insert('users', [
            'email' => 'user@example.com',
            'password' => password_hash('user', PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
    }
}
