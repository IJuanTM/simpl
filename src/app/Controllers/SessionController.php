<?php

namespace app\Controllers;

/**
 * The SessionController class is the controller for accessing session variables.
 * It contains methods for setting, getting, checking, and removing session variables.
 */
class SessionController
{
    public function __construct()
    {
        // Set session lifetime
        ini_set('session.gc_maxlifetime', 86400 * SESSION_LIFETIME);
        session_set_cookie_params(86400 * SESSION_LIFETIME);

        // Start the session
        session_start();
    }

    /**
     * Method for setting a session variable.
     *
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Method for getting a session variable.
     *
     * @param string $key
     *
     * @return mixed
     */
    public static function get(string $key): mixed
    {
        return self::has($key) ? $_SESSION[$key] : null;
    }

    /**
     * Method for checking if a session variable exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Method for removing a session variable.
     *
     * @param string $key
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }
}
