<?php

namespace app\Pages;

use app\Models\Page;

/**
 * Page specific code goes here, look at it as a controller for the page
 */
class HomePage
{
    public function __construct(Page $page)
    {
        // Override the subtitle
        $page->subtitle = 'Welcome';
    }
}
