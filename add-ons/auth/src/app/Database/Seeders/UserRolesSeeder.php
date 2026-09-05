<?php

declare(strict_types=1);

namespace app\Database\Seeders;

use app\Database\DB;
use app\Enums\Role;

class UserRolesSeeder
{
    /**
     * Assigns the admin role to admin@example.com and the user role to every other seeded user.
     */
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
