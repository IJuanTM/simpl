<?php

declare(strict_types=1);

namespace app\Database\Seeders;

use app\Database\DB;
use app\Enums\Role;

class RolesSeeder
{
    /**
     * Inserts a row for each Role enum case.
     */
    public static function run(): void
    {
        foreach (Role::cases() as $role) DB::insert('roles', ['name' => $role->value]);
    }
}
