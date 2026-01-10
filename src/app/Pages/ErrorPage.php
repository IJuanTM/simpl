<?php

namespace app\Pages;

use app\Controllers\PageController;
use app\Enums\ErrorCode;
use app\Models\Page;

class ErrorPage
{
    public string $message;
    public string $code;
    public string $redirectPage;

    public function __construct(Page $page)
    {
        $errorCode = ErrorCode::tryFrom((int)($page->urlArr['subpages'][0] ?? 0));

        // Check if the code is set in the URL and is valid
        if (!$errorCode) {
            // Redirect to the 404 page if the subpage is not set or is invalid
            PageController::error(ErrorCode::NOT_FOUND);
            exit;
        }

        // Set the error code and message
        $this->code = $errorCode->value;
        $this->message = $errorCode->message();
        $this->redirectPage = $page->urlArr['params']['redirect'] ?? REDIRECT;

        // Set the page subtitle
        $page->subtitle = "Error $this->code";

        // Redirect to the homepage if auto-redirect is enabled
        if (ERROR_AUTO_REDIRECT) PageController::redirect($this->redirectPage, 2);
    }
}
