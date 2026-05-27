<?php

declare(strict_types=1);

namespace app\Controllers;

/**
 * Provides sanitized read access to $_POST and $_GET request data.
 *
 * Returns null when a key is not present, enabling ?? fallback chains in views.
 * Form handlers that need raw values for database operations should read $_POST directly.
 */
class RequestController
{
    /**
     * Returns the sanitized value of a POST field, or null if not set.
     *
     * @param string $key Field name.
     *
     * @return string|null
     */
    public static function post(string $key): ?string
    {
        return isset($_POST[$key]) ? AppController::sanitize($_POST[$key]) : null;
    }

    /**
     * Returns the sanitized value of a GET parameter, or null if not set.
     *
     * @param string $key Parameter name.
     *
     * @return string|null
     */
    public static function get(string $key): ?string
    {
        return isset($_GET[$key]) ? AppController::sanitize($_GET[$key]) : null;
    }
}
