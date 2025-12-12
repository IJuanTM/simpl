<?php

namespace app\Controllers;

use app\Utils\Log;

/**
 * The ApplicationController class is the base class for all controllers.
 * It contains methods that are used by all controllers. It also contains the autoloader.
 */
class AppController
{
    public function __construct()
    {
        // Start the output buffer
        ob_start();

        // Initialize controllers
        new SessionController();
        new AlertController();
        new AliasController();
        new PageController();
    }

    /**
     * Method for loading a svg file and returning it as a string.
     *
     * @param string $name
     *
     * @return bool|string
     */
    public static function svg(string $name): bool|string
    {
        // Get the full path to the svg file
        $file = BASEDIR . "/public/img/svg/$name.svg";

        // Check if the file exists
        if (!file_exists($file)) {
            $message = "SVG \"$name\" not found";

            // Log the warning
            Log::warning($message);

            return DEV ? $message : "<!-- $message -->";
        }

        return file_get_contents($file);
    }
}
