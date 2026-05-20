<?php

declare(strict_types=1);

namespace app\Database\Seeders;

use app\Database\DB;
use app\Enums\Role;

class UserRolesSeeder
{
    public static function run(): void
    {
        $adminRoleId = DB::single(
            'id',
            'roles',
            [
                'name' => Role::ADMIN->value
            ]
        )['id'];
        $userRoleId = DB::single(
            'id',
            'roles',
            [
                'name' => Role::USER->value
            ]
        )['id'];

        // Assign admin role to the admin user
        $admin = DB::single(
            'id',
            'users',
            [
                'email' => 'admin@example.com'
            ]
        );
        DB::insert(
            'user_roles',
            [
                'user_id' => $admin['id'],
                'role_id' => $adminRoleId
            ]
        );

        // Assign user role to all other users
        $users = DB::select(
            'id',
            'users',
            [
                'email' => ['!=', 'admin@example.com']
            ]
        );

        foreach ($users as $user) DB::insert(
            'user_roles',
            [
                'user_id' => $user['id'],
                'role_id' => $userRoleId
            ]
        );
    }
}
