<?php

declare(strict_types=1);

namespace app\Pages;

use app\Models\Page;

/**
 * Page class for the home view.
 */
class HomePage
{
    public function __construct(Page $page)
    {
        $page->subtitle = 'Welcome';
    }
}
