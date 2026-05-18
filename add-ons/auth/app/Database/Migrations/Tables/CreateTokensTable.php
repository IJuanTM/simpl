<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateTokensTable
{
    public static function run(): void
    {
        Schema::create('tokens', static function (Blueprint $t) {
            $t->bigintUnsigned('id', notNull: true)->autoIncrement();
            $t->bigintUnsigned('user_id', notNull: true);
            $t->varchar('token', 32, notNull: true);
            $t->varchar('type', 50, notNull: true);
            $t->timestamp('created', notNull: true, default: 'CURRENT_TIMESTAMP');
            $t->timestamp('expires');
        })
            ->primary('id')
            ->foreign('user_id', 'users')
            ->index('idx_user_type_expires', ['user_id', 'type', 'expires']);
    }
}
