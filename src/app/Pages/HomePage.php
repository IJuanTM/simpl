<?php

namespace app\Pages;

/**
 * Page specific code goes here, look at it as a controller for the page
 */
class HomePage
{
    public function __construct(object $page)
    {
        // Override the subtitle
        $page->subtitle = 'Welcome';
    }
}
