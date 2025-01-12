<?php

namespace app\Pages;

/**
 * Page specific code goes here, look at it as a controller for the page
 */
class HomePage
{
    public function __construct(object $page)
    {
        // Set the page subtitle
        $page->subtitle = 'Welcome';
    }
}
