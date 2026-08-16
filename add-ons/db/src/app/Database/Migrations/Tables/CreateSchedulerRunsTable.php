<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateSchedulerRunsTable
{
    public static function up(): void
    {
        Schema::create('scheduler_runs', static function (Blueprint $t) {
            $t->bigintUnsigned('id', notNull: true)->autoIncrement();
            $t->varchar('task_name', 100, notNull: true)->unique();
            $t->timestamp('last_run');
            $t->intUnsigned('last_duration_ms');
            $t->enum('last_status', ['success', 'failed']);
            $t->text('last_error');
            $t->primary('id');
        });
    }

    public static function down(): void
    {
        Schema::drop('scheduler_runs');
    }
}
