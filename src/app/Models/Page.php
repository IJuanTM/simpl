<?php

declare(strict_types=1);

namespace app\Models;

use app\Controllers\SessionController;

/**
 * Represents a webpage with properties for routing, title management, and navigation history.
 */
class Page
{
    public array $urlArr;
    public ?object $pageObj = null;
    public string $title;
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
     * Retrieves and returns the browsing history from the session.
     *
     * @return array The history data stored in the session, or an empty array if no history exists.
     */
    public static function history(): array
    {
        return SessionController::get('history') ?? [];
    }

    /**
     * Constructs and returns a formatted URL string composed of the page, subpages, and query parameters.
     *
     * @return string The generated URL derived from the URL components.
     */
    final public function subUrl(): string
    {
        $page = $this->urlArr['page'];
        $subpages = implode('/', $this->urlArr['subpages']);
        $query = $this->urlArr['params'] ? '?' . http_build_query($this->urlArr['params']) : '';

        return "/$page/$subpages$query";
    }

    /**
     * Constructs and returns the subtitle by transforming the page value.
     *
     * @return string The formatted subtitle derived from the page URL segment.
     */
    private function getSubtitle(): string
    {
        return ucwords(str_replace('-', ' ', $this->urlArr['page']));
    }
}
