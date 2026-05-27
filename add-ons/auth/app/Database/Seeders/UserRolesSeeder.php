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
            SELECT: 'id',
            FROM: 'roles',
            WHERE: [
                'name' => Role::ADMIN->value
            ]
        )['id'];
        $userRoleId = DB::single(
            SELECT: 'id',
            FROM: 'roles',
            WHERE: [
                'name' => Role::USER->value
            ]
        )['id'];

        // Assign admin role to the admin user
        $admin = DB::single(
            SELECT: 'id',
            FROM: 'users',
            WHERE: [
                'email' => 'admin@example.com'
            ]
        );
        DB::insert(
            INTO: 'user_roles',
            VALUES: [
                'user_id' => $admin['id'],
                'role_id' => $adminRoleId
            ]
        );

        // Assign user role to all other users
        $users = DB::select(
            SELECT: 'id',
            FROM: 'users',
            WHERE: [
                'email' => ['!=', 'admin@example.com']
            ]
        );

        foreach ($users as $user) DB::insert(
            INTO: 'user_roles',
            VALUES: [
                'user_id' => $user['id'],
                'role_id' => $userRoleId
            ]
        );
    }
}
