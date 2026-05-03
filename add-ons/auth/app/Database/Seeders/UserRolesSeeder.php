<?php

declare(strict_types=1);

namespace app\Database\Seeders;

use app\Database\DB;

class UserRolesSeeder
{
    public static function run(): void
    {
        $admin = DB::single('id', 'users', ['email' => 'admin@example.com']);
        $user = DB::single('id', 'users', ['email' => 'user@example.com']);
        $adminRole = DB::single('id', 'roles', ['name' => 'admin']);
        $userRole = DB::single('id', 'roles', ['name' => 'user']);

        DB::insert('user_roles', ['user_id' => $admin['id'], 'role_id' => $adminRole['id']]);
        DB::insert('user_roles', ['user_id' => $user['id'], 'role_id' => $userRole['id']]);
    }
}
