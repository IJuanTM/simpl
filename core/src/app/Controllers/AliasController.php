<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Models\Alias;

/**
 * Registry of URL aliases: each maps a name to a target page, subpages, and (lazily evaluated) params.
 */
class AliasController
{
    private static array $aliases = [];

    public function __construct()
    {
        self::register('welcome', new Alias('home'));
    }

    /**
     * Registers an alias under the given name.
     *
     * @param string $alias
     * @param Alias  $aliasObj
     *
     * @return void
     */
    public static function register(string $alias, Alias $aliasObj): void
    {
        self::$aliases[$alias] = $aliasObj;
    }

    /**
     * Resolves an alias to its page, subpages, and params (evaluated against $params), or null if the name is unknown.
     *
     * @param string $alias
     * @param array  $params Overrides passed to the alias's param evaluation
     *
     * @return array{page: string, subpages: array, params: array}|null
     */
    public static function resolve(string $alias, array $params): ?array
    {
        $aliasObj = self::$aliases[$alias] ?? null;
        if (!$aliasObj) return null;

        return [
            'page' => $aliasObj->page,
            'subpages' => $aliasObj->subpages,
            'params' => $aliasObj->evaluate($params)
        ];
    }
}
