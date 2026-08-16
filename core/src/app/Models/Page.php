<?php

declare(strict_types=1);

namespace app\Models;

use app\Controllers\SessionController;

/**
 * Represents a webpage with properties for routing, title management, and navigation history.
 */
class Page
{
    public string $page;
    public array $subpages;
    public array $params;
    public ?object $pageObj = null;
    public string $title;
    public string $subtitle;

    public function __construct(string $page, array $subpages = [], array $params = [])
    {
        $this->page = $page;
        $this->subpages = $subpages;
        $this->params = $params;

        $history = self::history();
        $subUrl = $this->subUrl();

        // Push current subUrl to history and keep the last HISTORY_DEPTH entries
        if (end($history) !== $subUrl) SessionController::set('history', array_slice([...$history, $subUrl], -HISTORY_DEPTH));

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
        $subpages = implode('/', $this->subpages);
        $query = $this->params ? '?' . http_build_query($this->params) : '';

        return "/$this->page/$subpages$query";
    }

    /**
     * Constructs and returns the subtitle by transforming the page value.
     *
     * @return string The formatted subtitle derived from the page URL segment.
     */
    private function getSubtitle(): string
    {
        return ucwords(str_replace('-', ' ', $this->page));
    }

    /**
     * Returns the nth URL subpage segment, or null if it doesn't exist.
     */
    public function subpage(int $n = 0): ?string
    {
        return isset($this->subpages[$n]) ? (string)$this->subpages[$n] : null;
    }

    /**
     * Returns a query parameter value, or $default if the key is absent.
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}
