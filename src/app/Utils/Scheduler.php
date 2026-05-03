<?php

declare(strict_types=1);

namespace app\Utils;

use app\Database\DB;
use Throwable;

class Scheduler
{
    /** @var ScheduledTask[] */
    private static array $tasks = [];

    /* ---------------------------------------------------------------- */

    public static function run(bool $test = false): void
    {
        $title = 'Scheduler — ' . date('Y-m-d H:i:s');
        if ($test) $title .= ' [TEST]';

        Console::box($title);
        Console::line();

        $ran = 0;

        foreach (self::$tasks as $task) {
            $record = DB::single('*', 'scheduler_runs', ['task_name' => $task->name]);
            $lastRun = $record['last_run'] ?? null;

            if (!$test && !$task->isDue($lastRun)) continue;

            Console::task("⚙️ Running: {$task->name}...");

            $start = microtime(true);
            $error = null;

            try {
                ($task->callback)();
                $status = 'success';
            } catch (Throwable $e) {
                $status = 'failed';
                $error = $e->getMessage();
                Console::error($e->getMessage());
            }

            $duration = (int)round((microtime(true) - $start) * 1000);
            $now = date('Y-m-d H:i:s');

            if ($record) {
                DB::update(
                    'scheduler_runs',
                    [
                        'last_run' => $now,
                        'last_duration_ms' => $duration,
                        'last_status' => $status,
                        'last_error' => $error,
                    ],
                    [
                        'task_name' => $task->name
                    ]
                );
            } else {
                DB::insert(
                    'scheduler_runs',
                    [
                        'task_name' => $task->name,
                        'last_run' => $now,
                        'last_duration_ms' => $duration,
                        'last_status' => $status,
                        'last_error' => $error,
                    ]
                );
            }

            if ($status === 'success') Console::success("Completed in {$duration}ms");

            Console::line();
            $ran++;
        }

        if ($ran === 0) {
            Console::info("No tasks due");
            Console::line();
        }

        Console::divider();
        Console::successBold("Scheduler completed!");
        Console::line();
    }

    public static function task(string $name, callable $callback): ScheduledTask
    {
        $task = new ScheduledTask($name, $callback);
        self::$tasks[] = $task;
        return $task;
    }
}
