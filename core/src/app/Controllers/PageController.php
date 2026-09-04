<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Enums\AlertType;
use app\Enums\ErrorCode;
use app\Models\Page;
use app\Models\Url;
use app\Utils\Log;

/**
 * Represents the controller responsible for handling incoming page requests, routing,
 * and rendering the appropriate page or response.
 *
 * This class interprets the request URI, identifies the intended page or API endpoint,
 * initializes the required page handler or model, and manages the lifecycle of the request.
 * For API calls, it invokes specific API methods. For standard pages, it renders
 * the corresponding view.
 */
class PageController extends Page
{
    public function __construct()
    {
        // strtok() runs first so a bare "?query" root URL also falls back to REDIRECT.
        $requestPath = strtok(strtolower(trim($_SERVER['REQUEST_URI'], '/')), '?');
        $urlArr = explode('/', $requestPath ?: REDIRECT);

        // Reject path-traversal segments so an unmapped $page can't require_once its way into another page's view via "..".
        if (in_array('.', $urlArr, true) || in_array('..', $urlArr, true)) {
            self::error(ErrorCode::NOT_FOUND);
            return;
        }

        $page = array_shift($urlArr);
        $params = $_GET;

        // Checked before the $ROUTES short-circuit too, so a registered route can't be used to
        // dodge CSRF validation on a POST request. A route that genuinely needs session-token-free
        // POST (e.g. a signed webhook) must verify its own signature before this point instead.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !AppController::validateCsrf()) {
            self::error(ErrorCode::FORBIDDEN);
            exit;
        }

        global $ROUTES;
        if (isset($ROUTES[$page])) {
            $ROUTES[$page]();
            return;
        }

        // Alias may override page, subpages and params.
        if ($alias = AliasController::resolve($page, $params)) [$page, $urlArr, $params] = [$alias['page'], $alias['subpages'], array_merge($params, $alias['params'])];

        $api = $page === 'api';
        if ($api) $page = array_shift($urlArr) ?? '';

        parent::__construct($page, $urlArr, $params);

        $class = 'app\\Pages\\' . str_replace(' ', '', ucwords(str_replace('-', ' ', $page))) . 'Page';
        if (class_exists($class)) $this->pageObj = new $class($this);

        if ($api) {
            if (!$this->pageObj || !method_exists($this->pageObj, 'api')) {
                if (DEV) Log::error("Page \"$page\" was called as an API endpoint, but no page object or API method was found");
                self::error(ErrorCode::NOT_FOUND);
                return;
            }

            $this->pageObj->api($this);
            return;
        }

