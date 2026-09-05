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

        $this->recordHistory();

        $this->title = APP_NAME;
        $this->subtitle = $this->getSubtitle();
    }

    /**
     * Appends this page's URL to the navigation history, unless it's already the most recent entry.
     *
     * @return void
     */
    private function recordHistory(): void
    {
        $history = self::history();
        $subUrl = $this->subUrl();

        if (end($history) !== $subUrl) SessionController::set('history', array_slice([...$history, $subUrl], -HISTORY_DEPTH));
    }

    /**
     * The navigation history from the session, or an empty array if none exists.
     *
     * @return array
     */
    public static function history(): array
    {
        return SessionController::get('history') ?? [];
    }

    /**
     * The root-relative URL for this page: /page/subpage/subpage?query.
     *
     * @return string
     */
    final public function subUrl(): string
    {
        $subpages = implode('/', $this->subpages);
        $query = $this->params ? '?' . http_build_query($this->params) : '';

        return "/$this->page/$subpages$query";
    }

    /**
     * The page subtitle: the page slug with hyphens turned to spaces and each word capitalized.
     *
     * @return string
     */
    private function getSubtitle(): string
    {
        return ucwords(str_replace('-', ' ', $this->page));
    }

    /**
     * Returns the nth URL subpage segment, or null if it doesn't exist.
     *
     * @param int $n 0-based index.
     *
     * @return string|null
     */
    final public function subpage(int $n = 0): ?string
    {
        return isset($this->subpages[$n]) ? (string)$this->subpages[$n] : null;
    }

    /**
     * Returns a query parameter value, or $default if the key is absent.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    final public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}
