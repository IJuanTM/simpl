<?php

declare(strict_types=1);

namespace app\Pages;

use app\Models\Page;

/**
 * HomePage
 *
 * Simple page handler for the site home. Mutates the supplied Page model to
 * set a human-friendly subtitle.
 */
class HomePage
{
    public function __construct(Page $page)
    {
        // Provide a friendly subtitle for the homepage
        $page->subtitle = 'Welcome';
    }
}
