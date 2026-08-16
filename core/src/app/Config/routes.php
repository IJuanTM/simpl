<?php

declare(strict_types=1);

use app\Controllers\PageController;

// Routes with no page/view of their own - each entry calls a method directly (e.g. 'back').
$ROUTES = [
    'back' => static fn() => PageController::back(),
];
