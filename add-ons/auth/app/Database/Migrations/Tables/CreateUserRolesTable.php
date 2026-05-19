<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateUserRolesTable
{
    public static function up(): void
    {
        Schema::create('user_roles', static function (Blueprint $t) {
            $t->bigintUnsigned('user_id', notNull: true);
            $t->smallintUnsigned('role_id', notNull: true);
        })
            ->primary('user_id', 'role_id')
            ->foreign('user_id', 'users')
            ->foreign('role_id', 'roles')
            ->index('idx_role_user', ['role_id', 'user_id']);
    }

    public static function down(): void
    {
        Schema::drop('user_roles');
    }
}
