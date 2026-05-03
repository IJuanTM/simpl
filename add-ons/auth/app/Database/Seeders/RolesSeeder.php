<?php

declare(strict_types=1);

namespace app\Database\Seeders;

use app\Database\DB;

class RolesSeeder
{
    public static function run(): void
    {
        // Admin role
        DB::insert(
            'roles',
            [
                'id' => 1,
                'name' => 'admin'
            ]
        );

        // User role
        DB::insert(
            'roles',
            [
                'id' => 2,
                'name' => 'user'
            ]
        );
    }
}
