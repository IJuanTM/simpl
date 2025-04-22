<?php

namespace app\Models;

use app\Controllers\SessionController;

/**
 * The Page class is the model for a page.
 * It contains properties for the page, such as the page object, the url parts, the title and the subtitle.
 */
class Page
{
    public array $urlArr;
    public object $pageObj;
    public string $title;
    public string $subtitle;

    public function __construct(string $page, ?array $subpages = [], ?array $params = [])
    {
        // Combine the page, subpages and params into an array
        $this->urlArr = compact('page', 'subpages', 'params');

        $history = self::history();
        $subUrl = $this->subUrl();

        // If the history is empty or the last element is not the current subUrl, add the current subUrl to the history
        if (end($history) !== $subUrl) SessionController::set('history', array_slice([...$history, $subUrl], -5));

        // Set the title to the APP_NAME constant and the subtitle to the page name
        $this->title = APP_NAME;
        $this->subtitle = $this->subtitle();
    }

    /**
     * Get the history of visited pages from session.
     * @return array
     */
    public static function history(): array
    {
        return SessionController::get('history') ?? [];
    }

    /**
     * Get the sub URL of the page as a string by combining the page, subpages and parameters.
     * @return string
     */
    final public function subUrl(): string
    {
        return '/' . $this->urlArr['page'] . '/' . implode('/', $this->urlArr['subpages']) . ($this->urlArr['params'] ? '?' . http_build_query($this->urlArr['params']) : '');
    }

    /**
     * Generate the subtitle from the page name.
     * @return string
     */
    private function subtitle(): string
    {
        // Return the page name with the first letter of each word capitalized
        return ucwords(str_replace('-', ' ', $this->urlArr['page']));
    }
}
