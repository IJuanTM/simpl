<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Utils\Log;

/**
 * The AppController class serves as the main application controller.
 * It initializes essential controllers needed for core functionality
 * and provides utility methods for application-specific operations.
 */
class AppController
{
    public function __construct()
    {
        // Start output buffering to allow content capture and late header changes
        ob_start();

        // Initialize essential controllers
        new SessionController();
        new AlertController();
        new AliasController();
        new PageController();
    }

    /**
     * Retrieves the contents of an SVG file by its name.
     *
     * @param string $name The name of the SVG file (without the .svg extension).
     *
     * @return bool|string The SVG file contents as a string, or a boolean false if the file does not exist.
     */
    public static function svg(string $name): bool|string
    {
        $file = BASEDIR . "/public/img/svg/$name.svg";

        if (!file_exists($file)) {
            $message = "SVG \"$name\" not found";
            Log::warning($message);
            return DEV ? $message : "<!-- $message -->";
        }

        return file_get_contents($file);
    }
}