        $this->render();
    }

    /**
     * Handles errors by redirecting to an appropriate error page.
     *
     * This method constructs an error URL based on the provided error code and an optional
     * redirect URL. The user is then redirected to the generated error page.
     *
     * @param ErrorCode   $code     The specific error code used to determine the error page.
     * @param string|null $redirect An optional URL to redirect back to after handling the error.
     *
     * @return void
     */
    public static function error(ErrorCode $code, ?string $redirect = null): void
    {
        self::redirect("error/$code->value" . ($redirect ? '?redirect=' . urlencode($redirect) : ''));
    }

    /**
     * Redirects the user to the specified location with an optional refresh delay.
     *
     * This method sends a header to redirect the user's browser to a given URL.
     * An optional refresh delay can be specified to control the time before the redirection occurs.
     *
     * @param string   $location The target location URL for the redirect.
     * @param int|null $refresh  Optional delay in seconds before the redirection. Defaults to 0 for immediate redirect.
     *
     * @return void
     */
    public static function redirect(string $location, ?int $refresh = 0): void
    {
        $url = Url::to($location);

        // Immediate redirects use a real 302 so crawlers and API clients follow correctly.
        // Delayed redirects keep a meta-style refresh so the current page (and its flash alert) is shown first.
        if ($refresh) {
            header("refresh: $refresh; url=$url");
            return;
        }

        http_response_code(302);
        header("Location: $url");
    }

    /**
     * Renders the current page by including the corresponding view file and surrounding HTML parts.
     *
     * This method determines the appropriate view file based on the requested page and subpage.
     * If a subpage is specified and its view file exists, it will be preferred over the main page view.
     * The method outputs the top HTML part (e.g., header) before including the view file,
     * and concludes with the bottom HTML part (e.g., footer). If the view file cannot be located,
     * it logs an error (in development mode) and triggers a "Not Found" error response.
     *
     * @return void
     */
    private function render(): void
    {
        $this->part('top');

        $page = $this->page;
        $subpage = $this->subpage();

        // Walk back from the most specific subpage to find the closest matching view file.
        $parts = [$page, ...$this->subpages];
        $file = null;

        while (count($parts) > 1) {
            $candidate = BASEDIR . '/views/' . implode('/', $parts) . '.phtml';

            if (is_file($candidate)) {
                $file = $candidate;
                break;
            }

            array_pop($parts);
        }

        $file ??= BASEDIR . "/views/$page.phtml";

        if (!is_file($file)) {
            if (DEV) Log::error("Could not find view \"$page" . ($subpage ? "/$subpage" : '') . "\"");
            self::error(ErrorCode::NOT_FOUND);
            return;
        }

        require_once $file;

        $this->part('bottom');
    }

    /**
     * Loads and includes a view part file based on the given name.
     *
     * Parts are the fixed page skeleton (header, footer, cookie, the index/ head fragments) that
     * the render pipeline assembles itself - never called from a view template, hence protected.
     * Each is included once per request and takes no data. For template-invoked, repeatable,
     * prop-taking fragments use component() instead.
     *
     * If the file is not found, a warning is logged and feedback is shown visibly in DEV or as an
     * HTML comment otherwise.
     *
     * @param string $name The name of the view part to load, relative to views/parts/.
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
     * Redirects the user while queuing a session-persisted flash alert to show after navigation.
     * Use this (not FormController::addAlert) whenever a message needs to survive a redirect.
     *
     * @param string    $location The target location URL for the redirect.
     * @param string    $message  The alert message to show after redirecting.
     * @param AlertType $type     Visual type/style for the alert.
     * @param int       $timeout  Seconds until the alert expires. 0 means it persists until the next page load.
     * @param int|null  $refresh  Optional delay in seconds before the redirection. Defaults to 0 for immediate redirect.
     *
     * @return void
     */
    public static function redirectWithAlert(string $location, string $message, AlertType $type, int $timeout = 0, ?int $refresh = 0): void
    {
        AlertController::globalAlert($message, $type, $timeout);
        self::redirect($location, $refresh);
    }

    /**
     * Retrieves the URL of the previous page in the user's navigation history.
     *
     * This method accesses the user's navigation history and returns the URL of the
     * second-to-last entry. If no valid previous entry exists, it returns a default URL.
     *
     * @return string The URL of the previous page or a default fallback URL.
     */
    public static function prev(): string
    {
        $history = Page::history();
        return Url::to($history[count($history) - 2] ?? REDIRECT);
    }

    /**
     * Navigates back to the previous page in the user's navigation history.
     *
     * This method updates the user's navigation history by removing the last entry
     * and redirects the user to the new last entry in the history. If no history is available,
     * it redirects to a default location.
     *
     * @return void
     */
    public static function back(): void
    {
        $updated = array_slice(Page::history(), 0, -1);
        SessionController::set('history', $updated);
        self::redirect(end($updated) ?: REDIRECT);
    }

    /**
     * Loads and includes a view component file, extracting the given props into its scope.
     *
     * Components are reusable fragments (breadcrumbs, modals, column-toggle, ...) that a view
     * template drops in via $this->component() wherever it wants them, hence public. Unlike a
     * part, a component may be rendered more than once per request and receives per-call data
     * through $props. For the fixed, framework-assembled page skeleton use part() instead.
     *
     * If the file is not found, a warning is logged and feedback is shown visibly in DEV or as an
     * HTML comment otherwise.
     *
     * @param string $name  The name of the view component to load, relative to views/components/.
     * @param array  $props Associative array of values extracted into the component's local scope.
     *
     * @return void
     */
    final public function component(string $name, array $props = []): void
    {
        $file = BASEDIR . "/views/components/$name.phtml";

        if (is_file($file)) {
            extract($props, EXTR_SKIP);
            require $file;
            return;
        }

        $message = "Component \"$name\" not found";
        Log::warning($message);

        if (DEV) echo $message;
        else echo "<!-- $message -->";
    }
}
