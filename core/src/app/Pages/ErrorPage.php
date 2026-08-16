<?php

declare(strict_types=1);

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
        $errorCode = ErrorCode::tryFrom((int)($page->subpage() ?? 0));

        if (!$errorCode) {
            PageController::error(ErrorCode::NOT_FOUND);
            exit;
        }

        // $errorCode->value is an int backed enum value; cast to string for storage
        $this->code = (string)$errorCode->value;
        $this->message = $errorCode->message();

        // Only accept a safe internal path from the user-controlled redirect param; fall back otherwise.
        $redirect = $page->param('redirect');
        $this->redirectPage = is_string($redirect) && $redirect !== '' && preg_match('#^[\w\-/.?=&%]+$#', $redirect) ? $redirect : REDIRECT;

        $page->subtitle = "Error $this->code";

        if (ERROR_AUTO_REDIRECT) PageController::redirect($this->redirectPage, 2);
    }
}
