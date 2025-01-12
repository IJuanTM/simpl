<?php

namespace app\Pages;

use app\Controllers\PageController;

class ErrorPage
{
    public string $message;
    public string $code;

    private array $errors = [
        '400' => 'Bad request.',
        '401' => 'You are not authorized to view this page.',
        '403' => 'You have no access to this page.',
        '404' => 'This page does not exist.',
        '500' => 'An internal server error occurred.'
    ];

    public function __construct(object $page)
    {
        // Check if the code is set in the URL and is valid
        if (!isset($page->urlArr['subpages'][0]) || !array_key_exists($page->urlArr['subpages'][0], $this->errors)) {
            // Redirect to /error/404
            PageController::redirect('/error/404');
            exit;
        }

        // Set the error code and message
        $this->code = $page->urlArr['subpages'][0];
        $this->message = $this->errors[$this->code];

        // Set the page subtitle
        $page->subtitle = "Error $this->code";

        // Redirect to the homepage if auto-redirect is enabled
        if (ERROR_AUTO_REDIRECT) PageController::redirect(REDIRECT, 2);
    }
}
