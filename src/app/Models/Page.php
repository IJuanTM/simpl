<?php

declare(strict_types=1);

namespace app\Models;

use app\Controllers\SessionController;

/**
 * Page model
 *
 * Holds routing information for the current request and maintains a simple
 * navigation history stored in the session. Designed to be extended by page
 * controller classes which provide rendering and API handling.
 */
class Page
{
    /** @var array{page:string,subpages:array,params:array} Route data */
    public array $urlArr;

    /** @var object|null Optional page handler object instantiated by PageController */
    public ?object $pageObj = null;

    /** @var string Site title (defaults to APP_NAME) */
    public string $title;

    /** @var string Readable subtitle generated from the page slug */
    public string $subtitle;

    public function __construct(string $page, array $subpages = [], array $params = [])
    {
        // Store routing data
        $this->urlArr = compact('page', 'subpages', 'params');

        $history = self::history();
        $subUrl = $this->subUrl();

        // Push current subUrl to history and keep last 5 entries
        if (end($history) !== $subUrl) SessionController::set('history', array_slice([...$history, $subUrl], -5));

        $this->title = APP_NAME;
        $this->subtitle = $this->getSubtitle();
    }

    /**
     * Retrieve navigation history from session.
     *
     * @return array<int,string>
     */
    public static function history(): array
    {
        return SessionController::get('history') ?? [];
    }

    /**
     * Return the sub-URL string composed of page, subpages and query params.
     *
     * @return string
     */
    final public function subUrl(): string
    {
        $page = $this->urlArr['page'];
        $subpages = implode('/', $this->urlArr['subpages']);
        $query = $this->urlArr['params'] ? '?' . http_build_query($this->urlArr['params']) : '';

        return "/$page/$subpages$query";
    }

    /**
     * Build a readable subtitle from the page slug.
     *
     * @return string
     */
    private function getSubtitle(): string
    {
        return ucwords(str_replace('-', ' ', $this->urlArr['page']));
    }
}
