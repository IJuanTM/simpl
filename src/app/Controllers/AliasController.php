<?php

namespace app\Controllers;

use app\Models\Alias;

/**
 * Provides aliasing functionality for pages.
 * Allows registering a page alias that maps to a different page and subpages, as well as parameters.
 */
class AliasController
{
    private static array $aliases = [];

    public function __construct()
    {
        // Welcome > Home
        self::register('welcome', new Alias('home'));
    }

    /**
     * Register an alias.
     *
     * @param string $alias
     * @param Alias $aliasObj
     */
    public static function register(string $alias, Alias $aliasObj): void
    {
        // Register the alias
        self::$aliases[$alias] = $aliasObj;
    }

    /**
     * Resolve an alias to a page and subpages.
     *
     * @param string $alias
     * @param array $params
     *
     * @return array|null
     */
    public static function resolve(string $alias, array $params): array|null
    {
        // Check if the alias exists
        $aliasObj = self::$aliases[$alias] ?? null;
        if (!$aliasObj) return null;

        return [
            'page' => $aliasObj->page,
            'subpages' => $aliasObj->subpages,
            'params' => $aliasObj->evaluate($params),
        ];
    }
}
