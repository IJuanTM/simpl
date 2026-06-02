<?php

declare(strict_types=1);

namespace app\Controllers;

/**
 * Provides read access to $_POST and $_GET request data.
 *
 * post()/get() sanitize with htmlspecialchars and are safe for display output.
 * rawPost() returns the untouched value; use it in validation, auth, and DB operations
 * where htmlspecialchars would corrupt the data.
 * Returns null when a key is not present, enabling ?? fallback chains in views.
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
     * Returns the raw (unsanitized) value of a POST field, or null if not set.
     * Use for validation logic, password handling, and any value going into a DB query.
     *
     * @param string $key Field name.
     *
     * @return string|null
     */
    public static function rawPost(string $key): ?string
    {
        return $_POST[$key] ?? null;
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
