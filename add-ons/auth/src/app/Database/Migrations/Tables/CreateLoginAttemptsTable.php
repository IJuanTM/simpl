<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateLoginAttemptsTable
{
    /**
     * Creates the login_attempts table, recording every login attempt for lockout tracking and the admin audit log.
     */
    public static function up(): void
    {
        Schema::create('login_attempts', static function (Blueprint $t) {
            $t->bigintUnsigned('id', notNull: true)->autoIncrement();
            $t->bigintUnsigned('user_id');
            $t->varchar('ip_address', 45, notNull: true);
            $t->varchar('user_agent', notNull: true);
            $t->timestamp('attempt_time', notNull: true, default: 'CURRENT_TIMESTAMP');
            $t->tinyint('success', notNull: true, default: 0);
            $t->varchar('failed_reason', 50);
            $t->primary('id');
            $t->foreign('user_id', 'users');
            $t->index('idx_user_success_time', ['user_id', 'success', 'attempt_time']);
            $t->index('idx_ip_success_time', ['ip_address', 'success', 'attempt_time']);
        });
    }

    /**
     * Drops the login_attempts table.
     */
    public static function down(): void
    {
        Schema::drop('login_attempts');
    }
}
