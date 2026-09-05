<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateUsersTable
{
    /**
     * Creates the users table: identity/auth fields, profile image, password-change tracking, and soft-delete status/timestamp.
     */
    public static function up(): void
    {
        Schema::create('users', static function (Blueprint $t) {
            $t->bigintUnsigned('id', notNull: true)->autoIncrement();
            $t->varchar('username', MAX_USERNAME_LENGTH)->unique();
            $t->varchar('first_name', MAX_NAME_LENGTH);
            $t->varchar('last_name', MAX_NAME_LENGTH);
            $t->varchar('email', MAX_EMAIL_LENGTH, notNull: true)->unique();
            $t->varchar('password', notNull: true);
            // Explicit DEFAULT NULL: with explicit_defaults_for_timestamp off, a first bare TIMESTAMP becomes ON UPDATE CURRENT_TIMESTAMP, so every users UPDATE would trip requireAuth()'s password-change check.
            $t->timestamp('password_changed_at', default: null);
            $t->varchar('profile_img', 50);
            $t->tinyint('must_change_password', notNull: true, default: 0);
            $t->timestamp('last_login', default: null);
            $t->timestamp('created_at', notNull: true, default: 'CURRENT_TIMESTAMP');
            $t->timestamp('last_update', notNull: true, default: 'CURRENT_TIMESTAMP')->onUpdateCurrentTimestamp();
            $t->enum('status', ['active', 'deactivated', 'deleted'], notNull: true, default: 'active');
            $t->timestamp('inactive_since', default: null);
            $t->primary('id');
        });
    }

    /**
     * Drops the users table.
     */
    public static function down(): void
    {
        Schema::drop('users');
    }
}
