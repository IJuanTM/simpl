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
            $t->varchar('username', 100);
            $t->varchar('first_name', 100);
            $t->varchar('last_name', 100);
            $t->varchar('email', 100, notNull: true)->unique();
            $t->varchar('password', notNull: true);
            $t->varchar('profile_img', 50);
            $t->tinyint('must_change_password', notNull: true, default: 0);
            $t->timestamp('last_login');
            $t->timestamp('created_at', notNull: true, default: 'CURRENT_TIMESTAMP');
            $t->timestamp('last_update', notNull: true, default: 'CURRENT_TIMESTAMP')->onUpdateCurrentTimestamp();
            $t->tinyint('is_active', notNull: true, default: 1);
            $t->timestamp('deleted_at');
        })
            ->primary('id');
    }

    public static function down(): void
    {
        Schema::drop('users');
    }
}
