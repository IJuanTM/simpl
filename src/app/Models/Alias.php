<?php

declare(strict_types=1);

namespace app\Models;

/**
 * Alias model
 *
 * Represents a URL alias mapping to a target page, optional subpages and
 * parameter defaults. Parameter defaults may contain callables which will be
 * executed when resolving the alias.
 */
class Alias
{
    public string $page;

    /** @var array<int,string> */
    public array $subpages;

    /** @var array<string,mixed> */
    public array $params;

    final public function __construct(string $page, array $subpages = [], array $params = [])
    {
        $this->page = $page;
        $this->subpages = $subpages;
        $this->params = $params;
    }

    /**
     * Resolve alias parameters by invoking callables and applying defaults.
     *
     * @param array<string,mixed> $params Incoming request params
     *
     * @return array<string,mixed> Resolved params
     */
    final public function evaluate(array $params): array
    {
        return array_map(static fn($value, $key) => is_callable($value) ? $value() : ($params[$key] ?? $value), $this->params, array_keys($this->params));
    }
}
