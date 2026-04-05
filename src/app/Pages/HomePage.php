<?php

declare(strict_types=1);

namespace app\Pages;

use app\Models\Page;

class HomePage
{
    public function __construct(Page $page)
    {
        // Provide a friendly subtitle for the homepage
        $page->subtitle = 'Welcome';
    }
}
