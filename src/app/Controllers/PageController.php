<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Enums\ErrorCode;
use app\Models\Page;
use app\Models\Url;
use app\Utils\Log;

/**
 * PageController
 *
 * Responsible for parsing the incoming request URI, resolving routes and
 * aliases, instantiating page handler classes and dispatching requests to them
 * (including API endpoints). Inherits from the Page model which stores route
 * information and history.
 *
 * Notes:
 * - The constructor uses several superglobals ($_SERVER, $_GET) and an
 *   external $ROUTES map. Consider refactoring to inject a Request/Router in
 *   the future to improve testability.
 */
class PageController extends Page
{
    public function __construct()
    {
        // Split the request URI into components and extract query parameters
        $urlArr = explode('/', strtok(strtolower(trim($_SERVER['REQUEST_URI'], '/')) ?: REDIRECT, '?'));

        $page = array_shift($urlArr);
        $params = $_GET;

        // Check for special route handlers (external $ROUTES)
        global $ROUTES;
        if (isset($ROUTES[$page])) {
            $ROUTES[$page]();
            return;
        }

        // Resolve alias if present; alias may override page, subpages and params
        if ($alias = AliasController::resolve($page, $params)) [$page, $urlArr, $params] = [$alias['page'], $alias['subpages'], array_merge($params, $alias['params'])];

        // Detect API calls (first segment 'api') and set page accordingly
        $api = $page === 'api';
        if ($api) $page = array_shift($urlArr);

        // Initialize Page model with resolved route data
        parent::__construct($page, $urlArr, $params);

        // Instantiate the page handler class if it exists
        $class = 'app\\Pages\\' . str_replace(' ', '', ucwords(str_replace('-', ' ', $page))) . 'Page';
        if (class_exists($class)) $this->pageObj = new $class($this);

        // If called as API, dispatch to the page object's api() method
        if ($api) {
            if (!$this->pageObj || !method_exists($this->pageObj, 'api')) {
                if (DEV) Log::error("Page \"$page\" was called as an API endpoint, but no page object or API method was found");
                self::error(ErrorCode::NOT_FOUND);
                return;
            }

            $this->pageObj->api($this);
            return;
        }

        // Render the page normally
        $this->render();
    }

    /**
     * Redirect to an error page based on an ErrorCode value.
     *
     * @param ErrorCode $code Error enumeration value
     * @param string|null $redirect Optional redirect URL to include
     *
     * @return void
     */
    public static function error(ErrorCode $code, ?string $redirect = null): void
    {
        // Build error URL and redirect
        self::redirect("error/$code->value" . ($redirect ? '?redirect=' . urlencode($redirect) : ''));
    }

    /**
     * Send a refresh header to redirect the client after an optional delay.
     *
     * @param string $location Destination path or URL
     * @param int|null $refresh Delay in seconds before redirect (0 = immediate)
     *
     * @return void
     */
    public static function redirect(string $location, ?int $refresh = 0): void
    {
        header("refresh: $refresh; url=" . Url::to($location));
    }

    /**
     * Render the page: include top, page view, and bottom parts.
     * Shows 404 if view file is missing.
     *
     * @return void
     */
    private function render(): void
    {
        // Output top HTML part (head, header)
        $this->part('top');

        $page = $this->urlArr['page'];
        $subpage = $this->urlArr['subpages'][0] ?? null;

        // Prefer subpage view when present
        $file = $subpage && is_file(BASEDIR . "/views/$page/$subpage.phtml")
            ? BASEDIR . "/views/$page/$subpage.phtml"
            : BASEDIR . "/views/$page.phtml";

        if (!is_file($file)) {
            if (DEV) Log::error("Could not find view \"$page\"" . ($subpage ? "/$subpage" : ''));
            self::error(ErrorCode::NOT_FOUND);
            return;
        }

        // Include page content
        require_once $file;

        // Output bottom HTML part (footer, scripts)
        $this->part('bottom');
    }

    /**
     * Include a reusable part from views/parts (e.g. header, footer).
     *
     * @param string $name Part name (file without extension)
     *
     * @return void
     */
    final protected function part(string $name): void
    {
        $file = BASEDIR . "/views/parts/$name.phtml";

        if (is_file($file)) {
            require_once $file;
            return;
        }

        $message = "Part \"$name\" not found";
        Log::warning($message);

        if (DEV) echo $message;
        else echo "<!-- $message -->";
    }

    /**
     * Get the previous page URL from session history.
     *
     * @return string
     */
    public static function prev(): string
    {
        $history = Page::history();
        return Url::to($history[count($history) - 2] ?? REDIRECT);
    }

    /**
     * Go back to the previous page and update session history.
     *
     * @return void
     */
    public static function back(): void
    {
        $updated = array_slice(Page::history(), 0, -2);
        SessionController::set('history', $updated);
        self::redirect(end($updated) ?: REDIRECT);
    }
}
