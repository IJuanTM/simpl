<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateRolesTable
{
    public static function up(): void
    {
        Schema::create('roles', static function (Blueprint $t) {
            $t->smallintUnsigned('id', notNull: true)->autoIncrement();
            $t->varchar('name', 50, notNull: true)->unique();
            $t->primary('id');
        });
    }

    public static function down(): void
    {
        Schema::drop('roles');
    }
}
