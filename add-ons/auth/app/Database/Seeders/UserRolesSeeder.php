<?php

declare(strict_types=1);

namespace app\Database\Seeders;

use app\Database\DB;

class UserRolesSeeder
{
    public static function run(): void
    {
        // Assign admin role to the admin user
        $admin = DB::single('id', 'users', ['email' => 'admin@example.com']);
        DB::insert(
            'user_roles',
            [
                'user_id' => $admin['id'],
                'role_id' => 1
            ]
        );

        // Get all users except the admin
        $users = DB::select(
            'id',
            'users',
            [
                'email' => ['!=', 'admin@example.com']
            ]
        );

        // Assign user role to all users except the admin
        foreach ($users as $user) DB::insert(
            'user_roles',
            [
                'user_id' => $user['id'],
                'role_id' => 2
            ]
        );
    }
}
