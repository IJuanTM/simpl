<?php

declare(strict_types=1);

namespace app\Utils;

use LogicException;

/**
 * A named, callable task with a schedule - either a cron expression or a fixed interval - set
 * via cron()/everyMinutes()/hourly()/etc. and checked against the last run time by isDue().
 */
class ScheduledTask
{
    public string $name;
    public mixed $callback;
    public ?string $cronExpression = null;
    public ?int $intervalSeconds = null;

    public function __construct(string $name, callable $callback)
    {
        $this->name = $name;
        $this->callback = $callback;
    }

    /**
     * Schedules the task by a standard 5-field cron expression, clearing any interval schedule.
     */
    final public function cron(string $expression): static
    {
        $this->cronExpression = $expression;
        $this->intervalSeconds = null;
        return $this;
    }

    /**
     * Schedules the task to run every $n minutes.
     */
    final public function everyMinutes(int $n = 1): static
    {
        return $this->interval($n * 60);
    }

    /**
     * Schedules the task on a fixed interval, clearing any cron schedule.
     */
    private function interval(int $seconds): static
    {
        $this->intervalSeconds = $seconds;
        $this->cronExpression = null;
        return $this;
    }

    /**
     * Schedules the task to run every hour.
     */
    final public function hourly(): static
    {
        return $this->everyHours();
    }

    /**
     * Schedules the task to run every $n hours.
     */
    final public function everyHours(int $n = 1): static
    {
        return $this->interval($n * 3600);
    }

    /**
     * Schedules the task to run once a day.
     */
    final public function daily(): static
    {
        return $this->everyDays();
    }

    /**
     * Schedules the task to run every $n days.
     */
    final public function everyDays(int $n = 1): static
    {
        return $this->interval($n * 86400);
    }

    /**
     * Schedules the task to run once a week.
     */
    final public function weekly(): static
    {
        return $this->everyWeeks();
    }

    /**
     * Schedules the task to run every $n weeks.
     */
    final public function everyWeeks(int $n = 1): static
    {
        return $this->interval($n * 604800);
    }

    /**
     * Whether the task is due to run now, given $lastRun (null if it has never run).
     *
     * @throws LogicException if no schedule was ever set
     */
    final public function isDue(?string $lastRun): bool
    {
        if ($this->cronExpression !== null) return $this->isCronDue($this->cronExpression);

        if ($this->intervalSeconds === null) {
            throw new LogicException("Scheduled task \"$this->name\" has no schedule (call cron() or an interval helper).");
        }

        if ($lastRun === null) return true;

        return (time() - strtotime($lastRun)) >= $this->intervalSeconds;
    }

    /**
     * Whether the current time matches a standard 5-field cron expression.
     */
    private function isCronDue(string $expression): bool
    {
        $parts = explode(' ', trim($expression));

        if (count($parts) !== 5) return false;

        [$minute, $hour, $day, $month, $weekday] = $parts;

        $currentWeekday = (int)date('w');

        $dayMatches = $this->matchesCronField((int)date('j'), $day);

        // date('w') is 0-6; standard cron also accepts 7 for Sunday.
        $weekdayMatches = $this->matchesCronField($currentWeekday, $weekday)
            || ($currentWeekday === 0 && $this->matchesCronField(7, $weekday));

        // Standard cron semantics: day-of-month and weekday OR together when both are restricted, AND otherwise.
        $dayOrWeekday = $day === '*' || $weekday === '*' ? $dayMatches && $weekdayMatches : $dayMatches || $weekdayMatches;

        return $this->matchesCronField((int)date('i'), $minute)
            && $this->matchesCronField((int)date('H'), $hour)
            && $dayOrWeekday
            && $this->matchesCronField((int)date('n'), $month);
    }

    /**
     * Whether $current matches a single cron field - a wildcard, comma-list, step (/), range (-), or literal value.
     */
    private function matchesCronField(int $current, string $field): bool
    {
        if ($field === '*') return true;

        // Check comma-lists first, so "1,3,5-10" matches as three parts, not one "-" range.
        if (str_contains($field, ',')) return array_any(explode(',', $field), fn($part) => $this->matchesCronField($current, $part));

        if (str_contains($field, '/')) {
            [$range, $step] = explode('/', $field);
            $step = (int)$step;

            if ($range === '*') [$start, $end] = [0, null];
            else if (str_contains($range, '-')) [$start, $end] = array_map('intval', explode('-', $range));
            else [$start, $end] = [(int)$range, null];

            return $step > 0 && $current >= $start && ($end === null || $current <= $end) && ($current - $start) % $step === 0;
        }

        if (str_contains($field, '-')) {
            [$from, $to] = explode('-', $field);
            return $current >= (int)$from && $current <= (int)$to;
        }

        return $current === (int)$field;
    }
}
