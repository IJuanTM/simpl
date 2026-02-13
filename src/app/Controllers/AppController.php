<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Utils\Log;

/**
 * Application bootstrap controller
 *
 * Responsible for early application initialization tasks such as starting the
 * output buffer and creating essential helper controllers. This controller is
 * lightweight and intentionally uses side-effects in the constructor to ensure
 * the environment is ready for subsequent request handling.
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
     * Load an SVG file and return its contents or a fallback string.
     *
     * Note: `file_get_contents()` will return false on failure; the method
     * preserves the original behaviour by returning a string in normal
     * circumstances (file contents or HTML comment) or the raw false value when
     * PHP fails to read the file.
     *
     * @param string $name SVG filename (without extension)
     *
     * @return bool|string File contents, HTML comment fallback, or false if read fails
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
