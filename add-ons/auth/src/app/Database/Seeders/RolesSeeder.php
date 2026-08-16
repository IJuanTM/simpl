<?php

declare(strict_types=1);

namespace app\Database\Seeders;

use app\Database\DB;
use app\Enums\Role;

class RolesSeeder
{
    public static function run(): void
    {
        DB::insert(
            'roles',
            [
                'name' => Role::ADMIN->value
            ]
        );
        DB::insert(
            'roles',
            [
                'name' => Role::USER->value
            ]
        );
    }
}
