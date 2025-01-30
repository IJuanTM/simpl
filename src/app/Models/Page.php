<?php

namespace app\Models;

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

        // Set the title to the APP_NAME constant and the subtitle to the page name
        $this->title = APP_NAME;
        $this->subtitle = $this->subtitle();
    }

    /**
     * Generate the subtitle from the page name.
     *
     * @return string
     */
    private function subtitle(): string
    {
        // Return the page name with the first letter of each word capitalized
        return ucwords(str_replace('-', ' ', $this->urlArr['page']));
    }
}
