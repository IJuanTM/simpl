<?php

declare(strict_types=1);

namespace app\Models;

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
     * Evaluates and processes the parameters by applying provided callback functions or default values.
     *
     * @param array $params An associative array of input parameters to be evaluated.
     *
     * @return array The resulting array after processing each parameter with its associated logic.
     */
    final public function evaluate(array $params): array
    {
        // array_map() re-indexes numerically when given multiple arrays, so the string
        // keys are restored with array_combine() rather than lost from the result.
        return array_combine(
            array_keys($this->params),
            array_map(static fn($value, $key) => $params[$key] ?? (is_callable($value) ? $value() : $value), $this->params, array_keys($this->params))
        );
    }
}
