<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Models\Alias;

/**
 * AliasController
 *
 * Maintains a registry of URL aliases and resolves them into concrete page
 * targets and parameters. Aliases are stored in a private static map and are
 * resolved using the Alias model which performs parameter merging/evaluation.
 */
class AliasController
{
    /** @var array<string, Alias> Registered aliases */
    private static array $aliases = [];

    public function __construct()
    {
        // Register default aliases (welcome -> home)
        self::register('welcome', new Alias('home'));
    }

    /**
     * Register a page alias.
     *
     * @param string $alias URL alias (key)
     * @param Alias $aliasObj Alias model describing target mapping
     *
     * @return void
     */
    public static function register(string $alias, Alias $aliasObj): void
    {
        self::$aliases[$alias] = $aliasObj;
    }

    /**
     * Resolve an alias to its page mapping and merged params.
     *
     * Returns an associative array with keys:
     * - 'page' => string target page name
     * - 'subpages' => array list of subpage segments
     * - 'params' => array merged request parameters
     *
     * @param string $alias Alias name to resolve
     * @param array<string,mixed> $params Incoming request params to evaluate against
     *
     * @return array<string,mixed>|null ['page'=>string,'subpages'=>array,'params'=>array] or null when not found
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
