<?php

namespace app\Controllers;

use app\Models\PageModel;

/**
 * The PageController class is the controller for the pages. It parses the URL and loads the page.
 * It also contains the methods for loading the needed HTML parts.
 */
class PageController extends PageModel
{
    private array $aliases = [
        'home' => [
            'index',
            'start'
        ]
    ];

    public function __construct()
    {
        // Split the url route into an array
        $urlArr = explode('/', explode('?', trim(strtolower($_SERVER['REQUEST_URI']), '/') ?: REDIRECT)[0]);

        // Create the a page object from the current url, with the page name, subpages and parameters
        parent::__construct(array_shift($urlArr), $urlArr, $_GET);

        // Check if the page is an alias, if so set the page to the alias
        foreach ($this->aliases as $alias => $pages) if (in_array($this->urlArr['page'], $pages)) {
            $this->urlArr['page'] = $alias;
            break;
        }

        // Get the Page class that corresponds with the current page and create an object from it. Pass this object to the PageModel if it exists.
        $obj = 'app\Pages\\' . str_replace(' ', '', ucwords(str_replace('-', ' ', $this->urlArr['page']))) . 'Page';
        if (class_exists($obj)) $this->pageObj = new $obj($this);

        // Load the page
        $this->load();
    }

    /**
     * Method for loading the page. It loads the needed PHP class (Page) that corresponds with the page and loads the needed HTML parts.
     * It also checks if the page exists. If not, it redirects to the 404 page.
     *
     * @return void
     */
    private function load(): void
    {
        // Get start of HTML and the HEAD
        $this->part('top');

        // Get the page name
        $page = $this->urlArr['page'];

        // Get the file from the views folder
        $file = BASEDIR . "/views/$page.phtml";

        // Get the content of the BODY -> SECTION
        if (file_exists($file)) require_once $file;
        else {
            // Log the error if the environment is development
            if (DEV) LogController::log("Could not find view \"$page\"", 'debug');

            // Redirect to the 404 page
            self::redirect('error/404');
        }

        // Get the footer part and end of HTML
        $this->part('bottom');
    }

    /**
     * Method for loading a part from the parts folder.
     *
     * @param string $name
     *
     * @return void
     */
    private function part(string $name): void
    {
        // Get the file from the parts folder
        $file = BASEDIR . "/views/parts/$name.phtml";

        // Check if the file exists, if so load the file
        if (file_exists($file)) require_once $file;
        else {
            if (DEV) {
                // Log the error
                LogController::log("Could not load part \"$name\"", 'debug');

                // Print an error message
                echo "Part \"$name\" not found";
            } else echo "<!-- Part \"$name\" not found -->";
        }
    }

    /**
     * Method for redirecting to another page. It takes a delay and a location as input and redirects to the given location with the given delay.
     *
     * @param string $location
     * @param int|null $refresh
     *
     * @return void
     */
    public static function redirect(string $location, int|null $refresh = 0): void
    {
        // Redirect to the given location with the given delay
        header("refresh: $refresh; url=" . self::url($location));
    }

    /**
     * Method for creating a URL. It takes a sub URL as input and returns a complete URL path.
     *
     * @param string $subUrl
     *
     * @return string
     */
    public static function url(string $subUrl = ''): string
    {
        static $baseUrl;

        // Get the root directory and remove the trailing slash
        $rootDir = rtrim(BASEDIR, '/');

        // Get the base URL of the website
        if (!$baseUrl) $baseUrl = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . preg_replace('@^' . preg_quote($rootDir) . '@', '', BASEDIR);

        // Create the URL
        $url = rtrim($baseUrl, '/') . '/' . ltrim($subUrl, '/');

        // Get the file path
        $file = $rootDir . '/public/' . ltrim($subUrl, '/');

        // Check if the file exists, if so add the version to the URL
        if (is_file($file)) return strtok($url, '#') . (str_contains($url, '?') ? '&' : '?') . 'v=' . filemtime($file) . (str_contains($url, '#') ? '#' . explode('#', $url, 2)[1] : '');

        return rtrim($url, '/');
    }
}
