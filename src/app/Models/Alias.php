<?php

namespace app\Models;

class Alias
{
    public string $page;
    public array $subpages;
    public array $params;

    final public function __construct(string $page, array|null $subpages = [], array|null $params = [])
    {
        $this->page = $page;
        $this->subpages = $subpages;
        $this->params = $params;
    }

    /**
     * Evaluate the parameters of the alias. If the parameter is a callable, call it and return the result, otherwise return the parameter.
     *
     * @param array $params
     *
     * @return array
     */
    final public function evaluate(array $params): array
    {
        // For each value in the params array, if the value is a callable, call it and return the result, otherwise return the value
        return array_map(static fn($value, $key) => is_callable($value) ? $value() : ($params[$key] ?? $value), $this->params, array_keys($this->params));
    }
}
