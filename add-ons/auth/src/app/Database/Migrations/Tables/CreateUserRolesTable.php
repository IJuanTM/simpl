<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateUserRolesTable
{
    /**
     * Creates the user_roles pivot table linking users to roles.
     */
    public static function up(): void
    {
        Schema::create('user_roles', static function (Blueprint $t) {
            $t->bigintUnsigned('user_id', notNull: true);
            $t->smallintUnsigned('role_id', notNull: true);
            $t->primary('user_id', 'role_id');
            $t->foreign('user_id', 'users');
            $t->foreign('role_id', 'roles');
            $t->index('idx_role_user', ['role_id', 'user_id']);
        });
    }

    /**
     * Drops the user_roles table.
     */
    public static function down(): void
    {
        Schema::drop('user_roles');
    }
}
