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
            // RESTRICT (not the project default CASCADE): Roles::deleteRole() checks for assigned users
            // first, but a role assigned in the race window between that check and the DELETE must block
            // the delete at the DB level instead of silently cascading the assignment away with it.
            $t->foreign('role_id', 'roles', onDelete: 'RESTRICT');
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
