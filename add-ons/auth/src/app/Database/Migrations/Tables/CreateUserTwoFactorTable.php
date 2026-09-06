<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateUserTwoFactorTable
{
    /**
     * Creates the user_two_factor table: one row per user, holding the 2FA on/off flag, the primary method, per-method enrolment flags, and the encrypted TOTP secret.
     */
    public static function up(): void
    {
        Schema::create('user_two_factor', static function (Blueprint $t) {
            $t->bigintUnsigned('user_id', notNull: true);
            $t->tinyint('enabled', notNull: true, default: 0);
            $t->enum('primary_method', ['email', 'totp', 'passkey'], notNull: true, default: 'email');
            $t->tinyint('email_enabled', notNull: true, default: 0);
            $t->tinyint('totp_enabled', notNull: true, default: 0);
            $t->tinyint('passkey_enabled', notNull: true, default: 0);
            $t->tinyint('remember_device', notNull: true, default: 1);
            // Crypto::encrypt() output, never the raw base32 secret.
            $t->varchar('totp_secret', 255);
            $t->timestamp('totp_confirmed_at', default: null);
            $t->timestamp('created_at', notNull: true, default: 'CURRENT_TIMESTAMP');
            $t->timestamp('last_update', notNull: true, default: 'CURRENT_TIMESTAMP')->onUpdateCurrentTimestamp();
            $t->primary('user_id');
            $t->foreign('user_id', 'users');
        });
    }

    /**
     * Drops the user_two_factor table.
     */
    public static function down(): void
    {
        Schema::drop('user_two_factor');
    }
}
