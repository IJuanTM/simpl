<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateUsersTable
{
    public static function up(): void
    {
        Schema::create('users', static function (Blueprint $t) {
            $t->bigintUnsigned('id', notNull: true)->autoIncrement();
            $t->varchar('username', MAX_USERNAME_LENGTH)->unique();
            $t->varchar('first_name', MAX_NAME_LENGTH);
            $t->varchar('last_name', MAX_NAME_LENGTH);
            $t->varchar('email', MAX_EMAIL_LENGTH, notNull: true)->unique();
            $t->varchar('password', notNull: true);
            $t->timestamp('password_changed_at');
            $t->varchar('profile_img', 50);
            $t->tinyint('must_change_password', notNull: true, default: 0);
            $t->timestamp('last_login');
            $t->timestamp('created_at', notNull: true, default: 'CURRENT_TIMESTAMP');
            $t->timestamp('last_update', notNull: true, default: 'CURRENT_TIMESTAMP')->onUpdateCurrentTimestamp();
            $t->enum('status', ['active', 'deactivated', 'deleted'], notNull: true, default: 'active');
            $t->timestamp('inactive_since');
            $t->primary('id');
        });
    }

    public static function down(): void
    {
        Schema::drop('users');
    }
}
