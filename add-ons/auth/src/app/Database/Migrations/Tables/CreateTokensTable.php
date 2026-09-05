<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateTokensTable
{
    /**
     * Creates the tokens table: verification/reset/remember-me tokens tied to a user and type, with expiry.
     */
    public static function up(): void
    {
        Schema::create('tokens', static function (Blueprint $t) {
            $t->bigintUnsigned('id', notNull: true)->autoIncrement();
            $t->bigintUnsigned('user_id', notNull: true);
            $t->varchar('token', 64, notNull: true);
            $t->varchar('type', 50, notNull: true);
            $t->timestamp('created', notNull: true, default: 'CURRENT_TIMESTAMP');
            // Explicit DEFAULT NULL: with explicit_defaults_for_timestamp off a bare TIMESTAMP defaults to a zero date, which checkToken() would read as expired.
            $t->timestamp('expires', default: null);
            $t->primary('id');
            $t->foreign('user_id', 'users');
            $t->index('idx_user_type_expires', ['user_id', 'type', 'expires']);
            $t->index('idx_token', ['token']);
        });
    }

    /**
     * Drops the tokens table.
     */
    public static function down(): void
    {
        Schema::drop('tokens');
    }
}
