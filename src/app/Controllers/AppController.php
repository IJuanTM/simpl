<?php

namespace app\Controllers;

use app\Enums\LogType;

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
        // Get the svg file
        $file = BASEDIR . "/public/img/svg/$name.svg";

        // Return the svg file if it exists, else return an error message
        if (file_exists($file)) return file_get_contents($file);
        else if (DEV) {
            // Log the error
            LogController::log("Could not find SVG \"$name\"", LogType::DEBUG);

            // Return an error message
            return "SVG \"$name\" not found";
        } else return "<!-- SVG \"$name\" not found -->";
    }
}
