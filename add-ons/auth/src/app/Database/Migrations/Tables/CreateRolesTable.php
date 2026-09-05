<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateRolesTable
{
    /**
     * Creates the roles table.
     */
    public static function up(): void
    {
        Schema::create('roles', static function (Blueprint $t) {
            $t->smallintUnsigned('id', notNull: true)->autoIncrement();
            $t->varchar('name', MAX_ROLE_NAME_LENGTH, notNull: true)->unique();
            $t->primary('id');
        });
    }

    /**
     * Drops the roles table.
     */
    public static function down(): void
    {
        Schema::drop('roles');
    }
}
