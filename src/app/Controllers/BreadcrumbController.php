<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Models\Page;

/**
 * Holds the breadcrumb trail for the current page. A page registers its trail once,
 * typically in its constructor; the shared breadcrumbs component renders it.
 */
class BreadcrumbController
{
    /**
     * @var array<int, array{label: string, url: string|null}>
     */
    private static array $trail = [];

    /**
     * Sets the breadcrumb trail for the current page.
     *
     * @param array<int, array{label: string, url: string|null}> $trail Ordered list of
     *                                                                  crumbs; a null url marks a non-clickable (current page) crumb.
     *
     * @return void
     */
    public static function set(array $trail): void
    {
        self::$trail = $trail;
    }

    /**
     * Builds and sets the breadcrumb trail straight from the page's URL: one crumb
     * per segment (top-level page, then each subpage), dashes replaced with spaces
     * and the first letter capitalized. The last crumb is left without a url, marking
     * it as the current (non-clickable) page.
     *
     * @return void
     */
    public static function generate(Page $page): void
    {
        $path = '';
        $trail = [];

        foreach ([$page->page, ...$page->subpages] as $segment) {
            $path = $path === '' ? $segment : "$path/$segment";
            $trail[] = ['label' => ucfirst(str_replace('-', ' ', $segment)), 'url' => $path];
        }

        $trail[array_key_last($trail)]['url'] = null;

        self::$trail = $trail;
    }

    /**
     * Returns the breadcrumb trail for the current page, or an empty array if none was set.
     *
     * @return array<int, array{label: string, url: string|null}>
     */
    public static function get(): array
    {
        return self::$trail;
    }
}
