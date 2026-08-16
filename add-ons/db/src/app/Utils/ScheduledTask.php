<?php

declare(strict_types=1);

namespace app\Utils;

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

    public function cron(string $expression): static
    {
        $this->cronExpression = $expression;
        $this->intervalSeconds = null;
        return $this;
    }

    public function everyMinutes(int $n = 1): static
    {
        return $this->interval($n * 60);
    }

    private function interval(int $seconds): static
    {
        $this->intervalSeconds = $seconds;
        $this->cronExpression = null;
        return $this;
    }

    public function hourly(): static
    {
        return $this->everyHours();
    }

    public function everyHours(int $n = 1): static
    {
        return $this->interval($n * 3600);
    }

    public function daily(): static
    {
        return $this->everyDays();
    }

    public function everyDays(int $n = 1): static
    {
        return $this->interval($n * 86400);
    }

    public function weekly(): static
    {
        return $this->everyWeeks();
    }

    public function everyWeeks(int $n = 1): static
    {
        return $this->interval($n * 604800);
    }

    public function isDue(?string $lastRun): bool
    {
        if ($this->cronExpression !== null) return $this->isCronDue($this->cronExpression);

        if ($lastRun === null) return true;

        return (time() - strtotime($lastRun)) >= $this->intervalSeconds;
    }

    private function isCronDue(string $expression): bool
    {
        $parts = explode(' ', trim($expression));

        if (count($parts) !== 5) return false;

        [$minute, $hour, $day, $month, $weekday] = $parts;

        return $this->matchesCronField((int)date('i'), $minute)
            && $this->matchesCronField((int)date('H'), $hour)
            && $this->matchesCronField((int)date('j'), $day)
            && $this->matchesCronField((int)date('n'), $month)
            && $this->matchesCronField((int)date('w'), $weekday);
    }

    private function matchesCronField(int $current, string $field): bool
    {
        if ($field === '*') return true;

        if (str_contains($field, '/')) {
            [$range, $step] = explode('/', $field);
            $step = (int)$step;
            $start = $range === '*' ? 0 : (int)$range;
            return $step > 0 && ($current - $start) % $step === 0 && $current >= $start;
        }

        if (str_contains($field, '-')) {
            [$from, $to] = explode('-', $field);
            return $current >= (int)$from && $current <= (int)$to;
        }

        if (str_contains($field, ',')) return explode(',', $field)
                |> (static fn($x) => array_map('intval', $x))
                |> (static fn($x) => in_array($current, $x, true));

        return $current === (int)$field;
    }
}
