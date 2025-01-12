<?php

namespace app\Models;

/**
 * The PageModel class is the model for a page.
 * It contains properties for the page, such as the page object, the url parts, the title and the subtitle.
 */
class PageModel
{
    public object $pageObj;
    public array $urlArr;
    public string|null $title;
    public string|null $subtitle;

    public function __construct(string $page, array $subpages, array $params)
    {
        // Set the url array
        $this->urlArr = compact('page', 'subpages', 'params');

        // Set the title
        $this->title = APP_NAME;
        $this->subtitle = ucwords(str_replace('-', ' ', $page));
    }
}
