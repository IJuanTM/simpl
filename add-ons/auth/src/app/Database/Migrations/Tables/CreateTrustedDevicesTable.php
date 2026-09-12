<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateTrustedDevicesTable
{
    /**
     * Creates the trusted_devices table: one row per browser that cleared 2FA and was remembered, keyed by a public selector with a separately hashed validator.
     */
    public static function up(): void
    {
        Schema::create('trusted_devices', static function (Blueprint $t) {
            $t->bigintUnsigned('id', notNull: true)->autoIncrement();
            $t->bigintUnsigned('user_id', notNull: true);
            $t->varchar('selector', 32, notNull: true)->unique();
            $t->varchar('validator_hash', 64, notNull: true);
            $t->varchar('label', 255);
            $t->varchar('ip_address', 45);
            // Explicit default: a bare first TIMESTAMP would otherwise get an implicit ON UPDATE CURRENT_TIMESTAMP.
            $t->timestamp('expires', notNull: true, default: 'CURRENT_TIMESTAMP');
            $t->timestamp('created_at', notNull: true, default: 'CURRENT_TIMESTAMP');
            $t->timestamp('last_used_at', default: null);
            $t->primary('id');
            $t->foreign('user_id', 'users');
            $t->index('idx_trusted_user', ['user_id']);
        });
    }

    /**
     * Drops the trusted_devices table.
     */
    public static function down(): void
    {
        Schema::drop('trusted_devices');
    }
}
