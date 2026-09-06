<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateTwoFactorRecoveryCodesTable
{
    /**
     * Creates the two_factor_recovery_codes table: one row per single-use recovery code, stored as a hash and marked with used_at once redeemed.
     */
    public static function up(): void
    {
        Schema::create('two_factor_recovery_codes', static function (Blueprint $t) {
            $t->bigintUnsigned('id', notNull: true)->autoIncrement();
            $t->bigintUnsigned('user_id', notNull: true);
            $t->varchar('code_hash', 64, notNull: true);
            $t->timestamp('used_at', default: null);
            $t->timestamp('created_at', notNull: true, default: 'CURRENT_TIMESTAMP');
            $t->primary('id');
            $t->foreign('user_id', 'users');
            $t->index('idx_recovery_user_used', ['user_id', 'used_at']);
        });
    }

    /**
     * Drops the two_factor_recovery_codes table.
     */
    public static function down(): void
    {
        Schema::drop('two_factor_recovery_codes');
    }
}
