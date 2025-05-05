<?php

namespace app\Controllers;

use app\Models\Page;
use app\Models\Url;

/**
 * The PageController class is the controller for the pages. It parses the URL and loads the page.
 * It also contains the methods for loading the needed HTML parts.
 */
class PageController extends Page
{
    public function __construct()
    {
        // Split the url route into an array and get the page and parameters
        $urlArr = explode('/', strtok(strtolower(trim($_SERVER['REQUEST_URI'], '/')) ?: REDIRECT, '?'));

        $page = array_shift($urlArr);
        $params = $_GET;

        // Check if the page is a special route, if so call the route method
        if (isset($ROUTES[$page])) {
            $ROUTES[$page]();
            return;
        }

        // Check if the page is an alias, if so set the page to the alias
        if ($alias = AliasController::resolve($page, $params)) [$page, $urlArr, $params] = [$alias['page'], $alias['subpages'], array_merge($params, $alias['params'])];

        // Check if the page is an API page, if so take the second part of the url as the page name
        $api = $page === 'api';
        if ($api) $page = array_shift($urlArr);

        // Create a page object from the current url, with the page name, subpages and parameters
        parent::__construct($page, $urlArr, $params);

        // Get the Page class that corresponds with the current page and create an object from it. Pass this object to the Page model if it exists.
        $class = 'app\Pages\\' . str_replace(' ', '', ucwords(str_replace('-', ' ', $page))) . 'Page';
        if (class_exists($class)) $this->pageObj = new $class($this);

        // Check if the page is called as an API endpoint
        if ($api) {
            // Check if the page object exists and the API method exists
            if (!$this->pageObj || !method_exists($this->pageObj, 'api')) {
                // Log the error if the environment is development
                if (DEV) LogController::log("Page \"$page\" was called as an API endpoint, but no page object or API method was found", 'debug');

                // Redirect to the 404 page
                self::redirect('error/404');
                return;
            }

            // Call the API method
            $this->pageObj->api($this);
            return;
        }

        // Render the page
        $this->render();
    }

    /**
     * Redirect to the given location with the given delay.
     *
     * @param string $location
     * @param int|null $refresh
     *
     * @return void
     */
    public static function redirect(string $location, ?int $refresh = null): void
    {
        $url = Url::to($location);
        header($refresh ? "Refresh: $refresh; url=$url" : "Location: $url", true, 302);
    }

    /**
     * Render the page by loading the needed HTML parts and the content of the page. If the page does not exist, redirect to the 404 page.
     *
     * @return void
     */
    private function render(): void
    {
        // Get start of HTML and the HEAD
        $this->part('top');

        // Get the page name
        $page = $this->urlArr['page'];

        // Get the file from the views folder
        $file = BASEDIR . "/views/$page.phtml";

        // Check if the file exists, if not redirect to the 404 page
        if (!is_file($file)) {
            // Log the error if the environment is development
            if (DEV) LogController::log("Could not find view \"$page\"", 'debug');

            // Redirect to the 404 page
            self::redirect('error/404');
            return;
        }

        // Get the content of the BODY -> SECTION
        require_once $file;

        // Get the footer part and end of HTML
        $this->part('bottom');
    }

    /**
     * Load the needed HTML parts. It takes a name as input and loads the corresponding part from the parts folder.
     *
     * @param string $name
     *
     * @return void
     */
    private function part(string $name): void
    {
        // Get the file from the parts folder
        $file = BASEDIR . "/views/parts/$name.phtml";

        // Check if the file exists, if so require it
        if (is_file($file)) {
            require_once $file;
            return;
        }

        $message = "Part \"$name\" not found";

        if (DEV) {
            // Write a debug log message
            LogController::log($message, 'debug');
            echo $message;
        } else echo "<!-- $message -->";
    }

    /**
     * Return the previous page URL from session history.
     *
     * @return string
     */
    public static function prev(): string
    {
        $history = self::history();

        // Return the previous page URL or a default redirect URL
        return Url::to($history[count($history) - 2] ?? REDIRECT);
    }

    /**
     * Redirect to the previous page.
     *
     * @return void
     */
    public static function back(): void
    {
        // Get the current history
        $updated = array_slice(self::history(), 0, -2);

        // Update the session history
        SessionController::set('history', $updated);

        // Redirect to the previous page
        self::redirect(end($updated) ?: REDIRECT);
    }
}
