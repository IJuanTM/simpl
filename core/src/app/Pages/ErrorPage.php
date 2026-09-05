<?php

declare(strict_types=1);

namespace app\Pages;

use app\Controllers\PageController;
use app\Enums\ErrorCode;
use app\Models\Page;
use JsonException;

/**
 * Page class for the error view; $message, $code and $redirectPage are populated by resolve() for the template to render.
 */
class ErrorPage
{
    public string $message;
    public string $code;
    public string $redirectPage;

    public function __construct(Page $page)
    {
        $this->resolve($page);
    }

    /**
     * Resolves the error code from the URL subpage segment (404 if invalid), sets the HTTP
     * response code, message and redirect target, and triggers the configured auto-redirect if enabled.
     *
     * @param Page $page The page model containing the subpage and parameters.
     *
     * @return void
     *
     * @throws JsonException
     */
    private function resolve(Page $page): void
    {
        $errorCode = ErrorCode::tryFrom((int)($page->subpage() ?? 0));

        if (!$errorCode) {
            PageController::error(ErrorCode::NOT_FOUND);
            exit;
        }

        http_response_code($errorCode->value);

        $this->code = (string)$errorCode->value;
        $this->message = $errorCode->message();

        // Only accept a safe internal path from the user-controlled redirect param; fall back otherwise.
        $redirect = $page->param('redirect');
        $this->redirectPage = is_string($redirect) && $redirect !== '' && preg_match('#^[\w\-/.?=&%]+$#', $redirect) ? $redirect : REDIRECT;

        $page->subtitle = "Error $this->code";

        if (ERROR_AUTO_REDIRECT) PageController::redirect($this->redirectPage, 2);
    }
}
