<?php

declare(strict_types=1);

namespace app\Models;

use Closure;

/**
 * Represents an alias with associated page information, subpages, and parameters.
 */
class Alias
{
    public string $page;
    public array $subpages;
    public array $params;

    final public function __construct(string $page, array $subpages = [], array $params = [])
    {
        $this->page = $page;
        $this->subpages = $subpages;
        $this->params = $params;
    }

    /**
     * Resolves each configured param to its override in $params, or to its default (calling the default if it's a Closure).
     *
     * @param array $params Overrides keyed by param name
     *
     * @return array
     */
    final public function evaluate(array $params): array
    {
        // array_map() with multiple arrays re-indexes numerically, so array_combine() restores the original string keys.
        return array_combine(
            array_keys($this->params),
            array_map(static fn($value, $key) => $params[$key] ?? ($value instanceof Closure ? $value() : $value), $this->params, array_keys($this->params))
        );
    }
}
