<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Models\Page;

/**
 * Holds the breadcrumb trail for the current page, set by the page and rendered by the component.
 */
class BreadcrumbController
{
    /**
     * @var array<int, array{label: string, url: string|null}>
     */
    private static array $trail = [];

    /**
     * Builds and sets the breadcrumb trail from the page's URL segments.
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

        self::set($trail);
    }

    /**
     * Sets the breadcrumb trail for the current page. A null url marks the current crumb.
     * Sanitizes label/url here, so every caller's output is safe regardless of input.
     *
     * @param array<int, array{label: string, url: string|null}> $trail
     *
     * @return void
     */
    public static function set(array $trail): void
    {
        self::$trail = array_map(static fn(array $crumb): array => [
            'label' => AppController::sanitize($crumb['label']),
            'url' => $crumb['url'] !== null ? AppController::sanitize($crumb['url']) : null,
        ], $trail);
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
