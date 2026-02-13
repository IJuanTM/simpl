<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\PageController;
use app\Enums\ErrorCode;
use app\Models\Page;

/**
 * ErrorPage
 *
 * Displays an error page based on an ErrorCode passed in the route. The page
 * may optionally redirect back to a provided page after a short delay when
 * `ERROR_AUTO_REDIRECT` is enabled.
 */
class ErrorPage
{
    public string $message;
    public string $code;
    public string $redirectPage;

    public function __construct(Page $page)
    {
        $errorCode = ErrorCode::tryFrom((int)($page->urlArr['subpages'][0] ?? 0));

        // If no valid error code, return a 404
        if (!$errorCode) {
            PageController::error(ErrorCode::NOT_FOUND);
            exit;
        }

        // $errorCode->value is an int backed enum value; cast to string for storage
        $this->code = (string)$errorCode->value;
        $this->message = $errorCode->message();
        $this->redirectPage = $page->urlArr['params']['redirect'] ?? REDIRECT;

        $page->subtitle = "Error $this->code";

        if (ERROR_AUTO_REDIRECT) PageController::redirect($this->redirectPage, 2);
    }
}
