<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateRolesTable
{
    public static function run(): void
    {
        // AUTO_INCREMENT starts at 3 to reserve IDs 1 (admin) and 2 (user) for the seeder
        Schema::create('roles', static function (Blueprint $t) {
            $t->smallintUnsigned('id', notNull: true)->autoIncrement();
            $t->varchar('name', 50, notNull: true)->unique();
        })
            ->primary('id')
            ->startAt(3);
    }
}
