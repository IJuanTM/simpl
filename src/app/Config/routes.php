<?php

use app\Controllers\PageController;

/**
 * This constant contains extra routes for the application that do not have a view or page associated with them.
 * These routes are used to directly call a method to perform an action, for example, to go back to the previous page.
 */
$ROUTES = [
    'back' => static fn() => PageController::back(),
];
