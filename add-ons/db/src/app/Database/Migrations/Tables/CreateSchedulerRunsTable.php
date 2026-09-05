<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateSchedulerRunsTable
{
    /**
     * Creates the scheduler_runs table: one row per registered scheduled task, tracking its last run.
     */
    public static function up(): void
    {
        Schema::create('scheduler_runs', static function (Blueprint $t) {
            $t->bigintUnsigned('id', notNull: true)->autoIncrement();
            $t->varchar('task_name', 100, notNull: true)->unique();
            // Explicit DEFAULT NULL: with explicit_defaults_for_timestamp off a first bare TIMESTAMP auto-fills now(), which isDue() would read as "already ran".
            $t->timestamp('last_run', default: null);
            $t->intUnsigned('last_duration_ms');
            $t->enum('last_status', ['success', 'failed']);
            $t->text('last_error');
            $t->primary('id');
        });
    }

    /**
     * Drops the scheduler_runs table.
     */
    public static function down(): void
    {
        Schema::drop('scheduler_runs');
    }
}
