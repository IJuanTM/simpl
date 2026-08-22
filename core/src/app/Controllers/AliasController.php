<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Models\Alias;

/**
 * Handles the creation and management of aliases within the application.
 * Aliases can be registered and resolved to map them to specific pages,
 * subpages, and associated parameters.
 */
class AliasController
{
    private static array $aliases = [];

    public function __construct()
    {
        self::register('welcome', new Alias('home'));
    }

    /**
     * Registers a new alias by associating it with the specified alias object.
     *
     * @param string $alias The name of the alias to register.
     * @param Alias  $aliasObj The alias object to associate with the given alias.
     *
     * @return void
     */
    public static function register(string $alias, Alias $aliasObj): void
    {
        self::$aliases[$alias] = $aliasObj;
    }

    /**
     * Resolves the specified alias to retrieve associated information, including
     * the page, subpages, and evaluated parameters.
     *
     * @param string $alias The alias to resolve.
     * @param array  $params An array of parameters to evaluate with the alias.
     *
     * @return array|null Returns an associative array with the resolved information or null if the alias cannot be resolved.
     */
    public static function resolve(string $alias, array $params): array|null
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
