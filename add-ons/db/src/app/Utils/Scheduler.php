<?php

declare(strict_types=1);

namespace app\Utils;

use app\Database\DB;
use app\Enums\Ansi;
use PDOException;
use Throwable;

/**
 * Registry and runner for cron-style scheduled tasks, with run history persisted in scheduler_runs.
 */
class Scheduler
{
    /** @var ScheduledTask[] */
    private static array $tasks = [];

    /**
     * Runs every registered task that's due (or all of them, when $test is true), recording each
     * outcome in scheduler_runs.
     *
     * @return int Number of tasks that threw during this run (0 when all succeeded).
     */
    public static function run(bool $test = false): int
    {
        Console::titleBox('Scheduler', date('Y-m-d H:i:s') . ($test ? ', test run' : ''));
        Console::line();

        $ran = 0;
        $failed = 0;

        foreach (self::$tasks as $task) {
            $record = DB::single(
                SELECT: '*',
                FROM: 'scheduler_runs',
                WHERE: [
                    'task_name' => $task->name
                ]
            );
            $lastRun = $record['last_run'] ?? null;

            if (!$test && !$task->isDue($lastRun)) continue;

            if ($ran > 0) Console::line();
            Console::task("⚙️ Running: $task->name...");
            Console::line();

            $start = microtime(true);
            $error = null;

            try {
                ($task->callback)();
                $status = 'success';
            } catch (Throwable $e) {
                $status = 'failed';
                $error = $e->getMessage();
                $failed++;
                Console::error($e->getMessage());
            }

            $duration = (int)round((microtime(true) - $start) * 1000);
            $now = date('Y-m-d H:i:s');

            $set = [
                'last_run' => $now,
                'last_duration_ms' => $duration,
                'last_status' => $status,
                'last_error' => $error,
            ];
            $where = ['task_name' => $task->name];

            try {
                if ($record) DB::update(UPDATE: 'scheduler_runs', SET: $set, WHERE: $where);
                else DB::insert(INTO: 'scheduler_runs', VALUES: ['task_name' => $task->name, ...$set]);
            } catch (PDOException $e) {
                // A concurrent scheduler run for this task can win the insert race; fall back to an update.
                // DB::handleError() re-throws via the standard Exception constructor, which always coerces the code to int; it's never the SQLSTATE string, even though PDO's own driver exceptions sometimes carry it as one.
                if ($record || (int)$e->getCode() !== 23000) throw $e;
                DB::update(UPDATE: 'scheduler_runs', SET: $set, WHERE: $where);
            }

            if ($status === 'success') Console::success("Completed in {$duration}ms");
            $ran++;
        }

        if ($ran === 0) Console::info('No tasks due');

        Console::divider();
        if ($failed > 0) Console::error(Console::styled('Scheduler finished with ' . Console::plural($failed, 'failed task'), Ansi::BOLD, Ansi::RED), true);
        else Console::success(Console::styled('Scheduler completed!', Ansi::BOLD, Ansi::GREEN), true);
        Console::line();

        return $failed;
    }

    /**
     * Registers a named task, returning it so its schedule can be set fluently (e.g. ->daily()).
     */
    public static function task(string $name, callable $callback): ScheduledTask
    {
        $task = new ScheduledTask($name, $callback);
        self::$tasks[] = $task;
        return $task;
    }
}
